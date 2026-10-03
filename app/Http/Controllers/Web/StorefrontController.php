<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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

    public function product(string $slug): View
    {
        return view('store.product', ['area' => 'buyer', 'slug' => $slug]);
    }

    public function vendor(int $vendor): View
    {
        return view('store.vendor', ['area' => 'buyer', 'vendorId' => $vendor]);
    }

    public function order(int $order): View
    {
        return view('buyer.order', ['area' => 'buyer', 'orderId' => $order]);
    }
}
