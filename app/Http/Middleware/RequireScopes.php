<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Middleware\CheckToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * `scopes:a,b`: the token must hold ALL listed scopes AND the user's role must be allowed
 * to use them (User::allowedScopes()). The role check matters because a signed-in browser
 * session gets a first-party cookie token that carries every scope; scopes alone would let
 * a buyer in the web UI call seller-only endpoints.
 */
class RequireScopes
{
    public function __construct(private readonly CheckToken $token)
    {
    }

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        if ($user && count(array_diff($scopes, $user->allowedScopes())) > 0) {
            return response()->json(['message' => 'Your account role cannot use this endpoint.'], 403);
        }

        return $this->token->handle($request, $next, ...$scopes);
    }
}
