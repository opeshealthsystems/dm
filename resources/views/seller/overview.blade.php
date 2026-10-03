@extends('layouts.dashboard')

@section('title', __('seller.overview.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerOverview', () => ({
    loading: true, error: '',
    me: null, sales: [], salesCapped: false, toShip: 0, ordersToShip: [], available: [],
    lowStock: [], unreplied: [],
    LOW_STOCK: 5,
    urls: { order: @json(route('seller.orders.show', 0)), product: @json(route('seller.products.edit', 0)) },
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            const me = (await api('auth/me')).data;
            const [paid, wallet, sales, products, reviews] = await Promise.all([
                api('orders', { query: { role: 'vendor', status: 'paid', per_page: 5 } }),
                api('wallet'),
                api('wallet/entries', { query: { type: 'sale_credit', date_from: sellerUi.ymd(30), per_page: 100 } }),
                api('vendor/products', { query: { status: 'active', per_page: 100 } }),
                api('vendors/' + me.id + '/reviews', { query: { per_page: 50 } }),
            ]);
            this.me = me;
            this.toShip = paid.meta.total;
            this.ordersToShip = paid.data;
            this.available = Object.entries(wallet.data || {}).map(([currency, w]) => ({ currency, cents: w.available_cents }));
            const totals = {};
            sales.data.forEach(e => { totals[e.currency] = (totals[e.currency] || 0) + e.amount_cents; });
            this.sales = Object.entries(totals).map(([currency, cents]) => ({ currency, cents }));
            this.salesCapped = sales.meta.last_page > 1;
            this.lowStock = products.data.filter(p => p.stock <= this.LOW_STOCK);
            this.unreplied = reviews.data.filter(r => !r.vendor_reply);
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },
    fmt(list) { return list.length ? list.map(x => money(x.cents, x.currency)).join(' / ') : money(0); },
    orderUrl(o) { return sellerUi.url(this.urls.order, o.id); },
    productUrl(p) { return sellerUi.url(this.urls.product, p.id); },
    get allClear() { return !this.toShip && !this.lowStock.length && !this.unreplied.length; },
})));
</script>

<div x-data="sellerOverview">
    <h1 class="mb-4 text-2xl font-medium">{{ __('seller.overview.title') }}</h1>

    @include('seller.partials.state')

    <div x-show="!loading && !error" x-cloak>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="stat">
                <div class="text-sm text-ink-3">{{ __('seller.overview.sales_30d') }}</div>
                <div class="text-xl font-medium" x-text="fmt(sales)"></div>
                <div class="text-xs text-ink-3" x-show="salesCapped" x-text="t('seller.overview.sales_capped')"></div>
            </div>
            <div class="stat">
                <div class="text-sm text-ink-3">{{ __('seller.overview.to_ship') }}</div>
                <div class="text-xl font-medium" x-text="toShip"></div>
            </div>
            <div class="stat">
                <div class="text-sm text-ink-3">{{ __('seller.overview.available') }}</div>
                <div class="text-xl font-medium" x-text="fmt(available)"></div>
            </div>
            <div class="stat">
                <div class="text-sm text-ink-3">{{ __('seller.overview.rating') }}</div>
                <div class="text-xl font-medium" x-text="me && me.rating_count ? me.rating_avg.toFixed(1) + ' / 5' : t('seller.overview.no_rating')"></div>
                <div class="text-xs text-ink-3" x-show="me && me.rating_count" x-text="t('seller.overview.rating_count', { count: me ? me.rating_count : 0 })"></div>
            </div>
        </div>

        <h2 class="mb-3 mt-8 text-lg font-medium">{{ __('seller.overview.next') }}</h2>
        <div x-show="allClear" class="card text-ink-2">{{ __('seller.overview.all_clear') }}</div>

        <div class="grid gap-4 lg:grid-cols-3" x-show="!allClear">
            <section class="card" x-show="toShip">
                <h3 class="mb-2 font-medium">{{ __('seller.overview.next_ship') }}</h3>
                <ul class="flex flex-col gap-2">
                    <template x-for="o in ordersToShip" :key="o.id">
                        <li class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate" x-text="'#' + o.number"></span>
                            <a class="btn btn-sm" :href="orderUrl(o)" x-text="t('seller.overview.open')"></a>
                        </li>
                    </template>
                </ul>
                <a class="mt-3 inline-block" href="{{ route('seller.orders') }}">{{ __('seller.overview.all_orders') }}</a>
            </section>

            <section class="card" x-show="lowStock.length">
                <h3 class="mb-2 font-medium">{{ __('seller.overview.next_stock') }}</h3>
                <ul class="flex flex-col gap-2">
                    <template x-for="p in lowStock" :key="p.id">
                        <li class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate" x-text="p.title"></span>
                            <span class="badge badge-warn" x-text="t('seller.overview.in_stock', { count: p.stock })"></span>
                            <a class="btn btn-sm" :href="productUrl(p)" x-text="t('seller.overview.restock')"></a>
                        </li>
                    </template>
                </ul>
            </section>

            <section class="card" x-show="unreplied.length">
                <h3 class="mb-2 font-medium">{{ __('seller.overview.next_reviews') }}</h3>
                <p class="mb-3 text-ink-2" x-text="t('seller.overview.reviews_waiting', { count: unreplied.length })"></p>
                <a class="btn btn-sm" href="{{ route('seller.reviews') }}">{{ __('seller.overview.reply') }}</a>
            </section>
        </div>
    </div>
</div>
@endsection
