@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.payouts.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminPayouts()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('admin.payouts.title') }}</h1>
    <div class="mb-4 max-w-xs"><label for="ps">{{ __('admin.payouts.status') }}</label>
        <select id="ps" x-model="filters.status" @change="reset()"><option value="">{{ __('admin.shared.all') }}</option>
            @foreach (['pending', 'approved', 'paid', 'rejected'] as $s)<option value="{{ $s }}">{{ __("admin.payouts.statuses.$s") }}</option>@endforeach</select></div>
    @include('admin._status')

    <div class="grid gap-3" x-show="!loading" x-cloak>
        <template x-for="p in items" :key="p.id">
            <article class="card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-lg font-medium" x-text="money(p.amount_cents, p.currency)"></span>
                    <span class="badge" :class="{'badge-warn': p.status === 'pending', 'badge-accent': p.status === 'approved', 'badge-ok': p.status === 'paid', 'badge-bad': p.status === 'rejected'}" x-text="t('admin.payouts.statuses.' + p.status)"></span>
                </div>
                <div class="mt-1 text-sm text-ink-2"><span x-text="t('admin.payouts.method')"></span>: <span x-text="p.method.charAt(0).toUpperCase() + p.method.slice(1)"></span> · <span x-text="t('admin.payouts.vendor')"></span> #<span x-text="p.user_id"></span> · <span x-text="fmtDate(p.created_at)"></span></div>
                <div dir="ltr" class="mt-1 break-all text-sm text-ink-3 rtl:text-end" x-show="p.destination_address" x-text="p.destination_address"></div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary" x-show="p.status === 'pending'" @click="act('admin/payouts/' + p.id + '/approve', {})">{{ __('admin.payouts.approve') }}</button>
                    <button type="button" class="btn btn-sm btn-danger" x-show="p.status === 'pending'" @click="reject(p)">{{ __('admin.payouts.reject') }}</button>
                    <button type="button" class="btn btn-sm btn-primary" x-show="p.status === 'approved'" @click="paid(p)">{{ __('admin.payouts.mark_paid') }}</button>
                </div>
            </article>
        </template>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('admin.payouts.empty') }}</p>
    @include('admin._pager')
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminPayouts', () => ({
        ...adminList('admin/payouts', {status: ''}),
        async reject(p) {
            const note = await Alpine.store('dlg').ask({title: t('admin.payouts.reject'), label: t('admin.payouts.reject_prompt'), required: true});
            if (note) this.act('admin/payouts/' + p.id + '/reject', {note});
        },
        async paid(p) {
            const txid = await Alpine.store('dlg').ask({title: t('admin.payouts.mark_paid'), label: t('admin.payouts.txid_prompt'), required: true});
            if (txid) this.act('admin/payouts/' + p.id + '/mark-paid', {txid});
        },
    }));
});
</script>
@endsection
