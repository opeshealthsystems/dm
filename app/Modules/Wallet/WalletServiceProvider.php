<?php

namespace App\Modules\Wallet;

use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Wallet\Listeners\CreditVendorOnOrderCompleted;
use App\Modules\Wallet\Models\PayoutRequest;
use App\Modules\Wallet\Policies\PayoutRequestPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Wires the Wallet module: ledger credit on OrderCompleted, policies, routes. */
class WalletServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderCompleted::class, CreditVendorOnOrderCompleted::class);
        Gate::policy(PayoutRequest::class, PayoutRequestPolicy::class);

        Route::middleware('api')->prefix('api')->group(__DIR__ . '/routes.php');
    }
}
