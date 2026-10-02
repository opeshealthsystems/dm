<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Http\Requests\CartItemRequest;
use App\Modules\Orders\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /** Show the authenticated user's cart with live prices and totals per currency. */
    public function show(Request $request): JsonResponse
    {
        $items = CartItem::with('product:id,title,slug,price_cents,currency,stock,status,vendor_id')
            ->where('user_id', $request->user()->id)->orderBy('id')->get();

        $lines = $items->map(fn (CartItem $i) => [
            'product_id' => $i->product_id,
            'title' => $i->product->title,
            'slug' => $i->product->slug,
            'vendor_id' => $i->product->vendor_id,
            'unit_price_cents' => $i->product->price_cents,
            'currency' => $i->product->currency,
            'quantity' => $i->quantity,
            'line_total_cents' => $i->product->price_cents * $i->quantity,
            'available' => $i->product->status === Product::STATUS_ACTIVE && $i->product->stock >= $i->quantity,
        ]);

        return response()->json([
            'data' => $lines->values(),
            'totals' => $lines->groupBy('currency')->map(fn ($g) => $g->sum('line_total_cents')),
        ]);
    }

    /** Add a product (quantities add up if it is already in the cart). */
    public function add(CartItemRequest $request): JsonResponse
    {
        $product = Product::active()->findOrFail($request->integer('product_id'));

        if ($product->vendor_id === $request->user()->id) {
            throw new OrderException('You cannot buy your own product.', 422);
        }

        $item = CartItem::firstOrNew(['user_id' => $request->user()->id, 'product_id' => $product->id]);
        $item->quantity = ($item->exists ? $item->quantity : 0) + $request->integer('quantity');

        if ($item->quantity > $product->stock) {
            throw new OrderException("Only {$product->stock} of '{$product->title}' in stock.", 422);
        }
        $item->save();

        return $this->show($request)->setStatusCode(201);
    }

    /** Set the quantity of a product already in the cart. */
    public function update(CartItemRequest $request, int $productId): JsonResponse
    {
        $item = CartItem::where('user_id', $request->user()->id)->where('product_id', $productId)->firstOrFail();
        $item->update(['quantity' => $request->integer('quantity')]);

        return $this->show($request);
    }

    /** Remove one product from the cart. */
    public function remove(Request $request, int $productId): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->where('product_id', $productId)->delete();

        return $this->show($request);
    }

    /** Empty the cart. */
    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json(null, 204);
    }
}
