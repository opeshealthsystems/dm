<?php

namespace App\Modules\Messaging\Policies;

use App\Models\User;
use App\Modules\Messaging\Models\Conversation;

/** Only participants may read or write a conversation. Admins get no backdoor to private mail. */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
