<?php

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Community\Events\ThreadReplied;
use App\Modules\Community\Exceptions\CommunityException;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Models\CommunityPost;
use App\Modules\Community\Models\CommunityReport;
use App\Modules\Community\Models\CommunitySubscription;
use App\Modules\Community\Models\CommunityThread;
use App\Modules\Community\Models\CommunityThreadRead;
use App\Modules\Community\Support\MarkdownLite;
use Illuminate\Support\Facades\DB;

/**
 * All forum state changes. Controllers stay thin; authorisation (who may do what) lives in the
 * policies, and the anti-abuse rules (cooldown, link limit) live here so every entry point shares them.
 */
class CommunityService
{
    // --- anti-abuse ------------------------------------------------------

    /** New accounts must wait before their first post; admins are exempt. */
    private function assertCooldown(User $user): void
    {
        $minutes = (int) config('community.cooldown_minutes');
        if (! $user->isAdmin() && $user->created_at && $user->created_at->gt(now()->subMinutes($minutes))) {
            throw new CommunityException('cooldown', 429, ['minutes' => $minutes]);
        }
    }

    /** Accounts under 24 hours old may include at most N links per post. */
    private function assertLinkLimit(User $user, string ...$texts): void
    {
        if ($user->isAdmin() || ! $user->created_at
            || $user->created_at->lte(now()->subHours((int) config('community.new_account_hours')))) {
            return;
        }
        $max = (int) config('community.new_account_max_links');
        if (MarkdownLite::countLinks(implode("\n", $texts)) > $max) {
            throw new CommunityException('too_many_links', 422, ['max' => $max]);
        }
    }

    // --- threads and posts -------------------------------------------------

    public function createThread(User $user, CommunityCategory $category, string $title, string $body, ?string $productSlug = null): CommunityThread
    {
        $this->assertCooldown($user);
        $this->assertLinkLimit($user, $title, $body);

        $product = null;
        if ($productSlug !== null && $productSlug !== '') {
            $product = Product::query()->active()->where('slug', $productSlug)->where('vendor_id', $user->id)->first();
            if (! $product) {
                throw new CommunityException('product_not_yours', 422);
            }
        }

        return DB::transaction(function () use ($user, $category, $title, $body, $product) {
            $thread = new CommunityThread(['category_id' => $category->id, 'user_id' => $user->id, 'product_id' => $product?->id, 'title' => trim($title)]);
            $thread->posts_count = 0;
            $thread->save();

            $post = $thread->posts()->create(['user_id' => $user->id, 'body' => trim($body)]);
            $this->syncThreadStats($thread);
            $this->subscribe($user, $thread);
            $this->markRead($user, $thread);

            return $thread->refresh();
        });
    }

    public function reply(User $user, CommunityThread $thread, string $body): CommunityPost
    {
        $this->assertCooldown($user);
        $this->assertLinkLimit($user, $body);

        $post = DB::transaction(function () use ($user, $thread, $body) {
            $locked = CommunityThread::query()->lockForUpdate()->findOrFail($thread->id);
            if ($locked->is_locked && ! $user->isAdmin()) {
                throw new CommunityException('thread_locked', 403);
            }

            $post = $locked->posts()->create(['user_id' => $user->id, 'body' => trim($body)]);
            $this->syncThreadStats($locked);
            $this->subscribe($user, $locked);
            $this->markRead($user, $locked);

            return $post;
        });

        ThreadReplied::dispatch($thread->refresh(), $post);

        return $post;
    }

    public function editPost(User $user, CommunityPost $post, string $body): CommunityPost
    {
        $this->assertLinkLimit($user, $body);
        $post->forceFill(['body' => trim($body), 'edited_at' => now()])->save();

        return $post;
    }

