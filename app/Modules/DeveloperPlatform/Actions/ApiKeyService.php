<?php

namespace App\Modules\DeveloperPlatform\Actions;

use App\Models\User;
use App\Modules\DeveloperPlatform\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeyService
{
    /** @return array{0: ApiKey, 1: string} the model and the plaintext key (shown once). */
    public function create(User $user, string $name, array $scopes, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = 'dm_live_' . Str::random(40);
        $key = ApiKey::create([
            'user_id' => $user->id,
            'name' => $name,
            'prefix' => substr($plain, 0, 12),
            'key_hash' => ApiKey::hash($plain),
            'scopes' => array_values(array_unique($scopes)),
            'expires_at' => $expiresAt,
        ]);

        return [$key->refresh(), $plain];
    }

    public function revoke(ApiKey $key): ApiKey
    {
        if ($key->revoked_at === null) {
            $key->forceFill(['revoked_at' => now()])->save();
        }

        return $key;
    }

    public function findActiveByPlain(string $plain): ?ApiKey
    {
        $key = ApiKey::where('key_hash', ApiKey::hash($plain))->first();

        return $key && $key->isActive() ? $key : null;
    }
}
