<?php

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        // The delivery address is only shown to the buyer and to the vendor who must ship it.
        $canSeeAddress = $user && ($user->id === $this->buyer_id || $user->id === $this->vendor_id);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'escrow_status' => $this->escrow_status,
            'shipment_status' => $this->shipment_status,
            'currency' => $this->currency,
            'subtotal_cents' => $this->subtotal_cents,
            'payment_method' => $this->payment_method,
            'tracking_number' => $this->tracking_number,
            'shipping_address' => $this->when($canSeeAddress, $this->shipping_address),
            'vendor' => $this->whenLoaded('vendor', fn () => ['id' => $this->vendor_id, 'handle' => $this->vendor->handle, 'shop_name' => $this->vendor->shop_name]),
            'buyer_id' => $this->buyer_id,
            'vendor_id' => $this->vendor_id,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'title' => $i->title,
                'unit_price_cents' => $i->unit_price_cents,
                'quantity' => $i->quantity,
                'line_total_cents' => $i->line_total_cents,
            ])),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
