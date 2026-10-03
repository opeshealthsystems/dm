<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;
use Symfony\Component\HttpFoundation\Response;

/**
 * `scope:a,b`: the token must hold ANY listed scope AND the user's role must be allowed to
 * use at least one of them. See RequireScopes for why the role check exists.
 */
class RequireAnyScope
{
    public function __construct(private readonly CheckTokenForAnyScope $token)
    {
    }

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        if ($user && count(array_intersect($scopes, $user->allowedScopes())) === 0) {
            return response()->json(['message' => 'Your account role cannot use this endpoint.'], 403);
        }

        return $this->token->handle($request, $next, ...$scopes);
    }
}
