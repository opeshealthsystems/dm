<?php

namespace App\Modules\DeveloperPlatform\Listeners;

use App\Modules\DeveloperPlatform\Actions\WebhookDispatcher;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderEvent;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderRefunded;
use App\Modules\Orders\Events\OrderShipped;

class DispatchOrderWebhooks
{
    private const MAP = [
        OrderPlaced::class => 'order.placed', OrderPaid::class => 'order.paid', OrderShipped::class => 'order.shipped',
        OrderCompleted::class => 'order.completed', OrderCancelled::class => 'order.cancelled', OrderRefunded::class => 'order.refunded',
    ];

    public function __construct(private readonly WebhookDispatcher $dispatcher)
    {
    }

    public function handle(OrderEvent $event): void
    {
        if ($name = self::MAP[$event::class] ?? null) {
            $this->dispatcher->dispatchOrderEvent($name, $event->order);
        }
    }
}
