@extends('layouts.dashboard')

@section('title', __('buyer.orders.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="buyerOrders()">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-2xl font-medium">{{ __('buyer.orders.title') }}</h1>
        <div class="w-full sm:w-56">
            <label for="status">{{ __('buyer.orders.filter') }}</label>
            <select id="status" x-model="status" @change="go(1)">
                <option value="">{{ __('buyer.orders.all') }}</option>
                @foreach (['pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'disputed'] as $s)
                    <option value="{{ $s }}">{{ __('common.status.' . $s) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('buyer.shared.retry') }}</button>
    </div>

    <p x-show="loaded && !loading && !error && items.length === 0" x-cloak class="empty">
        {{ __('buyer.orders.empty') }}
        <a class="btn btn-primary" href="{{ route('home') }}">{{ __('buyer.orders.browse') }}</a>
    </p>

    <ul class="flex flex-col gap-3" x-show="!loading">
        <template x-for="o in items" :key="o.id">
            <li>
                <a :href="'/account/orders/' + o.id" class="card grid grid-cols-1 gap-2 text-ink no-underline hover:bg-surface-2 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_auto] sm:items-center sm:gap-4">
                    <div class="min-w-0">
                        <div class="font-medium" x-text="o.number"></div>
                        <div class="truncate text-sm text-ink-3" x-text="o.vendor ? (o.vendor.shop_name || o.vendor.handle) : ''"></div>
                    </div>
                    <div class="truncate text-sm text-ink-2" x-text="summary(o)"></div>
                    <div class="font-medium" x-text="money(o.subtotal_cents, o.currency)"></div>
                    <div class="flex items-center justify-between gap-2 sm:justify-end">
                        <span class="badge" :class="statusClass(o.status)" x-text="t('common.status.' + o.status)"></span>
                        <span class="text-xs text-ink-3 sm:hidden" x-text="fmtDate(o.created_at)"></span>
                    </div>
                </a>
            </li>
        </template>
    </ul>

    @include('partials.pager')
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('buyerOrders', () => ({
        items: [], status: '', page: 1, lastPage: 1, loading: false, loaded: false, error: '',
        init() {
            const p = new URLSearchParams(location.search);
            this.status = p.get('status') ?? '';
            this.page = Math.max(1, parseInt(p.get('page') ?? '1', 10) || 1);
            this.load();
        },
        summary(o) {
            const n = (o.items ?? []).length;
            if (n === 0) return '';
            return n === 1 ? o.items[0].title : t('buyer.orders.more_items', {title: o.items[0].title, count: n - 1});
        },
        go(n) { this.page = n; this.load(); },
        async load() {
            this.loading = true; this.error = '';
            const next = new URLSearchParams();
            if (this.status) next.set('status', this.status);
            if (this.page > 1) next.set('page', this.page);
            history.replaceState(null, '', location.pathname + (next.toString() ? '?' + next : ''));
            try {
                const r = await api('orders', {query: {status: this.status, page: this.page, per_page: 15}});
                this.items = r.data; this.lastPage = r.meta?.last_page ?? 1;
            } catch (e) { this.error = errText(e); }
            finally { this.loading = false; this.loaded = true; }
        },
    }));
});
</script>
@endsection
