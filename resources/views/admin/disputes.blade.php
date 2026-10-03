@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.disputes.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminDisputes()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('admin.disputes.title') }}</h1>
    <div class="mb-4 max-w-xs"><label for="ds">{{ __('admin.disputes.status') }}</label>
        <select id="ds" x-model="filters.status" @change="reset()">
            <option value="open">{{ __('admin.disputes.open') }}</option><option value="resolved">{{ __('admin.disputes.resolved') }}</option></select></div>
    @include('admin._status')

    <div class="grid gap-3" x-show="!loading" x-cloak>
        <template x-for="d in items" :key="d.id">
            <article class="card">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium" x-text="t('admin.disputes.order') + ' #' + d.order_id"></span>
                    <span class="badge" :class="d.status === 'open' ? 'badge-warn' : 'badge-ok'" x-text="d.status === 'open' ? t('admin.disputes.open') : t('admin.disputes.resolved')"></span>
                </div>
                <p class="mt-2 whitespace-pre-line text-ink-2" x-text="d.reason"></p>
                <p class="mt-2 text-sm text-ink-3" x-show="d.resolution"><span class="badge badge-accent me-1" x-show="d.outcome" x-text="d.outcome === 'buyer' ? t('admin.disputes.resolve_buyer') : t('admin.disputes.resolve_vendor')"></span><span x-text="d.resolution"></span></p>
                <div class="mt-3 flex flex-wrap gap-2" x-show="d.status === 'open'">
                    <button type="button" class="btn" @click="resolve(d, 'buyer')">{{ __('admin.disputes.resolve_buyer') }}</button>
                    <button type="button" class="btn" @click="resolve(d, 'vendor')">{{ __('admin.disputes.resolve_vendor') }}</button>
                </div>
            </article>
        </template>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('admin.disputes.empty') }}</p>
    @include('admin._pager')
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminDisputes', () => ({
        ...adminList('admin/disputes', {status: 'open'}),
        async resolve(d, outcome) {
            const resolution = await Alpine.store('dlg').ask({title: t(outcome === 'buyer' ? 'admin.disputes.resolve_buyer' : 'admin.disputes.resolve_vendor'), label: t('admin.disputes.note_prompt'), required: true});
            if (resolution) this.act('admin/disputes/' + d.id + '/resolve', {outcome, resolution});
        },
    }));
});
</script>
@endsection
