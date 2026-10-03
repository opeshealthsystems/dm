@extends('layouts.dashboard')

@section('title', __('seller.reviews.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerReviews', () => ({
    loading: true, error: '', rows: [], titles: {}, vendorId: null, page: 1, onlyOpen: false,
    meta: { current_page: 1, last_page: 1, total: 0 },
    drafts: {}, busy: {}, errors: {},
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            if (!this.vendorId) this.vendorId = (await api('auth/me')).data.id;
            const [r, prods] = await Promise.all([
                api('vendors/' + this.vendorId + '/reviews', { query: { page: this.page } }),
                api('vendor/products', { query: { per_page: 100 } }),
            ]);
            prods.data.forEach(p => { this.titles[p.id] = p.title; });
            this.rows = r.data; this.meta = r.meta;
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },
    go(p) { this.page = p; this.load(); },
    get shown() { return this.onlyOpen ? this.rows.filter(r => !r.vendor_reply) : this.rows; },
    title(r) { return this.titles[r.product_id] || t('seller.reviews.product_n', { id: r.product_id }); },
    stars(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); },
    async reply(r) {
        const text = (this.drafts[r.id] || '').trim();
        this.errors[r.id] = '';
        if (!text) { this.errors[r.id] = t('seller.reviews.err_required'); return; }
        if (text.length > 2000) { this.errors[r.id] = t('seller.reviews.err_long'); return; }
        if (this.busy[r.id]) return;
        this.busy[r.id] = true;
        try {
            const res = await api('reviews/' + r.id + '/reply', { method: 'POST', body: { reply: text } });
            const i = this.rows.findIndex(x => x.id === r.id);
            if (i > -1) this.rows[i] = res.data;
            this.drafts[r.id] = '';
        } catch (e) { this.errors[r.id] = sellerUi.err(e); } finally { this.busy[r.id] = false; }
    },
})));
</script>

<div x-data="sellerReviews">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-medium">{{ __('seller.reviews.title') }}</h1>
        <label class="mb-0 flex min-h-[44px] items-center gap-2 text-ink">
            <input type="checkbox" class="!min-h-0 !w-auto" x-model="onlyOpen">
            {{ __('seller.reviews.only_open') }}
        </label>
    </div>

    @include('seller.partials.state')

    <div x-show="!loading && !error && shown.length === 0" x-cloak class="card text-ink-2" x-text="onlyOpen ? t('seller.reviews.none_open') : t('seller.reviews.empty')"></div>

    <div class="flex flex-col gap-3">
        <template x-for="r in shown" :key="r.id">
            <article class="card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-medium" x-text="title(r)"></div>
                    <div class="text-sm text-ink-3" x-text="fmtDate(r.created_at)"></div>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <span class="text-accent" aria-hidden="true" x-text="stars(r.rating)"></span>
                    <span class="sr-only" x-text="t('seller.reviews.rating_of', { rating: r.rating })"></span>
                    <span class="text-sm text-ink-3" x-show="r.buyer" x-text="r.buyer ? t('seller.reviews.by', { handle: r.buyer.handle }) : ''"></span>
                </div>
                <p class="mt-2 whitespace-pre-line" x-show="r.body" x-text="r.body"></p>

                <div class="mt-3 rounded-lg bg-surface-2 p-3" x-show="r.vendor_reply">
                    <div class="text-sm text-ink-3" x-text="t('seller.reviews.your_reply', { date: fmtDate(r.vendor_replied_at) })"></div>
                    <p class="whitespace-pre-line" x-text="r.vendor_reply"></p>
                </div>

                <form class="mt-3" x-show="!r.vendor_reply" @submit.prevent="reply(r)" novalidate>
                    <label :for="'reply-' + r.id">{{ __('seller.reviews.reply_label') }}</label>
                    <textarea :id="'reply-' + r.id" x-model="drafts[r.id]" maxlength="2000"></textarea>
                    <p class="mt-1 text-sm text-bad" role="alert" x-show="errors[r.id]" x-text="errors[r.id]"></p>
                    <button type="submit" class="btn btn-primary mt-2" :disabled="!!busy[r.id]" x-text="busy[r.id] ? t('seller.reviews.sending') : t('seller.reviews.send')"></button>
                </form>
            </article>
        </template>
    </div>

    @include('seller.partials.pager')
</div>
@endsection
