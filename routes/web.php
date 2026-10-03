<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{code}', [PageController::class, 'locale'])->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/dashboard', [PageController::class, 'dashboard'])->middleware('auth')->name('dashboard');

/*
| Dashboard areas. One page per entry in config/navigation.php, so the menu and the routes
| can never disagree. Detail pages (e.g. an order) are added under each area as needed.
*/
$areas = [
    'buyer' => ['prefix' => 'account', 'roles' => 'buyer,vendor,admin'],
    'seller' => ['prefix' => 'seller', 'roles' => 'vendor,admin'],
    'admin' => ['prefix' => 'admin', 'roles' => 'admin'],
];

foreach ($areas as $area => $cfg) {
    Route::middleware(['auth', "role:{$cfg['roles']}"])->prefix($cfg['prefix'])->group(function () use ($area, $cfg) {
        foreach (config("navigation.$area") as $item) {
            $page = substr($item['route'], strlen($area) + 1);
            Route::get($page, fn () => app(PageController::class)->show($area, $page))->name($item['route']);
        }
        Route::redirect('/', '/' . $cfg['prefix'] . '/' . substr(config("navigation.$area")[0]['route'], strlen($area) + 1));
    });
}

/*
| Seller detail pages. The data comes from /api/v1 in the browser; the route only carries the id.
*/
Route::middleware(['auth', 'role:vendor,admin'])->prefix('seller')->group(function () {
    Route::get('orders/{order}', fn (int $order) => view('seller.order', ['area' => 'seller', 'orderId' => $order]))
        ->whereNumber('order')->name('seller.orders.show');
    Route::get('products/new', fn () => view('seller.product-form', ['area' => 'seller', 'productId' => null]))
        ->name('seller.products.create');
    Route::get('products/{product}/edit', fn (int $product) => view('seller.product-form', ['area' => 'seller', 'productId' => $product]))
        ->whereNumber('product')->name('seller.products.edit');
});

// Buyer detail pages (data comes from /api/v1; the API enforces ownership).
Route::middleware(['auth', 'role:buyer,vendor,admin'])->prefix('account')->group(function () {
    Route::get('orders/{order}', [StorefrontController::class, 'order'])->whereNumber('order')->name('buyer.orders.show');
});

// Public storefront.
Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/p/{slug}', [StorefrontController::class, 'product'])->name('store.product');
Route::get('/v/{vendor}', [StorefrontController::class, 'vendor'])->whereNumber('vendor')->name('store.vendor');
