<?php

use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Orders\Http\Controllers\CartController;
use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Reputation\Http\Controllers\ReviewController;
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

    // --- Reputation: public reads ---------------------------------------
    Route::middleware('throttle:api')->group(function () {
        Route::get('products/{product}/reviews', [ReviewController::class, 'productReviews'])->whereNumber('product');
        Route::get('vendors/{vendor}', [ReviewController::class, 'vendor'])->whereNumber('vendor');
        Route::get('vendors/{vendor}/reviews', [ReviewController::class, 'vendorReviews'])->whereNumber('vendor');
    });

    // --- Reputation: buyer writes (scope `orders:write`) ----------------
    Route::middleware(['auth:api', 'scopes:orders:write', 'throttle:api'])->group(function () {
        Route::post('products/{product}/reviews', [ReviewController::class, 'store'])->whereNumber('product');
    });

    // --- Reputation: vendor reply (scope `vendor:manage`) ---------------
    Route::middleware(['auth:api', 'scopes:vendor:manage', 'throttle:api'])->group(function () {
        Route::post('reviews/{review}/reply', [ReviewController::class, 'reply'])->whereNumber('review');
    });

    // --- Reputation: follow + helpful votes (scope `profile`) -----------
    Route::middleware(['auth:api', 'scopes:profile', 'throttle:api'])->group(function () {
        Route::post('vendors/{vendor}/follow', [ReviewController::class, 'follow'])->whereNumber('vendor');
        Route::delete('vendors/{vendor}/follow', [ReviewController::class, 'unfollow'])->whereNumber('vendor');
        Route::post('reviews/{review}/helpful', [ReviewController::class, 'vote'])->whereNumber('review');
        Route::delete('reviews/{review}/helpful', [ReviewController::class, 'unvote'])->whereNumber('review');
    });

    // --- Messaging: reads (scope `orders:read`) -------------------------
    Route::middleware(['auth:api', 'scopes:orders:read', 'throttle:api'])->group(function () {
        Route::get('conversations', [\App\Modules\Messaging\Http\Controllers\ConversationController::class, 'index']);
        Route::get('conversations/unread-count', [\App\Modules\Messaging\Http\Controllers\ConversationController::class, 'unread']);
        Route::get('conversations/{conversation}', [\App\Modules\Messaging\Http\Controllers\ConversationController::class, 'show'])->whereNumber('conversation');
        Route::post('conversations/{conversation}/read', [\App\Modules\Messaging\Http\Controllers\ConversationController::class, 'read'])->whereNumber('conversation');
        Route::get('blocks', [\App\Modules\Messaging\Http\Controllers\BlockController::class, 'index']);
        Route::get('notifications', [\App\Modules\Messaging\Http\Controllers\NotificationController::class, 'index']);
        Route::post('notifications/read-all', [\App\Modules\Messaging\Http\Controllers\NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [\App\Modules\Messaging\Http\Controllers\NotificationController::class, 'read'])->whereNumber('notification');
    });

    // --- Messaging: writes (buyers `orders:write` or vendors `vendor:manage`), rate limited ---
    Route::middleware(['auth:api', 'scope:orders:write,vendor:manage', 'throttle:api'])->group(function () {
        Route::post('conversations', [\App\Modules\Messaging\Http\Controllers\ConversationController::class, 'store'])->middleware('throttle:messages');
        Route::post('conversations/{conversation}/messages', [\App\Modules\Messaging\Http\Controllers\ConversationController::class, 'send'])
            ->whereNumber('conversation')->middleware('throttle:messages');
        Route::post('users/{user}/block', [\App\Modules\Messaging\Http\Controllers\BlockController::class, 'store'])->whereNumber('user');
        Route::delete('users/{user}/block', [\App\Modules\Messaging\Http\Controllers\BlockController::class, 'destroy'])->whereNumber('user');
    });

    // --- Developer platform: key/webhook/usage management (OAuth, scope `vendor:manage`) ---
    Route::middleware(['auth:api', 'scopes:vendor:manage', 'throttle:api'])->prefix('developer')->group(function () {
        $keys = \App\Modules\DeveloperPlatform\Http\Controllers\ApiKeyController::class;
        $hooks = \App\Modules\DeveloperPlatform\Http\Controllers\WebhookEndpointController::class;
        Route::get('keys', [$keys, 'index']);
        Route::post('keys', [$keys, 'store']);
        Route::delete('keys/{key}', [$keys, 'destroy'])->whereNumber('key');
        Route::get('usage', [\App\Modules\DeveloperPlatform\Http\Controllers\UsageController::class, 'index']);
        Route::get('webhooks', [$hooks, 'index']);
        Route::post('webhooks', [$hooks, 'store']);
        Route::get('webhooks/{endpoint}', [$hooks, 'show'])->whereNumber('endpoint');
        Route::put('webhooks/{endpoint}', [$hooks, 'update'])->whereNumber('endpoint');
        Route::delete('webhooks/{endpoint}', [$hooks, 'destroy'])->whereNumber('endpoint');
        Route::get('webhooks/{endpoint}/deliveries', [$hooks, 'deliveries'])->whereNumber('endpoint');
    });

    // --- Developer platform: API-key authenticated reads (`Bearer dm_live_...`) ---
    Route::prefix('developer')->group(function () {
        $mw = \App\Modules\DeveloperPlatform\Http\Middleware\AuthenticateApiKey::class;
        Route::get('catalog/products', [ProductController::class, 'index'])->middleware([$mw . ':catalog:read']);
        Route::middleware([$mw . ':orders:read'])->group(function () {
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
        });
    });
});
