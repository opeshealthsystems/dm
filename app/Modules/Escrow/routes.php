<?php

use App\Modules\Escrow\Http\Controllers\DisputeController;
use App\Modules\Escrow\Http\Controllers\FeeController;
use Illuminate\Support\Facades\Route;

/* Loaded by EscrowServiceProvider under the `api` prefix and middleware group: /api/v1/... */

Route::prefix('v1')->group(function () {
    // Disputes: buyers (`orders:write`) or vendors (`vendor:manage`) open; parties read/message.
    Route::middleware(['auth:api', 'scope:orders:write,vendor:manage', 'throttle:api'])->group(function () {
        Route::post('orders/{order}/disputes', [DisputeController::class, 'store'])->whereNumber('order');
        Route::post('disputes/{dispute}/messages', [DisputeController::class, 'message'])->whereNumber('dispute');
    });
    Route::middleware(['auth:api', 'scope:orders:read,vendor:manage', 'throttle:api'])->group(function () {
        Route::get('disputes', [DisputeController::class, 'index']);
        Route::get('disputes/{dispute}', [DisputeController::class, 'show'])->whereNumber('dispute');
    });

    // Vendor fees + schedule (scope `vendor:manage`)
    Route::middleware(['auth:api', 'scopes:vendor:manage', 'throttle:api'])->group(function () {
        Route::get('fees', [FeeController::class, 'index']);
        Route::post('fees', [FeeController::class, 'store']);
        Route::get('fee-schedule', [FeeController::class, 'schedule']);
    });

    // Admin (scope `admin`)
    Route::middleware(['auth:api', 'scopes:admin', 'throttle:api'])->prefix('admin')->group(function () {
        Route::get('disputes', [DisputeController::class, 'adminIndex']);
        Route::post('disputes/{dispute}/resolve', [DisputeController::class, 'resolve'])->whereNumber('dispute');
        Route::get('fees', [FeeController::class, 'adminIndex']);
        Route::post('fees/{fee}/mark-paid', [FeeController::class, 'markPaid'])->whereNumber('fee');
        Route::post('fee-tiers', [FeeController::class, 'storeTier']);
        Route::delete('fee-tiers/{tier}', [FeeController::class, 'destroyTier'])->whereNumber('tier');
    });
});
