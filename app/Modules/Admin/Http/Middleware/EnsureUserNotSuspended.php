<?php

namespace App\Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects OAuth-authenticated requests from suspended users. Appended to the `api`
 * middleware group, so every auth:api route is covered. Suspending also revokes tokens.
 */
class EnsureUserNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        // API keys (dm_...) are not OAuth tokens; they have their own middleware.
        if ($bearer && ! str_starts_with($bearer, 'dm_')) {
            $user = Auth::guard('api')->user();
            if ($user && $user->isSuspended()) {
                return response()->json(['message' => 'This account is suspended.'], 403);
            }
        }

        return $next($request);
    }
}
