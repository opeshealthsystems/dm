<?php

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Models\CommunityPost;
use App\Modules\Community\Models\CommunityThread;
use App\Modules\Community\Models\CommunityThreadRead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/** Read side of the forum, shared by the API controllers and the server-rendered thread page. */
class CommunityQuery
{
    /** @return Collection<int, CommunityCategory> with threads_count and posts_count */
    public function categories(): Collection
    {
        return CommunityCategory::query()->withCount('threads')->withSum('threads as posts_sum', 'posts_count')
            ->orderBy('position')->orderBy('id')->get();
    }

    public function threads(CommunityCategory $category, ?User $viewer, int $perPage): LengthAwarePaginator
    {
        $page = CommunityThread::query()->where('category_id', $category->id)
            ->with(['author:id,handle,role,is_verified_vendor', 'product:id,slug,title'])
            ->orderByDesc('is_pinned')->orderByDesc('last_posted_at')->orderByDesc('id')
            ->paginate($perPage);

        $this->attachUnread($page->getCollection(), $viewer);
        $page->getCollection()->each->setRelation('category', $category);

        return $page;
    }

    public function thread(int $id): CommunityThread
    {
        return CommunityThread::query()->with(['category', 'author:id,handle,role,is_verified_vendor', 'product:id,slug,title'])->findOrFail($id);
    }

    public function posts(CommunityThread $thread, int $perPage): LengthAwarePaginator
    {
        return $thread->posts()->with('author:id,handle,role,is_verified_vendor')->orderBy('id')->paginate($perPage);
    }

    public function post(int $id): CommunityPost
    {
        return CommunityPost::query()->with('author:id,handle,role,is_verified_vendor')->findOrFail($id);
    }

    /** Sets `is_unread` on each thread for the viewer (guests: always false). */
    public function attachUnread(iterable $threads, ?User $viewer): void
    {
        $threads = collect($threads);
        $reads = $viewer && $threads->isNotEmpty()
            ? CommunityThreadRead::query()->where('user_id', $viewer->id)->whereIn('thread_id', $threads->pluck('id'))->pluck('last_read_post_id', 'thread_id')
            : collect();

        foreach ($threads as $thread) {
            $thread->setAttribute('is_unread', $viewer !== null && (int) $thread->last_post_id > (int) ($reads[$thread->id] ?? 0));
        }
    }
}
