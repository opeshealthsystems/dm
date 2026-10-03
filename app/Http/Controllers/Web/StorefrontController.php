<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Contracts\View\View;

/**
 * Public storefront and buyer detail pages. The pages are shells: all data is loaded from
 * /api/v1 in the browser, so the API stays the single source of truth and enforces access.
 * 'area' => 'buyer' makes the buyer translations available to the page's JavaScript.
 */
class StorefrontController extends Controller
{
    public function home(): View
    {
        return view('home', ['area' => 'buyer']);
    }

    /**
     * The product page is rendered on the server (title, description, price, JSON-LD) so search
     * engines and link previews see it; Alpine then takes over and refreshes it from the API.
     * Unknown or inactive products are a real 404, except for their owner and admins.
     */
    public function product(string $slug): View
    {
        $product = Product::query()->with('vendor:id,handle,shop_name')->where('slug', $slug)->first();
        $user = auth()->user();
        $canSee = $product && ($product->status === Product::STATUS_ACTIVE
            || ($user && ($user->id === $product->vendor_id || $user->role === User::ROLE_ADMIN)));
        abort_unless($canSee, 404);

        return view('store.product', [
            'area' => 'buyer',
            'slug' => $slug,
            'product' => $product,
            'indexable' => $product->status === Product::STATUS_ACTIVE,
        ]);
    }

    public function vendor(int $vendor): View
    {
        $user = User::query()->where('role', User::ROLE_VENDOR)->findOrFail($vendor);

        return view('store.vendor', [
            'area' => 'buyer',
            'vendorId' => $vendor,
            'vendor' => $user,
            'indexable' => $user->suspended_at === null,
        ]);
    }

    public function order(int $order): View
    {
        return view('buyer.order', ['area' => 'buyer', 'orderId' => $order]);
    }
}
