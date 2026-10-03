<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: role:vendor, role:admin, role:buyer,vendor ... Sends others to their own area. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuspended()) {
            auth()->logout();

            return redirect()->route('login');
        }

        if (! in_array($user->role, $roles, true)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
