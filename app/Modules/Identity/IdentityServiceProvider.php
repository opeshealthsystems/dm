<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Http\Middleware\ApplyRequestLocale;
use App\Modules\Identity\Http\Middleware\EnsureEmailVerified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Account safety: password reset, e-mail verification, two-factor authentication. */
class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('email.verified', EnsureEmailVerified::class);
        $router->aliasMiddleware('request.locale', ApplyRequestLocale::class);

        // Reset links: 5/min per IP and 5/hour per e-mail+IP.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('pwreset-ip|' . $request->ip()),
            Limit::perHour(5)->by('pwreset|' . strtolower((string) $request->input('email')) . '|' . $request->ip()),
        ]);
        // Verification mails: 3 per 10 minutes per user.
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinutes(10, 3)
            ->by('verify|' . ($request->user()?->getAuthIdentifier() ?: $request->ip())));
        // Two-factor management endpoints: 10/min per user (on top of the 5-failures account lockout).
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(10)
            ->by('2fa|' . ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        Route::middleware('api')->prefix('api/v1')->group(base_path('app/Modules/Identity/routes_api.php'));
        Route::middleware('web')->group(base_path('app/Modules/Identity/routes_web.php'));
    }
}