    /** Soft delete. Deleting the opening post removes the whole thread from view. */
    public function deletePost(CommunityPost $post): void
    {
        DB::transaction(function () use ($post) {
            $thread = CommunityThread::query()->lockForUpdate()->find($post->thread_id);
            $firstId = $thread?->posts()->min('id');
            $post->delete();
            if (! $thread) {
                return;
            }
            if ((int) $firstId === (int) $post->id) {
                $thread->delete();

                return;
            }
            $this->syncThreadStats($thread);
        });
    }

    public function deleteThread(CommunityThread $thread): void
    {
        $thread->delete();
    }

    /** Recompute counters from the visible posts. */
    public function syncThreadStats(CommunityThread $thread): void
    {
        $last = $thread->posts()->latest('id')->first(['id', 'created_at']);
        $thread->forceFill([
            'posts_count' => $thread->posts()->count(),
            'last_post_id' => $last?->id,
            'last_posted_at' => $last?->created_at ?? $thread->created_at,
        ])->save();
    }

    // --- moderation -----------------------------------------------------------

    public function setPinned(CommunityThread $thread, bool $on): CommunityThread
    {
        $thread->forceFill(['is_pinned' => $on])->save();

        return $thread;
    }

    public function setLocked(CommunityThread $thread, bool $on): CommunityThread
    {
        $thread->forceFill(['is_locked' => $on])->save();

        return $thread;
    }

    public function report(User $user, CommunityPost $post, string $reason, ?string $note): CommunityReport
    {
        if (CommunityReport::query()->where('post_id', $post->id)->where('user_id', $user->id)->exists()) {
            throw new CommunityException('already_reported', 409);
        }

        return CommunityReport::create(['post_id' => $post->id, 'user_id' => $user->id, 'reason' => $reason, 'note' => $note]);
    }

    /** @param 'dismiss'|'remove_post' $action */
    public function resolveReport(User $admin, CommunityReport $report, string $action): CommunityReport
    {
        return DB::transaction(function () use ($admin, $report, $action) {
            $report = CommunityReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($report->status !== CommunityReport::STATUS_OPEN) {
                throw new CommunityException('report_resolved', 409);
            }

            if ($action === 'remove_post') {
                $post = CommunityPost::withTrashed()->find($report->post_id);
                if ($post && ! $post->trashed()) {
                    $this->deletePost($post);
                }
                CommunityReport::query()->where('post_id', $report->post_id)->where('status', CommunityReport::STATUS_OPEN)
                    ->update(['status' => CommunityReport::STATUS_ACTIONED, 'resolved_by' => $admin->id, 'resolved_at' => now()]);
            } else {
                $report->forceFill(['status' => CommunityReport::STATUS_DISMISSED, 'resolved_by' => $admin->id, 'resolved_at' => now()])->save();
            }

            return $report->refresh();
        });
    }

    // --- subscriptions and unread markers ---------------------------------------

    public function subscribe(User $user, CommunityThread $thread): void
    {
        CommunitySubscription::query()->firstOrCreate(['thread_id' => $thread->id, 'user_id' => $user->id]);
    }

    public function unsubscribe(User $user, CommunityThread $thread): void
    {
        CommunitySubscription::query()->where('thread_id', $thread->id)->where('user_id', $user->id)->delete();
    }

    public function isSubscribed(User $user, CommunityThread $thread): bool
    {
        return CommunitySubscription::query()->where('thread_id', $thread->id)->where('user_id', $user->id)->exists();
    }

    /** Mark everything up to $postId (default: the latest post) as read. Never moves backwards. */
    public function markRead(User $user, CommunityThread $thread, ?int $postId = null): void
    {
        $upTo = $postId ?? (int) $thread->fresh()->last_post_id;
        $read = CommunityThreadRead::query()->firstOrCreate(['thread_id' => $thread->id, 'user_id' => $user->id], ['last_read_post_id' => 0]);
        if ($upTo > $read->last_read_post_id) {
            $read->forceFill(['last_read_post_id' => $upTo])->save();
        }
    }
}
