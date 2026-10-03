<?php

namespace App\Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Community\Actions\CommunityQuery;
use App\Modules\Community\Actions\CommunityService;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Support\MarkdownLite;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Community pages. The index, category and new-thread pages are shells filled by Alpine from
 * /api/v1/community. The thread page is rendered on the server so that the sanitised post HTML
 * can be printed by the safe-html component (no x-html anywhere) and so search engines see the posts.
 */
class CommunityPageController extends Controller
{
    public function __construct(private readonly CommunityQuery $query, private readonly CommunityService $service)
    {
    }

    public function index(): View
    {
        return view('community.index', ['area' => 'buyer']);
    }

    public function category(string $category): View
    {
        $model = CommunityCategory::query()->where('slug', $category)->firstOrFail();

        return view('community.category', ['area' => 'buyer', 'category' => $model]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('community.new', [
            'area' => 'buyer',
            'preselect' => (string) $request->query('category', ''),
            'isAdmin' => $user->isAdmin(),
            'canLinkProduct' => $user->isVendor() || $user->isAdmin(),
        ]);
    }

    public function thread(Request $request, string $thread): View|RedirectResponse
    {
        [$id, $slug] = array_pad(explode('-', $thread, 2), 2, '');
        $model = $this->query->thread((int) $id);

        // One canonical URL per thread: /community/t/{id}-{slug}.
        if ($slug !== $model->slug) {
            return redirect()->to($model->path() . ($request->getQueryString() ? '?' . $request->getQueryString() : ''), 301);
        }

        $posts = $this->query->posts($model, (int) config('community.per_page'))->withQueryString();
        $posts->getCollection()->each->setRelation('thread', $model);

        $user = $request->user();
        $first = $model->posts()->orderBy('id')->first(['id', 'body']);
        $description = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags(MarkdownLite::render($first?->body)))), 155);
        if ($user) {
            $this->query->attachUnread([$model], $user);
        }

        return view('community.thread', [
            'area' => 'buyer',
            'thread' => $model,
            'posts' => $posts,
            'firstPostId' => (int) $first?->id,
            'description' => $description !== '' ? $description : __('community.seo_description'),
            'canonical' => url($model->path()) . ($posts->currentPage() > 1 ? '?page=' . $posts->currentPage() : ''),
            'signedIn' => $user !== null,
            'canReply' => $user ? Gate::forUser($user)->allows('reply', $model) : false,
            'subscribed' => $user ? $this->service->isSubscribed($user, $model) : false,
            'isAdmin' => (bool) $user?->isAdmin(),
        ]);
    }
}
