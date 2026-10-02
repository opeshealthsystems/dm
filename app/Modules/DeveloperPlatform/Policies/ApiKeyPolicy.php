<?php

namespace App\Modules\DeveloperPlatform\Policies;

use App\Models\User;
use App\Modules\DeveloperPlatform\Models\ApiKey;

class ApiKeyPolicy
{
    public function manage(User $user, ApiKey $key): bool
    {
        return $key->user_id === $user->id;
    }
}
