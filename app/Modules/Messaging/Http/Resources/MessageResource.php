<?php

namespace App\Modules\Messaging\Http\Resources;

use App\Modules\Messaging\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'type' => $this->type,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
