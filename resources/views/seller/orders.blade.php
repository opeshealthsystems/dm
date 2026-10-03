@extends('layouts.dashboard')

@section('title', __('seller.orders.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerOrders', () => ({
    status: '', page: 1, rows: [], loading: true, error: '',
    meta: { current_page: 1, last_page: 1, total: 0 },
    statuses: ['pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'disputed'],
    tpl: @json(route('seller.orders.show', 0)),
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            const r = await api('orders', { query: { role: 'vendor', status: this.status, page: this.page } });
            this.rows = r.data; this.meta = r.meta;
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },
    go(p) { this.page = p; this.load(); },
    filter() { this.page = 1; this.load(); },
    link(o) { return sellerUi.url(this.tpl, o.id); },
    summary(o) { return (o.items || []).map(i => i.quantity + ' x ' + i.title).join(', '); },
    badge(s) { return sellerUi.badge('order', s); },
})));
</script>

<div x-data="sellerOrders">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-medium">{{ __('seller.orders.title') }}</h1>
        <div class="w-full sm:w-56">
            <label for="order-status">{{ __('seller.orders.filter') }}</label>
            <select id="order-status" x-model="status" @change="filter()">
                <option value="">{{ __('seller.orders.all') }}</option>
                <template x-for="s in statuses" :key="s">
                    <option :value="s" x-text="t('common.status.' + s)"></option>
                </template>
            </select>
        </div>
    </div>

    @include('seller.partials.state')

    <div x-show="!loading && !error && rows.length === 0" x-cloak class="card text-ink-2">{{ __('seller.orders.empty') }}</div>

    <div x-show="rows.length" x-cloak class="card table-wrap">
        <table class="min-w-[640px]">
            <thead>
                <tr>
                    <th>{{ __('seller.orders.col_number') }}</th>
                    <th>{{ __('seller.orders.col_date') }}</th>
                    <th>{{ __('seller.orders.col_items') }}</th>
                    <th>{{ __('seller.orders.col_total') }}</th>
                    <th>{{ __('seller.orders.col_status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="o in rows" :key="o.id">
                    <tr>
                        <td x-text="'#' + o.number"></td>
                        <td x-text="fmtDate(o.created_at)"></td>
                        <td class="max-w-[16rem] truncate" x-text="summary(o)"></td>
                        <td x-text="money(o.subtotal_cents, o.currency)"></td>
                        <td><span :class="badge(o.status)" x-text="t('common.status.' + o.status)"></span></td>
                        <td class="text-end"><a class="btn btn-sm" :href="link(o)" x-text="t('seller.orders.view')"></a></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    @include('seller.partials.pager')
</div>
@endsection
