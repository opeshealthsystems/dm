<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureOAuth();
        $this->configureRateLimits();
        $this->configureOpenApi();

        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
    }

    /** OAuth 2.0 scopes exposed to API clients. */
    private function configureOAuth(): void
    {
        Passport::tokensCan([
            'profile' => 'Read your own account profile',
            'catalog:read' => 'Read the product catalog',
            'catalog:write' => 'Create, update and delete your own products',
            'orders:read' => 'Read your own orders',
            'orders:write' => 'Place and manage your own orders',
            'vendor:manage' => 'Manage your vendor shop',
            'admin' => 'Administer the marketplace',
        ]);
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }

    private function configureRateLimits(): void
    {
        // Per token/user when authenticated, else per IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // Brute-force protection for register/login.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')) . '|' . $request->ip()),
        ]);
    }

    private function configureOpenApi(): void
    {
        Scramble::configure()
            ->routes(fn ($route) => str_starts_with($route->uri, 'api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->info->title = config('app.name') . ' API';
                $openApi->info->version = '1.0.0';
                $openApi->info->description = 'Multi-vendor marketplace API. Authenticate with OAuth 2.0 '
                    . '(authorization code + PKCE, client credentials) or a first-party token from `/api/v1/auth/login`.';

                $openApi->secure(SecurityScheme::oauth2()->flow('authorizationCode', function ($flow) {
                    $flow->authorizationUrl(url('/oauth/authorize'))
                        ->tokenUrl(url('/oauth/token'))
                        ->addScope('profile', 'Read your own account profile')
                        ->addScope('catalog:read', 'Read the product catalog')
                        ->addScope('catalog:write', 'Create, update and delete your own products')
                        ->addScope('orders:read', 'Read your own orders')
                        ->addScope('orders:write', 'Place and manage your own orders')
                        ->addScope('vendor:manage', 'Manage your vendor shop')
                        ->addScope('admin', 'Administer the marketplace');
                }));
            });
    }
}
