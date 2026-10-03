<?php

namespace App\Modules\Wallet\Listeners;

use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Wallet\Actions\CreditVendorForOrder;

/** Synchronous on purpose: money must move in the same request that released the escrow. */
class CreditVendorOnOrderCompleted
{
    public function __construct(private readonly CreditVendorForOrder $credit)
    {
    }

    public function handle(OrderCompleted $event): void
    {
        $this->credit->handle($event->order);
    }
}
