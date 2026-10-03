@extends('layouts.store')

@section('title', __('community.title') . ' | ' . config('app.name'))
@section('description', __('community.seo_description'))
@section('canonical_url', url('/community'))

@section('content')
@include('community._i18n')
<div x-data="communityIndex()">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="mb-1">{{ __('community.title') }}</h1>
            <p class="max-w-xl text-ink-2">{{ __('community.subtitle') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('community.new') }}">{{ __('community.category.new_thread') }}</a>
    </div>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('community.retry') }}</button>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="empty">{{ __('common.empty') }}</p>

    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <template x-for="c in items" :key="c.id">
            <li class="card card-hover relative flex flex-col gap-1">
                <a :href="'/community/' + encodeURIComponent(c.slug)" class="text-lg font-medium text-ink no-underline after:absolute after:inset-0 after:content-[''] hover:underline" x-text="cName(c)"></a>
                <p class="text-sm text-ink-2" x-text="cDesc(c)"></p>
                <p class="mt-1 text-sm text-ink-3" x-text="t('community.stats', {threads: c.threads_count ?? 0, posts: c.posts_count ?? 0})"></p>
            </li>
        </template>
    </ul>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('communityIndex', () => ({
        items: [], loading: true, error: '',
        init() { this.load(); },
        async load() {
            this.loading = true; this.error = '';
            try { this.items = (await api('community/categories')).data; }
            catch (e) { this.error = errText(e); }
            finally { this.loading = false; }
        },
    }));
});
</script>
@endsection
