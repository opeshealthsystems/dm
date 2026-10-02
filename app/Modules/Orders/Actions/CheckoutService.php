<?php

namespace App\Modules\Orders\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\CartItem;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    /**
     * Turn the buyer's cart into one order per vendor.
     *
     * Everything happens in one transaction: product rows are locked, stock is checked and
     * decremented, prices are snapshotted into the order lines, and the cart is emptied.
     * Any failure rolls the whole checkout back.
     *
     * @return Collection<int, Order>
     */
    public function placeOrders(User $buyer, string $shippingAddress, string $paymentMethod): Collection
    {
        $orders = DB::transaction(function () use ($buyer, $shippingAddress, $paymentMethod) {
            $cart = CartItem::where('user_id', $buyer->id)->orderBy('id')->get();
            if ($cart->isEmpty()) {
                throw new OrderException('Your cart is empty.', 422);
            }

            // Lock products in a stable order to avoid deadlocks between concurrent checkouts.
            $products = Product::whereIn('id', $cart->pluck('product_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $created = collect();
            $byVendor = $cart->groupBy(fn (CartItem $i) => $products[$i->product_id]->vendor_id ?? 0);

            foreach ($byVendor as $vendorId => $lines) {
                $currencies = $lines->map(fn ($l) => $products[$l->product_id]->currency)->unique();
                if ($currencies->count() > 1) {
                    throw new OrderException('Items from one vendor must share a currency.', 422);
                }

                $order = new Order();
                $order->number = 'DM-' . Str::upper(Str::random(10));
                $order->buyer_id = $buyer->id;
                $order->vendor_id = $vendorId;
                $order->currency = $currencies->first();
                $order->payment_method = $paymentMethod;
                $order->shipping_address = $shippingAddress;
                $order->subtotal_cents = 0;
                $order->save();

                $subtotal = 0;
                foreach ($lines as $line) {
                    $product = $products[$line->product_id];

                    if ($product->status !== Product::STATUS_ACTIVE) {
                        throw new OrderException("'{$product->title}' is no longer available.");
                    }
                    if ($product->vendor_id === $buyer->id) {
                        throw new OrderException('You cannot buy your own product.', 422);
                    }
                    if ($product->stock < $line->quantity) {
                        throw new OrderException("Only {$product->stock} of '{$product->title}' left in stock.");
                    }

                    $product->decrement('stock', $line->quantity);

                    $lineTotal = $product->price_cents * $line->quantity;
                    $subtotal += $lineTotal;
                    $order->items()->create([
                        'product_id' => $product->id,
                        'title' => $product->title,
                        'unit_price_cents' => $product->price_cents,
                        'quantity' => $line->quantity,
                        'line_total_cents' => $lineTotal,
                    ]);
                }

                $order->subtotal_cents = $subtotal;
                $order->save();
                $created->push($order->refresh()); // load DB defaults (status, escrow, timestamps)
            }

            CartItem::where('user_id', $buyer->id)->delete();

            return $created;
        });

        $orders->each(fn (Order $o) => event(new OrderPlaced($o)));

        return $orders;
    }
}
