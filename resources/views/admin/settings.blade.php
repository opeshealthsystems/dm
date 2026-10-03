@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.settings.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminSettings()" x-init="load()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('admin.settings.title') }}</h1>
    @include('admin._status')
    <form class="card grid max-w-xl gap-4" x-show="!loading" x-cloak @submit.prevent="save()">
        <div><label for="s1">{{ __('admin.settings.site_name') }}</label><input id="s1" x-model="v.site_name" required maxlength="100"></div>
        <div><label for="s2">{{ __('admin.settings.support_email') }}</label><input id="s2" type="email" x-model="v.support_email" maxlength="190"></div>
        <div><label for="s3">{{ __('admin.settings.default_currency') }}</label><input id="s3" x-model="v.default_currency" maxlength="3" minlength="3" required class="uppercase"></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="s4">{{ __('admin.settings.buyer_fee_bps') }}</label><input id="s4" type="number" min="0" max="10000" x-model.number="v.fee_rate_buyer_bps"></div>
            <div><label for="s5">{{ __('admin.settings.vendor_fee_bps') }}</label><input id="s5" type="number" min="0" max="10000" x-model.number="v.fee_rate_vendor_bps"></div>
        </div>
        <div><label for="s6">{{ __('admin.settings.maintenance') }}</label>
            <select id="s6" x-model="v.maintenance_mode"><option :value="false">{{ __('admin.settings.off') }}</option><option :value="true">{{ __('admin.settings.on') }}</option></select></div>
        <button class="btn btn-primary" type="submit" :disabled="saving">{{ __('admin.settings.save') }}</button>
    </form>
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminSettings', () => ({
        v: {}, loading: true, saving: false, error: '', notice: '',
        async load() {
            this.loading = true; this.error = '';
            try { this.v = (await api('admin/settings')).data; } catch (e) { this.error = errText(e); } finally { this.loading = false; }
        },
        async save() {
            this.saving = true; this.error = ''; this.notice = '';
            try { this.v = (await api('admin/settings', {method: 'PUT', body: {settings: this.v}})).data; this.notice = t('admin.settings.saved'); }
            catch (e) { this.error = errText(e); } finally { this.saving = false; }
        },
    }));
});
</script>
@endsection
