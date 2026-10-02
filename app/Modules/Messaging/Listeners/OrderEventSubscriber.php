<?php

namespace App\Modules\Messaging\Listeners;

use App\Modules\Messaging\Actions\OrderNotifier;
use App\Modules\Orders\Events\OrderEvent;

/** Registered for every Orders domain event in AppServiceProvider. */
class OrderEventSubscriber
{
    public function __construct(private readonly OrderNotifier $notifier)
    {
    }

    public function handle(OrderEvent $event): void
    {
        $this->notifier->handle($event);
    }
}
