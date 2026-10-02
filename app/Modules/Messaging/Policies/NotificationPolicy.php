<?php

namespace App\Modules\Messaging\Policies;

use App\Models\User;
use App\Modules\Messaging\Models\Notification;

class NotificationPolicy
{
    public function update(User $user, Notification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}
