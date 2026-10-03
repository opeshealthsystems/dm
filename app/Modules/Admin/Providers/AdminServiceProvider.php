<?php

namespace App\Modules\Admin\Providers;

use App\Models\User;
use App\Modules\Admin\Console\CreateAdminCommand;
use App\Modules\Admin\Policies\AdminPolicy;
use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, AdminPolicy::class);
        Gate::policy(Category::class, AdminPolicy::class);

        // Gate for resources without a dedicated policy: products moderation, orders, settings, stats, audit log.
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdminCommand::class]);
        }
    }
}
