@extends('layouts.store')

@section('title', __('community.new.seo_title') . ' | ' . __('community.title') . ' | ' . config('app.name'))
@section('description', __('community.seo_description'))
@section('robots', 'noindex,follow')
@section('canonical_url', url('/community/new'))

@section('content')
@include('community._i18n')
<div class="mx-auto max-w-2xl" x-data="communityNew(@js($preselect), @js($isAdmin), @js($canLinkProduct))">
    <a href="{{ route('community.index') }}" class="back-link">{{ __('community.all_categories') }}</a>
    <h1 class="mb-4">{{ __('community.new.title') }}</h1>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="loadError" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="loadError"></span>
        <button type="button" class="btn btn-sm ms-2" @click="init()">{{ __('community.retry') }}</button>
    </div>

    <form class="card grid gap-4" x-show="!loading && !loadError" x-cloak @submit.prevent="submit()">
        <div>
            <label for="nt-cat">{{ __('community.new.category') }}</label>
            <select id="nt-cat" x-model="category" required>
                <template x-for="c in categories" :key="c.id">
                    <option :value="c.slug" x-text="cName(c)" :selected="c.slug === category"></option>
                </template>
            </select>
        </div>
        <div>
            <label for="nt-title">{{ __('community.new.thread_title') }}</label>
            <input id="nt-title" x-model="title" maxlength="150" required autocomplete="off">
        </div>
        <div>
            <label for="nt-body">{{ __('community.new.body') }}</label>
            <textarea id="nt-body" x-model="body" rows="8" maxlength="10000" required></textarea>
            <p class="mt-1 text-sm text-ink-3">{{ __('community.thread.help', ['max' => config('community.new_account_max_links')]) }}</p>
        </div>
        <div x-show="canLinkProduct && products.length > 0" x-cloak>
            <label for="nt-product">{{ __('community.new.product') }}</label>
            <select id="nt-product" x-model="product">
                <option value="">{{ __('community.new.no_product') }}</option>
                <template x-for="p in products" :key="p.id">
                    <option :value="p.slug" x-text="p.title"></option>
                </template>
            </select>
        </div>
        <p class="text-sm text-bad" x-show="error" x-cloak role="alert" x-text="error"></p>
        <div>
            <button type="submit" class="btn btn-primary" :disabled="busy" x-text="busy ? t('community.thread.posting') : t('community.new.submit')"></button>
        </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('communityNew', (preselect, isAdmin, canLinkProduct) => ({
        categories: [], category: preselect, title: '', body: '', product: '', products: [],
        canLinkProduct, loading: true, loadError: '', busy: false, error: '',
        async init() {
            this.loading = true; this.loadError = '';
            try {
                const all = (await api('community/categories')).data;
                this.categories = all.filter(c => isAdmin || !c.staff_only);
                if (!this.categories.some(c => c.slug === this.category)) this.category = this.categories[0]?.slug ?? '';
            } catch (e) { this.loadError = errText(e); this.loading = false; return; }
            this.loading = false;
            if (this.canLinkProduct) {
                try {
                    const r = await api('vendor/products', {query: {per_page: 100}});
                    this.products = r.data.filter(p => p.status === 'active');
                } catch (e) { this.products = []; } // optional field: hide it when it cannot load
            }
        },
        async submit() {
            if (this.busy) return;
            this.busy = true; this.error = '';
            try {
                const r = await api('community/categories/' + encodeURIComponent(this.category) + '/threads', {
                    method: 'POST', body: {title: this.title, body: this.body, product: this.product || null},
                });
                window.location.href = r.data.path;
            } catch (e) { this.error = errText(e); this.busy = false; }
        },
    }));
});
</script>
@endsection
