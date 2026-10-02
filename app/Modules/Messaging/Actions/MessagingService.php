<?php

namespace App\Modules\Messaging\Actions;

use App\Models\User;
use App\Modules\Messaging\Exceptions\MessagingException;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Models\Participant;
use App\Modules\Messaging\Models\UserBlock;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

class MessagingService
{
    /** Get or create the single conversation attached to an order (buyer + vendor). Idempotent. */
    public function conversationForOrder(Order $order): Conversation
    {
        return DB::transaction(function () use ($order) {
            $conversation = Conversation::firstOrCreate(
                ['order_id' => $order->id],
                ['subject' => 'Order ' . $order->number],
            );
            foreach ([$order->buyer_id, $order->vendor_id] as $userId) {
                Participant::firstOrCreate(['conversation_id' => $conversation->id, 'user_id' => $userId]);
            }

            return $conversation;
        });
    }

    /** Get or create the general (non-order) conversation between two users. */
    public function generalConversation(User $a, User $b, ?string $subject = null): Conversation
    {
        $existing = Conversation::whereNull('order_id')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $b->id))
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($a, $b, $subject) {
            $conversation = Conversation::create(['subject' => $subject]);
            foreach ([$a->id, $b->id] as $id) {
                Participant::create(['conversation_id' => $conversation->id, 'user_id' => $id]);
            }

            return $conversation;
        });
    }

    /** Post a user message. Refused when either side blocked the other. */
    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        $others = $conversation->participants()->where('user_id', '!=', $sender->id)->pluck('user_id');
        foreach ($others as $otherId) {
            if (UserBlock::between($sender->id, (int) $otherId)) {
                throw new MessagingException('You cannot message this user.', 403);
            }
        }

        $message = $this->append($conversation, $sender->id, Message::TYPE_USER, $body);
        $this->markRead($conversation, $sender);

        return $message;
    }

    /** Post an automatic system message (not subject to blocking). */
    public function system(Conversation $conversation, string $body): Message
    {
        return $this->append($conversation, null, Message::TYPE_SYSTEM, $body);
    }

    private function append(Conversation $conversation, ?int $senderId, string $type, string $body): Message
    {
        return DB::transaction(function () use ($conversation, $senderId, $type, $body) {
            $message = $conversation->messages()->create(['sender_id' => $senderId, 'type' => $type, 'body' => $body]);
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });
    }

    /** Read receipt: mark everything up to now as read for this participant. */
    public function markRead(Conversation $conversation, User $user): void
    {
        $latest = (int) $conversation->messages()->max('id');
        Participant::where('conversation_id', $conversation->id)->where('user_id', $user->id)
            ->where('last_read_message_id', '<', $latest)
            ->update(['last_read_message_id' => $latest, 'last_read_at' => now()]);
    }

    public function unreadCount(User $user, ?Conversation $conversation = null): int
    {
        $q = Message::query()
            ->join('conversation_participants as p', function ($j) use ($user) {
                $j->on('p.conversation_id', '=', 'messages.conversation_id')->where('p.user_id', $user->id);
            })
            ->whereColumn('messages.id', '>', 'p.last_read_message_id')
            ->where(fn ($q) => $q->whereNull('messages.sender_id')->orWhere('messages.sender_id', '!=', $user->id));
        if ($conversation) {
            $q->where('messages.conversation_id', $conversation->id);
        }

        return $q->count();
    }

    public function block(User $blocker, User $target): void
    {
        if ($blocker->is($target)) {
            throw new MessagingException('You cannot block yourself.');
        }
        UserBlock::firstOrCreate(['blocker_id' => $blocker->id, 'blocked_id' => $target->id]);
    }

    public function unblock(User $blocker, User $target): void
    {
        UserBlock::where('blocker_id', $blocker->id)->where('blocked_id', $target->id)->delete();
    }
}
