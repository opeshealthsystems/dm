<?php

namespace App\Modules\Community\Providers;

use App\Modules\Community\Events\ThreadReplied;
use App\Modules\Community\Models\CommunityPost;
use App\Modules\Community\Models\CommunityThread;
use App\Modules\Community\Policies\CommunityPostPolicy;
use App\Modules\Community\Policies\CommunityThreadPolicy;
use App\Modules\Messaging\Listeners\ThreadReplyNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class CommunityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(CommunityThread::class, CommunityThreadPolicy::class);
        Gate::policy(CommunityPost::class, CommunityPostPolicy::class);

        // Per-user posting limits (threads and replies share one bucket); IP fallback for safety.
        RateLimiter::for('community-posts', function (Request $request) {
            $key = 'community|' . ($request->user()?->getAuthIdentifier() ?: $request->ip());

            return [
                Limit::perMinute((int) config('community.rate_per_minute'))->by($key . '|m'),
                Limit::perHour((int) config('community.rate_per_hour'))->by($key . '|h'),
            ];
        });
        RateLimiter::for('community-reports', fn (Request $request) => Limit::perMinute((int) config('community.report_per_minute'))
            ->by('community-report|' . ($request->user()?->getAuthIdentifier() ?: $request->ip())));

        // Subscribers get a notification through the Messaging module when a thread is replied to.
        Event::listen(ThreadReplied::class, ThreadReplyNotifier::class);

        // <x-community::safe-html /> lives in resources/views/community/components.
        Blade::anonymousComponentPath(resource_path('views/community/components'), 'community');
    }
}
