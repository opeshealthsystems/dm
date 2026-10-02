<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Orders\Http\Controllers\CartController;
use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API, version 1
|--------------------------------------------------------------------------
| Mounted at /api (Laravel default); every route below lives under /api/v1.
| Module routes are registered here until each module ships its own file.
*/

Route::prefix('v1')->group(function () {

    // --- Identity -------------------------------------------------------
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:api', 'throttle:api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->middleware('scopes:profile');
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    // --- Catalog: public reads -----------------------------------------
    Route::middleware('throttle:api')->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{slug}', [ProductController::class, 'show']);
    });

    // --- Catalog: vendor writes (OAuth scope `catalog:write`) ----------
    Route::middleware(['auth:api', 'scopes:catalog:write', 'throttle:api'])->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
    });

    // --- Orders: reads (scope `orders:read`) ----------------------------
    Route::middleware(['auth:api', 'scopes:orders:read', 'throttle:api'])->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
    });

    // --- Cart + buyer order actions (scope `orders:write`) --------------
    Route::middleware(['auth:api', 'scopes:orders:write', 'throttle:api'])->group(function () {
        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/items', [CartController::class, 'add']);
        Route::patch('cart/items/{productId}', [CartController::class, 'update'])->whereNumber('productId');
        Route::delete('cart/items/{productId}', [CartController::class, 'remove'])->whereNumber('productId');
        Route::delete('cart', [CartController::class, 'clear']);

        Route::post('orders', [OrderController::class, 'store']);
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    });

    // --- Vendor fulfilment (scope `vendor:manage`) ----------------------
    Route::middleware(['auth:api', 'scopes:vendor:manage', 'throttle:api'])->group(function () {
        Route::post('orders/{order}/ship', [OrderController::class, 'ship']);
    });
});
