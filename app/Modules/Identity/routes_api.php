<?php

use App\Modules\Identity\Http\Controllers\EmailVerificationController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
| Account safety API, mounted at /api/v1 by IdentityServiceProvider.
*/

// Password reset (public, rate limited, never reveals whether an e-mail exists).
Route::middleware(['request.locale', 'throttle:password-reset'])->group(function () {
    Route::post('auth/forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset']);
});

Route::middleware(['auth:api', 'scopes:profile', 'request.locale'])->group(function () {
    Route::post('auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:verification');

    Route::middleware('throttle:two-factor')->prefix('auth/2fa')->group(function () {
        Route::get('/', [TwoFactorController::class, 'show']);
        Route::post('setup', [TwoFactorController::class, 'setup']);
        Route::post('confirm', [TwoFactorController::class, 'confirm']);
        Route::post('disable', [TwoFactorController::class, 'disable']);
        Route::post('recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
    });
});
