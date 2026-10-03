<?php

use App\Modules\Wallet\Http\Controllers\PayoutController;
use App\Modules\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/* Loaded by WalletServiceProvider under the `api` prefix and middleware group: /api/v1/... */

Route::prefix('v1')->group(function () {
    // Vendor wallet + payouts (scope `vendor:manage`)
    Route::middleware(['auth:api', 'scopes:vendor:manage', 'throttle:api'])->group(function () {
        Route::get('wallet', [WalletController::class, 'show']);
        Route::get('wallet/entries', [WalletController::class, 'entries']);
        Route::get('payouts', [PayoutController::class, 'index']);
        Route::post('payouts', [PayoutController::class, 'store'])->middleware('email.verified');
        Route::get('payouts/{payout}', [PayoutController::class, 'show'])->whereNumber('payout');
    });

    // Admin payout processing (scope `admin`)
    Route::middleware(['auth:api', 'scopes:admin', 'throttle:api'])->prefix('admin')->group(function () {
        Route::get('payouts', [PayoutController::class, 'adminIndex']);
        Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve'])->whereNumber('payout');
        Route::post('payouts/{payout}/reject', [PayoutController::class, 'reject'])->whereNumber('payout');
        Route::post('payouts/{payout}/mark-paid', [PayoutController::class, 'markPaid'])->whereNumber('payout');
    });
});
