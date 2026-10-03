<?php

use App\Modules\Identity\Http\Controllers\AccountWebController;
use Illuminate\Support\Facades\Route;

/*
| Account safety pages, loaded by IdentityServiceProvider.
*/
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [AccountWebController::class, 'forgot'])->name('password.request');
    Route::get('/reset-password/{token}', [AccountWebController::class, 'reset'])->name('password.reset');
    Route::get('/two-factor-challenge', [AccountWebController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [AccountWebController::class, 'submitChallenge'])->middleware('throttle:auth');
});

// Signed link from the verification e-mail; works logged in or out.
Route::get('/email/verify/{id}/{hash}', [AccountWebController::class, 'verifyEmail'])
    ->whereNumber('id')->middleware('signed')->name('verification.verify');

// Security settings for every role (buyer, seller, admin).
Route::get('/account/security', [AccountWebController::class, 'security'])
    ->middleware('auth')->name('account.security');
