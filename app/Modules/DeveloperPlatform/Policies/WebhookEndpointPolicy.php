<?php

namespace App\Modules\DeveloperPlatform\Policies;

use App\Models\User;
use App\Modules\DeveloperPlatform\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function manage(User $user, WebhookEndpoint $endpoint): bool
    {
        return $endpoint->user_id === $user->id;
    }
}
