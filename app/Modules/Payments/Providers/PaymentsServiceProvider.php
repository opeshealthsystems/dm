<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Payments\Actions\PaymentService;
use App\Modules\Payments\Console\PollPayments;
use App\Modules\Payments\Gateways\BitcoinGateway;
use App\Modules\Payments\Listeners\CreatePaymentForOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    /** Container tag every PaymentGateway implementation is registered under. */
    public const GATEWAY_TAG = 'payments.gateways';

    public function register(): void
    {
        $this->mergeConfigFrom(config_path('payments.php'), 'payments');

        // To add a gateway (e.g. Monero) add one line:
        //   $this->app->tag([\App\Modules\Payments\Gateways\MoneroGateway::class], self::GATEWAY_TAG);
        $this->app->tag([BitcoinGateway::class, \App\Modules\Payments\Gateways\MoneroGateway::class], self::GATEWAY_TAG);

        $this->app->bind(PaymentService::class, fn ($app) => new PaymentService(
            $app->tagged(self::GATEWAY_TAG),
            $app->make(OrderLifecycle::class),
        ));
    }

    public function boot(): void
    {
        Event::listen(OrderPlaced::class, CreatePaymentForOrder::class);

        if ($this->app->runningInConsole()) {
            $this->commands([PollPayments::class]);
        }
    }
}
