<?php

namespace App\Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Community\Actions\CommunityQuery;
use App\Modules\Community\Actions\CommunityService;
use App\Modules\Community\Http\Requests\ReportPostRequest;
use App\Modules\Community\Http\Requests\StorePostRequest;
use App\Modules\Community\Http\Requests\StoreThreadRequest;
use App\Modules\Community\Http\Resources\CategoryResource;
use App\Modules\Community\Http\Resources\PostResource;
use App\Modules\Community\Http\Resources\ThreadResource;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Models\CommunityPost;
use App\Modules\Community\Models\CommunityThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Community forum: public reads and signed-in writes.
 *
 * Reads are public. Writes need the `profile` scope (all roles have it) and a signed-in,
 * non-suspended buyer, vendor or admin. Post bodies are markdown-lite: `body` is the raw
 * text, `body_html` is the server-sanitised rendering (only strong, em, code, br and a).
 *
 * @tags Community
 */
class CommunityController extends Controller
{
    public function __construct(private readonly CommunityService $service, private readonly CommunityQuery $query)
    {
    }

    private function perPage(Request $request): int
    {
        return max(1, min($request->integer('per_page', (int) config('community.per_page')), 50));
    }

    /** List forum categories with thread and post counts (public). */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->query->categories());
    }

    /** Show one category by slug (public). */
    public function category(CommunityCategory $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('threads')->loadSum('threads as posts_sum', 'posts_count'));
    }

    /**
     * List the threads of a category (public). Pinned threads come first, then the most recently
     * active. When signed in, each thread carries `is_unread`.
     */
    public function threads(Request $request, CommunityCategory $category): AnonymousResourceCollection
    {
        return ThreadResource::collection($this->query->threads($category, $request->user('api'), $this->perPage($request)));
    }

    /** Show one thread (public). When signed in it also carries `is_subscribed` and `is_unread`. */
    public function thread(Request $request, CommunityThread $thread): ThreadResource
    {
        $thread->load(['category', 'author:id,handle,role,is_verified_vendor', 'product:id,slug,title']);
        $viewer = $request->user('api');
        $this->query->attachUnread([$thread], $viewer);
        $thread->setAttribute('is_subscribed', $viewer ? $this->service->isSubscribed($viewer, $thread) : false);

        return new ThreadResource($thread);
    }

    /** List the posts of a thread, oldest first (public). Deleted posts are not returned. */
    public function posts(Request $request, CommunityThread $thread): AnonymousResourceCollection
    {
        return PostResource::collection($this->query->posts($thread, $this->perPage($request)));
    }

    /** Show one post including its raw markdown (public); used to prefill the edit form. */
    public function post(CommunityPost $post): PostResource
    {
        return new PostResource($this->query->post($post->id));
    }

    /**
     * Start a thread. Requires `profile`.
     *
     * Rules: the account must be at least 5 minutes old; accounts under 24 hours old may use at
     * most 2 links; `staff_only` categories (announcements) accept admins only. `product` is the
     * public slug of one of your own active products. Rate limited per user.
     */
    public function storeThread(StoreThreadRequest $request, CommunityCategory $category): JsonResponse
    {
        $this->authorize('create', [CommunityThread::class, $category]);

        $thread = $this->service->createThread(
            $request->user(), $category, $request->string('title')->value(), $request->string('body')->value(),
            $request->input('product')
        );
        $thread->load(['category', 'author:id,handle,role,is_verified_vendor', 'product:id,slug,title']);

        return (new ThreadResource($thread))->response()->setStatusCode(201);
    }

    /**
     * Reply to a thread. Requires `profile`. Locked threads only accept admin replies.
     * Subscribers of the thread are notified. Rate limited per user.
     */
    public function storePost(StorePostRequest $request, CommunityThread $thread): JsonResponse
    {
        $this->authorize('reply', $thread);

        $post = $this->service->reply($request->user(), $thread, $request->string('body')->value());
        $post->load('author:id,handle,role,is_verified_vendor');
        $lastPage = (int) max(1, ceil($thread->fresh()->posts_count / $this->perPage($request)));

        return (new PostResource($post))->additional(['meta' => ['thread_path' => $thread->path(), 'last_page' => $lastPage]])
            ->response()->setStatusCode(201);
    }

    /** Edit your own post within 15 minutes of posting. Requires `profile`. */
    public function updatePost(StorePostRequest $request, CommunityPost $post): PostResource
    {
        $this->authorize('update', $post);
        $post = $this->service->editPost($request->user(), $post, $request->string('body')->value());

        return new PostResource($post->load('author:id,handle,role,is_verified_vendor'));
    }

    /** Soft delete a post: the author (thread open) or an admin. Deleting the opening post removes the thread. */
    public function destroyPost(CommunityPost $post): Response
    {
        $this->authorize('delete', $post);
        $this->service->deletePost($post);

        return response()->noContent();
    }

    /**
     * Report a post to the moderators. Requires `profile`. One report per user and post;
     * reasons: spam, abuse, illegal, off_topic, other.
     */
    public function report(ReportPostRequest $request, CommunityPost $post): JsonResponse
    {
        $this->authorize('report', $post);
        $report = $this->service->report($request->user(), $post, $request->string('reason')->value(), $request->input('note'));

        return response()->json(['data' => ['id' => $report->id, 'status' => $report->status]], 201);
    }

    /** Subscribe to a thread: replies create a notification for you. Requires `profile`. */
    public function subscribe(Request $request, CommunityThread $thread): JsonResponse
    {
        $this->authorize('interact', $thread);
        $this->service->subscribe($request->user(), $thread);

        return response()->json(['data' => ['is_subscribed' => true]]);
    }

    /** Unsubscribe from a thread. Requires `profile`. */
    public function unsubscribe(Request $request, CommunityThread $thread): JsonResponse
    {
        $this->authorize('interact', $thread);
        $this->service->unsubscribe($request->user(), $thread);

        return response()->json(['data' => ['is_subscribed' => false]]);
    }

    /** Mark a thread as read up to its latest post (clears the unread marker). Requires `profile`. */
    public function markRead(Request $request, CommunityThread $thread): Response
    {
        $this->authorize('interact', $thread);
        $this->service->markRead($request->user(), $thread);

        return response()->noContent();
    }
}
