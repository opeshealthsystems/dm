<?php

namespace App\Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Payments\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->order_id,
            'method' => $this->method,
            'address' => $this->address,
            'expected_atomic' => $this->expected_atomic,
            'detected_atomic' => $this->detected_atomic,
            'confirmed_atomic' => $this->confirmed_atomic,
            'confirmations' => $this->confirmations,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
