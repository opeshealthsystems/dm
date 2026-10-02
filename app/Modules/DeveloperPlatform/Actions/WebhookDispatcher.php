<?php

namespace App\Modules\DeveloperPlatform\Actions;

use App\Modules\DeveloperPlatform\Jobs\DeliverWebhook;
use App\Modules\DeveloperPlatform\Models\WebhookDelivery;
use App\Modules\DeveloperPlatform\Models\WebhookEndpoint;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /** Queue a signed delivery to every active endpoint of the order's vendor subscribed to $event. */
    public function dispatchOrderEvent(string $event, Order $order): void
    {
        $endpoints = WebhookEndpoint::where('user_id', $order->vendor_id)->where('active', true)->get()
            ->filter(fn (WebhookEndpoint $e) => in_array($event, $e->events ?? [], true));
        if ($endpoints->isEmpty()) {
            return;
        }

        $eventId = (string) Str::uuid();
        $data = [
            'id' => $order->id, 'number' => $order->number, 'status' => $order->status,
            'escrow_status' => $order->escrow_status, 'shipment_status' => $order->shipment_status,
            'currency' => $order->currency, 'subtotal_cents' => $order->subtotal_cents,
            'buyer_id' => $order->buyer_id, 'vendor_id' => $order->vendor_id,
        ];

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_id' => $eventId,
                'event' => $event,
                'payload' => ['id' => $eventId, 'type' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data],
            ]);
            DeliverWebhook::dispatch($delivery->id);
        }
    }
}
