<?php

namespace App\Modules\Admin\Http\Resources;

use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'buyer' => ['id' => $this->buyer_id, 'handle' => $this->whenLoaded('buyer', fn () => $this->buyer?->handle)],
            'vendor' => ['id' => $this->vendor_id, 'handle' => $this->whenLoaded('vendor', fn () => $this->vendor?->handle)],
            'currency' => $this->currency,
            'subtotal_cents' => $this->subtotal_cents,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'escrow_status' => $this->escrow_status,
            'shipment_status' => $this->shipment_status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'title' => $i->title,
                'unit_price_cents' => $i->unit_price_cents,
                'quantity' => $i->quantity,
                'line_total_cents' => $i->line_total_cents,
            ])),
        ];
    }
}
