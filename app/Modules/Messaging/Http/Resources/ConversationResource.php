<?php

namespace App\Modules\Messaging\Http\Resources;

use App\Modules\Messaging\Actions\MessagingService;
use App\Modules\Messaging\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'subject' => $this->subject,
            'last_message_at' => $this->last_message_at,
            'unread_count' => app(MessagingService::class)->unreadCount($request->user(), $this->resource),
            // Read receipts: how far each participant has read.
            'participants' => $this->participants->map(fn ($p) => [
                'user_id' => $p->user_id,
                'last_read_message_id' => $p->last_read_message_id,
                'last_read_at' => $p->last_read_at,
            ])->values(),
        ];
    }
}
