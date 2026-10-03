<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware `email.verified`: unverified users get 403 `email_unverified` (e.g. on payouts). */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('security.verify.required'),
                'code' => 'email_unverified',
            ], 403);
        }

        return $next($request);
    }
}
