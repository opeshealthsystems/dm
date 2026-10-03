<?php

namespace App\Modules\Payments\Listeners;

use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Payments\Actions\PaymentService;
use App\Modules\Payments\Support\PaymentLogger;

class CreatePaymentForOrder
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function handle(OrderPlaced $event): void
    {
        try {
            $this->payments->createForOrder($event->order);
        } catch (\Throwable $e) {
            // Never break checkout; GET /orders/{order}/payment retries creation (idempotent).
            PaymentLogger::error('Payment creation failed', ['order_id' => $event->order->id, 'error' => $e->getMessage()]);
        }
    }
}
