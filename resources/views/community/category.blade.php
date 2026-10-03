@extends('layouts.store')

@section('title', $category->displayName() . ' | ' . __('community.title') . ' | ' . config('app.name'))
@section('description', $category->displayDescription() ?: __('community.seo_category_description', ['name' => $category->displayName()]))
@section('canonical_url', url('/community/' . $category->slug))

@section('content')
@include('community._i18n')
<div x-data="communityCategory(@js($category->slug), @js(auth()->check()))">
    <a href="{{ route('community.index') }}" class="back-link">{{ __('community.all_categories') }}</a>

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="mb-1">{{ $category->displayName() }}</h1>
            <p class="max-w-xl text-ink-2">{{ $category->displayDescription() }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('community.new', ['category' => $category->slug]) }}">{{ __('community.category.new_thread') }}</a>
    </div>
    @if ($category->staff_only)
        <p class="mb-4 text-sm text-ink-3">{{ __('community.category.staff_only') }}</p>
    @endif

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('community.retry') }}</button>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="empty">{{ __('community.category.empty') }}</p>

    <ul class="flex flex-col gap-3">
        <template x-for="th in items" :key="th.id">
            <li class="card card-hover relative flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="th.path" class="font-medium text-ink no-underline after:absolute after:inset-0 after:content-[''] hover:underline" x-text="th.title"></a>
                    <span class="badge badge-accent" x-show="th.is_unread" x-cloak>{{ __('community.thread.new') }}</span>
                    <span class="badge badge-warn" x-show="th.is_pinned" x-cloak>{{ __('community.thread.pinned') }}</span>
                    <span class="badge" x-show="th.is_locked" x-cloak>{{ __('community.thread.locked') }}</span>
                </div>
                <p class="text-sm text-ink-3"
                   x-text="t('community.thread.by', {name: cHandle(th.author)}) + ' · ' + t('community.thread.replies', {count: th.replies_count}) + ' · ' + t('community.thread.last_activity', {date: fmtDate(th.last_posted_at)})"></p>
            </li>
        </template>
    </ul>
    @include('partials.pager', ['pg' => 'page', 'last' => 'lastPage', 'ld' => 'loading', 'goFn' => 'go'])
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('communityCategory', (slug, signedIn) => ({
        slug, signedIn, items: [], page: 1, lastPage: 1, loading: true, error: '',
        init() { this.load(); },
        async load() {
            this.loading = true; this.error = '';
            try {
                const r = await api('community/categories/' + encodeURIComponent(this.slug) + '/threads', {query: {page: this.page}});
                this.items = r.data; this.lastPage = r.meta?.last_page ?? 1;
            } catch (e) { this.error = e.status === 404 ? t('community.not_found') : errText(e); }
            finally { this.loading = false; }
        },
        go(n) { this.page = n; this.load(); window.scrollTo({top: 0}); },
    }));
});
</script>
@endsection
