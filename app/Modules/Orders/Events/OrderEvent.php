<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Order;

/**
 * Base domain event. Other modules (Payments, Escrow, Messaging, webhooks) listen to these
 * instead of being called directly by Orders.
 */
abstract class OrderEvent
{
    public function __construct(public readonly Order $order)
    {
    }
}
