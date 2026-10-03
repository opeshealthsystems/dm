@extends('layouts.store')

@section('title', __('buyer.store.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="storeHome()">
    <h1 class="mb-1 text-2xl font-medium sm:text-3xl">{{ __('buyer.store.title') }}</h1>
    <p class="mb-5 max-w-xl text-ink-2">{{ __('buyer.store.subtitle') }}</p>

    <form class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_220px_auto]" @submit.prevent="search()">
        <div>
            <label for="q">{{ __('buyer.store.search_label') }}</label>
            <input id="q" type="search" x-model="q" maxlength="100" placeholder="{{ __('buyer.store.search_placeholder') }}">
        </div>
        <div>
            <label for="cat">{{ __('buyer.store.category') }}</label>
            <select id="cat" x-model="category" @change="search()">
                <option value="">{{ __('buyer.store.all_categories') }}</option>
                <template x-for="c in categories" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="loading">{{ __('common.search') }}</button>
        </div>
    </form>

    <p x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('buyer.shared.retry') }}</button>
    </p>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" x-show="!loading">
        <template x-for="p in items" :key="p.id">
            <article class="card card-hover relative flex flex-col gap-1">
                <a :href="'/p/' + encodeURIComponent(p.slug)" class="font-medium text-ink no-underline after:absolute after:inset-0 after:content-[''] hover:underline" x-text="p.title"></a>
                <a :href="'/v/' + p.vendor.id" class="link-tap relative z-10 w-fit text-sm text-ink-3" x-text="p.vendor.shop_name || p.vendor.handle"></a>
                <div class="text-sm text-ink-2" x-text="rating(p)"></div>
                <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                    <span class="font-medium" x-text="money(p.price_cents, p.currency)"></span>
                    <span class="badge" :class="p.stock > 0 ? 'badge-ok' : 'badge-bad'"
                          x-text="p.stock > 0 ? t('buyer.store.in_stock') : t('buyer.store.out_of_stock')"></span>
                </div>
            </article>
        </template>
    </div>

    <p x-show="loaded && !loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('buyer.store.empty') }}</p>

    @include('partials.pager')
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('storeHome', () => ({
        items: [], categories: [], q: '', category: '', page: 1, lastPage: 1,
        loading: false, loaded: false, error: '',
        init() {
            const p = new URLSearchParams(location.search);
            this.q = p.get('q') ?? '';
            this.category = p.get('category') ?? '';
            this.page = Math.max(1, parseInt(p.get('page') ?? '1', 10) || 1);
            api('categories').then(r => { this.categories = r.data; }).catch(() => {});
            this.load();
        },
        rating(p) {
            return p.rating_count > 0 ? t('buyer.shared.rating', {avg: p.rating_avg.toFixed(1), count: p.rating_count}) : t('buyer.shared.no_ratings');
        },
        search() { this.page = 1; this.load(); },
        go(n) { this.page = n; this.load(); window.scrollTo({top: 0}); },
        async load() {
            this.loading = true; this.error = '';
            const next = new URLSearchParams();
            if (this.q) next.set('q', this.q);
            if (this.category) next.set('category', this.category);
            if (this.page > 1) next.set('page', this.page);
            history.replaceState(null, '', location.pathname + (next.toString() ? '?' + next : ''));
            try {
                const r = await api('products', {query: {q: this.q, category_id: this.category, page: this.page, per_page: 12}});
                this.items = r.data;
                this.lastPage = r.meta?.last_page ?? 1;
            } catch (e) {
                this.error = errText(e);
            } finally {
                this.loading = false; this.loaded = true;
            }
        },
    }));
});
</script>
@endsection
