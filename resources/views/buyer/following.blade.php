@extends('layouts.dashboard')

@section('title', __('buyer.following.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="buyerFollowing()" x-init="load()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('buyer.following.title') }}</h1>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('buyer.shared.retry') }}</button>
    </div>
    <p x-show="actionError" x-cloak class="card mb-4 border-bad text-bad" role="alert" x-text="actionError"></p>

    <p x-show="loaded && !loading && !error && items.length === 0" x-cloak class="empty">
        {{ __('buyer.following.empty') }} <a class="btn btn-primary" href="{{ route('home') }}">{{ __('buyer.orders.browse') }}</a>
    </p>

    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <template x-for="v in items" :key="v.id">
            <li class="card flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <a :href="'/v/' + v.id" class="font-medium" x-text="v.shop_name || v.handle"></a>
                    <div class="text-sm text-ink-2"
                         x-text="v.rating_count > 0 ? t('buyer.shared.rating', {avg: Number(v.rating_avg).toFixed(1), count: v.rating_count}) : t('buyer.shared.no_ratings')"></div>
                </div>
                <button type="button" class="btn btn-sm" :disabled="busyId === v.id" @click="unfollow(v)">{{ __('buyer.following.unfollow') }}</button>
            </li>
        </template>
    </ul>

    @include('partials.pager')
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('buyerFollowing', () => ({
        items: [], page: 1, lastPage: 1, loading: false, loaded: false, error: '', actionError: '', busyId: null,
        go(n) { this.page = n; this.load(); },
        async load() {
            this.loading = true; this.error = '';
            try {
                const r = await api('following', {query: {page: this.page, per_page: 24}});
                this.items = r.data; this.lastPage = r.meta?.last_page ?? 1;
                if (this.items.length === 0 && this.page > 1) { this.page = this.lastPage; return this.load(); }
            } catch (e) { this.error = errText(e); }
            finally { this.loading = false; this.loaded = true; }
        },
        async unfollow(v) {
            if (this.busyId) return;
            this.busyId = v.id; this.actionError = '';
            try {
                await api('vendors/' + v.id + '/follow', {method: 'DELETE'});
                await this.load();
            } catch (e) { this.actionError = errText(e); }
            finally { this.busyId = null; }
        },
    }));
});
</script>
@endsection
