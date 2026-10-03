@extends('layouts.dashboard')

@section('title', __('seller.products.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerProducts', () => ({
    q: '', status: '', page: 1, rows: [], loading: true, error: '', notice: '',
    meta: { current_page: 1, last_page: 1, total: 0 },
    statuses: ['draft', 'active', 'archived'],
    LOW_STOCK: 5,
    pending: null, busy: false, actionError: '',
    tpl: @json(route('seller.products.edit', 0)),
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            const r = await api('vendor/products', { query: { q: this.q.trim(), status: this.status, page: this.page } });
            this.rows = r.data; this.meta = r.meta;
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },
    go(p) { this.page = p; this.load(); },
    search() { this.page = 1; this.load(); },
    link(p) { return sellerUi.url(this.tpl, p.id); },
    badge(s) { return sellerUi.badge('product', s); },
    ask(type, p) {
        this.trigger = document.activeElement; this.pending = { type, product: p }; this.actionError = ''; this.notice = '';
        this.$nextTick(() => { const b = this.$refs.confirmBox; if (b) { b.scrollIntoView({ block: 'center' }); b.focus(); } });
    },
    cancel() { this.pending = null; this.actionError = ''; this.trigger?.focus?.(); },
    async confirm() {
        if (!this.pending || this.busy) return;
        this.busy = true; this.actionError = '';
        const { type, product } = this.pending;
        try {
            if (type === 'archive') {
                await api('products/' + product.id, { method: 'PUT', body: { status: 'archived' } });
                this.notice = t('seller.products.archived');
            } else {
                await api('products/' + product.id, { method: 'DELETE' });
                this.notice = t('seller.products.deleted');
            }
            this.pending = null;
            await this.load();
        } catch (e) { this.actionError = sellerUi.err(e); } finally { this.busy = false; }
    },
})));
</script>

<div x-data="sellerProducts">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-medium">{{ __('seller.products.title') }}</h1>
        <a class="btn btn-primary" href="{{ route('seller.products.create') }}">{{ __('seller.products.add') }}</a>
    </div>

    <form class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end" @submit.prevent="search()">
        <div>
            <label for="p-q">{{ __('seller.products.search') }}</label>
            <input id="p-q" type="search" x-model="q" maxlength="255">
        </div>
        <div>
            <label for="p-status">{{ __('seller.products.status') }}</label>
            <select id="p-status" x-model="status" @change="search()">
                <option value="">{{ __('seller.products.all') }}</option>
                <template x-for="s in statuses" :key="s">
                    <option :value="s" x-text="t('seller.products.status_' + s)"></option>
                </template>
            </select>
        </div>
        <button type="submit" class="btn">{{ __('common.search') }}</button>
    </form>

    <div class="card sticky top-16 z-20 mb-4 bg-ok-soft text-ok lg:top-2" role="status" x-show="notice" x-cloak x-text="notice"></div>

    <div class="card mb-4" role="alertdialog" x-ref="confirmBox" tabindex="-1" x-show="pending" x-cloak @keydown.escape="cancel()">
        <p class="font-medium" x-text="pending ? t(pending.type === 'archive' ? 'seller.products.confirm_archive' : 'seller.products.confirm_delete', { title: pending.product.title }) : ''"></p>
        <p class="mt-1 text-sm text-bad" x-show="actionError" x-text="actionError" role="alert"></p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn btn-danger" :disabled="busy" @click="confirm()" x-text="pending && pending.type === 'archive' ? t('seller.products.archive') : t('seller.products.delete')"></button>
            <button type="button" class="btn" :disabled="busy" @click="cancel()">{{ __('common.cancel') }}</button>
        </div>
    </div>

    @include('seller.partials.state')

    <div x-show="!loading && !error && rows.length === 0" x-cloak class="card text-ink-2">{{ __('seller.products.empty') }}</div>

    <div x-show="rows.length" x-cloak class="card table-wrap">
        <table class="min-w-[640px]">
            <thead>
                <tr>
                    <th>{{ __('seller.products.col_title') }}</th>
                    <th>{{ __('seller.products.col_price') }}</th>
                    <th>{{ __('seller.products.col_stock') }}</th>
                    <th>{{ __('seller.products.col_status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="p in rows" :key="p.id">
                    <tr>
                        <td class="max-w-[16rem] truncate" x-text="p.title"></td>
                        <td x-text="money(p.price_cents, p.currency)"></td>
                        <td>
                            <span x-text="p.stock"></span>
                            <span class="badge badge-warn ms-1" x-show="p.stock <= LOW_STOCK" x-text="t('seller.products.low_stock')"></span>
                        </td>
                        <td><span :class="badge(p.status)" x-text="t('seller.products.status_' + p.status)"></span></td>
                        <td class="text-end">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a class="btn btn-sm" :href="link(p)" x-text="t('seller.products.edit')"></a>
                                <button type="button" class="btn btn-sm" x-show="p.status !== 'archived'" @click="ask('archive', p)" x-text="t('seller.products.archive')"></button>
                                <button type="button" class="btn btn-sm btn-danger" @click="ask('delete', p)" x-text="t('seller.products.delete')"></button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    @include('seller.partials.pager')
</div>
@endsection
