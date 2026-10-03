<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // Web UI: pick the language, and give logged-in browsers a short-lived API cookie so
        // the dashboards call /api/v1 as the signed-in user (no token handling in JavaScript).
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SetLocale::class,
            \Laravel\Passport\Http\Middleware\CreateFreshApiToken::class,
        ]);
        // Reject suspended users on every OAuth-authenticated API request.
        $middleware->appendToGroup('api', \App\Modules\Admin\Http\Middleware\EnsureUserNotSuspended::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            // Passport's scope checks plus a role check (see RequireScopes for why).
            'scopes' => \App\Http\Middleware\RequireScopes::class,
            'scope' => \App\Http\Middleware\RequireAnyScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
