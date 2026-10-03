@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.overview.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminOverview()" x-init="load()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('admin.overview.title') }}</h1>
    @include('admin._status')

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" x-show="!loading" x-cloak>
        <div class="stat"><div class="text-sm text-ink-2">{{ __('admin.overview.users') }}</div><div class="text-2xl font-medium" x-text="sum(stats.users_by_role)"></div></div>
        <div class="stat"><div class="text-sm text-ink-2">{{ __('admin.overview.orders') }}</div><div class="text-2xl font-medium" x-text="sum(stats.orders_by_status)"></div></div>
        <div class="stat"><div class="text-sm text-ink-2">{{ __('admin.overview.gmv') }}</div>
            <template x-for="(v, cur) in stats.gmv_by_currency" :key="cur"><div class="text-2xl font-medium" x-text="money(v, cur)"></div></template>
            <div class="text-2xl font-medium" x-show="Object.keys(stats.gmv_by_currency ?? {}).length === 0">0</div></div>
        <div class="stat"><div class="text-sm text-ink-2">{{ __('admin.overview.new_users') }}</div><div class="text-2xl font-medium" x-text="stats.new_users_7d ?? 0"></div></div>
    </div>

    <div class="grid gap-4 md:grid-cols-2" x-show="!loading" x-cloak>
        <section class="card">
            <h2 class="mb-2 font-medium">{{ __('admin.overview.attention') }}</h2>
            <div class="flex items-center justify-between gap-3 border-b border-line py-2">
                <span x-text="t('admin.overview.open_disputes', {count: disputes})"></span>
                <a class="btn btn-sm" href="{{ route('admin.disputes') }}">{{ __('admin.overview.review') }}</a>
            </div>
            <div class="flex items-center justify-between gap-3 border-b border-line py-2">
                <span x-text="t('admin.overview.pending_payouts', {count: payouts})"></span>
                <a class="btn btn-sm" href="{{ route('admin.payouts') }}">{{ __('admin.overview.review') }}</a>
            </div>
            <div class="flex items-center justify-between gap-3 py-2">
                <span>{{ __('admin.overview.unverified') }}</span>
                <a class="btn btn-sm" href="{{ route('admin.users') }}">{{ __('admin.overview.review') }}</a>
            </div>
        </section>
        <section class="card">
            <h2 class="mb-2 font-medium">{{ __('admin.overview.by_status') }}</h2>
            <template x-for="(n, s) in stats.orders_by_status" :key="s">
                <div class="flex justify-between border-b border-line py-1.5 last:border-0"><span x-text="t('common.status.' + s)"></span><span class="font-medium" x-text="n"></span></div>
            </template>
            <h2 class="mb-2 mt-4 font-medium">{{ __('admin.overview.by_role') }}</h2>
            <template x-for="(n, r) in stats.users_by_role" :key="r">
                <div class="flex justify-between border-b border-line py-1.5 last:border-0"><span x-text="t('admin.users.roles.' + r)"></span><span class="font-medium" x-text="n"></span></div>
            </template>
        </section>
    </div>
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminOverview', () => ({
        stats: {users_by_role: {}, orders_by_status: {}, gmv_by_currency: {}, new_users_7d: 0},
        disputes: 0, payouts: 0, loading: true, error: '', notice: '',
        sum: (o) => Object.values(o ?? {}).reduce((a, b) => a + b, 0),
        async load() {
            this.loading = true; this.error = '';
            try {
                const [s, d, p] = await Promise.all([
                    api('admin/stats'),
                    api('admin/disputes', {query: {status: 'open', per_page: 1}}),
                    api('admin/payouts', {query: {status: 'pending', per_page: 1}}),
                ]);
                this.stats = s.data; this.disputes = d.meta?.total ?? d.data.length; this.payouts = p.meta?.total ?? p.data.length;
            } catch (e) { this.error = errText(e); } finally { this.loading = false; }
        },
    }));
});
</script>
@endsection
