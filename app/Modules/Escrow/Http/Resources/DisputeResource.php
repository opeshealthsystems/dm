<?php

namespace App\Modules\Escrow\Http\Resources;

use App\Modules\Escrow\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dispute */
class DisputeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'opened_by' => $this->opened_by,
            'against_user' => $this->against_user,
            'reason' => $this->reason,
            'status' => $this->status,
            'outcome' => $this->outcome,
            'resolution' => $this->resolution,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'kind' => $m->kind,
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ])),
        ];
    }
}
