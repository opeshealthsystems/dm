<?php

namespace App\Modules\Wallet\Http\Resources;

use App\Modules\Wallet\Models\PayoutRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayoutRequest */
class PayoutRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'currency' => $this->currency,
            'amount_cents' => $this->amount_cents,
            'method' => $this->method,
            'destination_address' => $this->destination_address,
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'txid' => $this->txid,
            'created_at' => $this->created_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
        ];
    }
}
