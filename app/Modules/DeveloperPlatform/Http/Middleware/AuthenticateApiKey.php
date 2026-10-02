<?php

namespace App\Modules\DeveloperPlatform\Http\Middleware;

use App\Modules\DeveloperPlatform\Actions\ApiKeyService;
use App\Modules\DeveloperPlatform\Actions\UsageMeter;
use Closure;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticates `Authorization: Bearer dm_live_...` keys. Usage: `api.key:catalog:read`.
 * Keys may only ever hold catalog:read / orders:read; the route's scope must be among them.
 * Counts one request per key per day.
 */
class AuthenticateApiKey
{
    public function __construct(private readonly ApiKeyService $keys, private readonly UsageMeter $meter)
    {
    }

    public function handle(Request $request, Closure $next, string $scope)
    {
        $token = $request->bearerToken();
        if (! $token || ! str_starts_with($token, 'dm_live_')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $key = $this->keys->findActiveByPlain($token);
        $user = $key?->user;
        if (! $key || ! $user || $user->isSuspended()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (! $key->can($scope)) {
            return response()->json(['message' => 'API key lacks the required scope: ' . $scope], 403);
        }

        if (RateLimiter::tooManyAttempts($rl = "dm-key:{$key->id}", 120)) {
            return response()->json(["message" => "Too many requests."], 429, ["Retry-After" => RateLimiter::availableIn($rl)]);
        }
        RateLimiter::hit($rl, 60);

        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        $this->meter->record($key);

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
