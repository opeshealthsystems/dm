@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.orders.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminList('admin/orders', {number: '', status: '', escrow_status: ''})">
    <h1 class="mb-2 text-2xl font-medium">{{ __('admin.orders.title') }}</h1>
    <p class="mb-4 text-sm text-ink-3">{{ __('admin.orders.note') }}</p>
    <form class="mb-4 grid gap-3 sm:grid-cols-3" @submit.prevent="reset()">
        <div><label for="n">{{ __('admin.orders.number') }}</label><input id="n" x-model="filters.number" maxlength="30"></div>
        <div><label for="s">{{ __('common.status.label') }}</label>
            <select id="s" x-model="filters.status" @change="reset()"><option value="">{{ __('admin.shared.all') }}</option>
                @foreach (['pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'disputed'] as $s)<option value="{{ $s }}">{{ __("common.status.$s") }}</option>@endforeach</select></div>
        <div><label for="e">{{ __('admin.orders.escrow') }}</label>
            <select id="e" x-model="filters.escrow_status" @change="reset()"><option value="">{{ __('admin.shared.all') }}</option>
                @foreach (['pending', 'held', 'released', 'refunded'] as $s)<option value="{{ $s }}">{{ __("admin.orders.escrow_values.$s") }}</option>@endforeach</select></div>
    </form>
    @include('admin._status')

    <div class="table-wrap" x-show="!loading" x-cloak>
        <table>
            <thead><tr><th>{{ __('admin.orders.number') }}</th><th>{{ __('admin.orders.total') }}</th><th>{{ __('common.status.label') }}</th><th>{{ __('admin.orders.escrow') }}</th><th>{{ __('admin.orders.shipment') }}</th><th>{{ __('common.date') }}</th></tr></thead>
            <tbody>
            <template x-for="o in items" :key="o.id">
                <tr>
                    <td class="whitespace-nowrap font-medium" x-text="o.number"></td>
                    <td class="whitespace-nowrap" x-text="money(o.subtotal_cents, o.currency)"></td>
                    <td><span class="badge" :class="statusClass(o.status)" x-text="t('common.status.' + o.status)"></span></td>
                    <td><span class="badge" :class="{'badge-ok': o.escrow_status === 'released', 'badge-warn': o.escrow_status === 'held', 'badge-bad': o.escrow_status === 'refunded'}" x-text="t('admin.orders.escrow_values.' + o.escrow_status)"></span></td>
                    <td x-text="t('admin.orders.shipment_values.' + o.shipment_status)"></td>
                    <td class="whitespace-nowrap text-sm text-ink-2" x-text="fmtDate(o.created_at)"></td>
                </tr>
            </template>
            </tbody>
        </table>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('common.empty') }}</p>
    @include('admin._pager')
</div>
@endsection
