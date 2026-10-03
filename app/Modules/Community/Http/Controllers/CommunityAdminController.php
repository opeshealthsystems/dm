<?php

namespace App\Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Community\Actions\CommunityService;
use App\Modules\Community\Exceptions\CommunityException;
use App\Modules\Community\Http\Requests\CategoryRequest;
use App\Modules\Community\Http\Requests\ResolveReportRequest;
use App\Modules\Community\Http\Resources\CategoryResource;
use App\Modules\Community\Http\Resources\ReportResource;
use App\Modules\Community\Http\Resources\ThreadResource;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Models\CommunityReport;
use App\Modules\Community\Models\CommunityThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Community moderation. Every endpoint requires the `admin` scope and an admin account.
 *
 * @tags Community: Admin
 */
class CommunityAdminController extends Controller
{
    public function __construct(private readonly CommunityService $service)
    {
    }

    private function moderate(): void
    {
        $this->authorize('moderate', CommunityThread::class);
    }

    /** Pin a thread to the top of its category. */
    public function pin(CommunityThread $thread): ThreadResource
    {
        $this->moderate();

        return new ThreadResource($this->service->setPinned($thread, true));
    }

    /** Unpin a thread. */
    public function unpin(CommunityThread $thread): ThreadResource
    {
        $this->moderate();

        return new ThreadResource($this->service->setPinned($thread, false));
    }

    /** Lock a thread: only admins can reply, authors can no longer edit or delete. */
    public function lock(CommunityThread $thread): ThreadResource
    {
        $this->moderate();

        return new ThreadResource($this->service->setLocked($thread, true));
    }

    /** Unlock a thread. */
    public function unlock(CommunityThread $thread): ThreadResource
    {
        $this->moderate();

        return new ThreadResource($this->service->setLocked($thread, false));
    }

    /** Soft delete a whole thread. */
    public function destroyThread(CommunityThread $thread): Response
    {
        $this->moderate();
        $this->service->deleteThread($thread);

        return response()->noContent();
    }

    /** All categories with counts (admin view). */
    public function categories(): AnonymousResourceCollection
    {
        $this->moderate();

        return CategoryResource::collection(
            CommunityCategory::query()->withCount('threads')->withSum('threads as posts_sum', 'posts_count')->orderBy('position')->orderBy('id')->get()
        );
    }

    /** Create a category. `slug` defaults to the slugified name. A custom `name` has no translations. */
    public function storeCategory(CategoryRequest $request): JsonResponse
    {
        $this->moderate();
        $data = $request->validated();
        $data['slug'] ??= Str::slug($data['name']);
        if (CommunityCategory::query()->where('slug', $data['slug'])->exists() || $data['slug'] === '') {
            throw new CommunityException('slug_taken', 422);
        }

        return (new CategoryResource(CommunityCategory::create($data)))->response()->setStatusCode(201);
    }

    /** Update a category. Setting `name` replaces a translated default name. */
    public function updateCategory(CategoryRequest $request, CommunityCategory $category): CategoryResource
    {
        $this->moderate();
        $data = $request->validated();
        if (isset($data['name'])) {
            $data['name_key'] = null; // an admin-typed name overrides the built-in translation
        }
        if (array_key_exists('description', $data)) {
            $data['description_key'] = null;
        }
        $category->update($data);

        return new CategoryResource($category->refresh());
    }

    /** Delete a category. Refused (409) while it still has threads. */
    public function destroyCategory(CommunityCategory $category): Response
    {
        $this->moderate();
        if ($category->threads()->withTrashed()->exists()) {
            throw new CommunityException('category_not_empty', 409);
        }
        $category->delete();

        return response()->noContent();
    }

    /** The report queue, newest first. Filter with `status` = open (default), dismissed, actioned or all. */
    public function reports(Request $request): AnonymousResourceCollection
    {
        $this->moderate();
        $status = $request->query('status', 'open');

        return ReportResource::collection(
            CommunityReport::query()->with(['reporter:id,handle', 'post.thread', 'post.author:id,handle'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    /**
     * Resolve a report: `dismiss` closes it; `remove_post` soft-deletes the post and closes every
     * open report on it.
     */
    public function resolveReport(ResolveReportRequest $request, CommunityReport $report): ReportResource
    {
        $this->moderate();
        $report = $this->service->resolveReport($request->user(), $report, $request->string('action')->value());

        return new ReportResource($report->load(['reporter:id,handle', 'post.thread', 'post.author:id,handle']));
    }
}
