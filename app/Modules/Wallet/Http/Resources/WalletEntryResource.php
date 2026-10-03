<?php

namespace App\Modules\Wallet\Http\Resources;

use App\Modules\Wallet\Models\WalletEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletEntry */
class WalletEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'currency' => $this->currency,
            'amount_cents' => $this->amount_cents,
            'balance_after_cents' => $this->balance_after_cents,
            'order_id' => $this->order_id,
            'payout_request_id' => $this->payout_request_id,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
