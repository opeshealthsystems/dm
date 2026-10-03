<?php

use App\Modules\Admin\Http\Controllers\AuditLogController;
use App\Modules\Admin\Http\Controllers\CategoryAdminController;
use App\Modules\Admin\Http\Controllers\OrderAdminController;
use App\Modules\Admin\Http\Controllers\ProductAdminController;
use App\Modules\Admin\Http\Controllers\SettingsController;
use App\Modules\Admin\Http\Controllers\StatsController;
use App\Modules\Admin\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Route;

/*
| Admin API, mounted under /api/v1/admin by routes/api.php. Requires the `admin` OAuth scope.
*/
Route::middleware(['auth:api', 'scopes:admin', 'throttle:api'])->prefix('admin')->group(function () {
    Route::get('stats', StatsController::class);
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    Route::get('users', [UserAdminController::class, 'index']);
    Route::get('users/{user}', [UserAdminController::class, 'show'])->whereNumber('user');
    Route::post('users/{user}/suspend', [UserAdminController::class, 'suspend'])->whereNumber('user');
    Route::post('users/{user}/unsuspend', [UserAdminController::class, 'unsuspend'])->whereNumber('user');
    Route::post('users/{user}/verify', [UserAdminController::class, 'verify'])->whereNumber('user');
    Route::post('users/{user}/unverify', [UserAdminController::class, 'unverify'])->whereNumber('user');
    Route::put('users/{user}/role', [UserAdminController::class, 'changeRole'])->whereNumber('user');

    Route::get('products', [ProductAdminController::class, 'index']);
    Route::post('products/{product}/archive', [ProductAdminController::class, 'archive'])->whereNumber('product');
    Route::post('products/{product}/restore', [ProductAdminController::class, 'restore'])->whereNumber('product');

    Route::get('categories', [CategoryAdminController::class, 'index']);
    Route::post('categories', [CategoryAdminController::class, 'store']);
    Route::put('categories/{category}', [CategoryAdminController::class, 'update'])->whereNumber('category');
    Route::delete('categories/{category}', [CategoryAdminController::class, 'destroy'])->whereNumber('category');

    Route::get('orders', [OrderAdminController::class, 'index']);
    Route::get('orders/{order}', [OrderAdminController::class, 'show'])->whereNumber('order');

    Route::get('support', [\App\Modules\ContentSeo\Http\Controllers\SupportAdminController::class, 'index']);
    Route::post('support/{supportRequest}/handle', [\App\Modules\ContentSeo\Http\Controllers\SupportAdminController::class, 'handle'])->whereNumber('supportRequest');

    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);
});
