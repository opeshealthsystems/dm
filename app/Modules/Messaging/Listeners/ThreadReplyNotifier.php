<?php

namespace App\Modules\Messaging\Listeners;

use App\Modules\Community\Events\ThreadReplied;
use App\Modules\Community\Models\CommunitySubscription;
use App\Modules\Messaging\Models\Notification;
use Illuminate\Support\Str;

/**
 * Creates one in-app notification for every subscriber of a community thread, except the
 * person who just replied. Registered for ThreadReplied by CommunityServiceProvider.
 */
class ThreadReplyNotifier
{
    public function handle(ThreadReplied $event): void
    {
        $thread = $event->thread;
        $post = $event->post;
        $author = $post->author()->first(['id', 'handle']);

        CommunitySubscription::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', '!=', $post->user_id)
            ->pluck('user_id')
            ->each(fn (int $userId) => Notification::create([
                'user_id' => $userId,
                'type' => 'community.reply',
                'title' => Str::limit(__('community.notify.reply_title', ['title' => $thread->title]), 150),
                'body' => __('community.notify.reply_body', ['author' => $author?->handle ?? '']),
                'data' => ['thread_id' => $thread->id, 'post_id' => $post->id, 'path' => $thread->path()],
            ]));
    }
}
