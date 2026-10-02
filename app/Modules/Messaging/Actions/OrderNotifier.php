<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Models\Notification;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderEvent;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderRefunded;
use App\Modules\Orders\Events\OrderShipped;

/** Turns Orders domain events into notifications and an automatic system message for both parties. */
class OrderNotifier
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    /** @return array{type:string, buyer:string, vendor:string, system:string}|null */
    private function copy(OrderEvent $event, string $n): ?array
    {
        return match (true) {
            $event instanceof OrderPlaced => ['type' => 'order.placed',
                'buyer' => "Order {$n} was placed and is awaiting payment.",
                'vendor' => "New order {$n} received; awaiting buyer payment.",
                'system' => "Order {$n} was placed and is awaiting payment."],
            $event instanceof OrderPaid => ['type' => 'order.paid',
                'buyer' => "Payment for order {$n} was received and is held in escrow.",
                'vendor' => "Order {$n} is paid. You can ship it now.",
                'system' => "Payment for order {$n} was received and is held in escrow."],
            $event instanceof OrderShipped => ['type' => 'order.shipped',
                'buyer' => "Order {$n} has been shipped.",
                'vendor' => "You marked order {$n} as shipped.",
                'system' => "Order {$n} has been shipped."],
            $event instanceof OrderCompleted => ['type' => 'order.completed',
                'buyer' => "Order {$n} is completed.",
                'vendor' => "Order {$n} was confirmed by the buyer; funds are being released.",
                'system' => "Order {$n} is completed."],
            $event instanceof OrderCancelled, $event instanceof OrderRefunded => ['type' => 'order.cancelled',
                'buyer' => "Order {$n} was cancelled.",
                'vendor' => "Order {$n} was cancelled.",
                'system' => "Order {$n} was cancelled."],
            default => null,
        };
    }

    public function handle(OrderEvent $event): void
    {
        $order = $event->order;
        $copy = $this->copy($event, $order->number);
        if (! $copy) {
            return;
        }

        foreach (['buyer' => $order->buyer_id, 'vendor' => $order->vendor_id] as $role => $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => $copy['type'],
                'title' => $copy[$role],
                'order_id' => $order->id,
                'data' => ['order_number' => $order->number, 'status' => $order->status],
            ]);
        }

        $this->messaging->system($this->messaging->conversationForOrder($order), $copy['system']);
    }
}
