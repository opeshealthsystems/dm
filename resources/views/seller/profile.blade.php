@extends('layouts.dashboard')

@section('title', __('seller.profile.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerProfile', () => ({
    loading: true, error: '', busy: false, saved: false, formError: '', errors: {},
    me: null,
    f: { name: '', shop_name: '', shop_description: '' },
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            this.me = (await api('auth/me')).data;
            this.f = { name: this.me.name || '', shop_name: this.me.shop_name || '', shop_description: this.me.shop_description || '' };
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },
    async save() {
        this.formError = ''; this.saved = false;
        const e = {}; const f = this.f;
        const name = f.name.trim(); const shop = f.shop_name.trim();
        if (!name) e.name = t('seller.profile.err_name'); else if (name.length > 255) e.name = t('seller.profile.err_name_long');
        if (!shop) e.shop_name = t('seller.profile.err_shop'); else if (shop.length > 255) e.shop_name = t('seller.profile.err_shop_long');
        if (f.shop_description.length > 5000) e.shop_description = t('seller.profile.err_description');
        this.errors = e;
        if (Object.keys(e).length || this.busy) return;
        this.busy = true;
        try {
            this.me = (await api('auth/me', { method: 'PUT', body: { name, shop_name: shop, shop_description: f.shop_description.trim() || null } })).data;
            this.saved = true;
        } catch (err) { this.errors = sellerUi.fieldErrors(err); this.formError = sellerUi.err(err); } finally { this.busy = false; }
    },
})));
</script>

<div x-data="sellerProfile">
    <h1 class="mb-4 text-2xl font-medium">{{ __('seller.profile.title') }}</h1>
    <p class="mb-4"><a class="btn btn-sm" href="{{ route('account.security') }}">{{ __('security.page.title') }}</a></p>

    @include('seller.partials.state')

    <form class="card flex max-w-2xl flex-col gap-4" x-show="!loading && !error" x-cloak @submit.prevent="save()" novalidate>
        <div>
            <label for="pr-email">{{ __('seller.profile.email') }}</label>
            <input id="pr-email" type="email" :value="me ? me.email : ''" readonly>
        </div>
        <div>
            <label for="pr-name">{{ __('seller.profile.name') }}</label>
            <input id="pr-name" type="text" x-model="f.name" maxlength="255" :aria-invalid="!!errors.name">
            <p class="mt-1 text-sm text-bad" x-show="errors.name" x-text="errors.name"></p>
        </div>
        <div>
            <label for="pr-shop">{{ __('seller.profile.shop_name') }}</label>
            <input id="pr-shop" type="text" x-model="f.shop_name" maxlength="255" :aria-invalid="!!errors.shop_name">
            <p class="mt-1 text-sm text-bad" x-show="errors.shop_name" x-text="errors.shop_name"></p>
        </div>
        <div>
            <label for="pr-desc">{{ __('seller.profile.shop_description') }}</label>
            <textarea id="pr-desc" x-model="f.shop_description" maxlength="5000" rows="6"></textarea>
            <p class="mt-1 text-sm text-bad" x-show="errors.shop_description" x-text="errors.shop_description"></p>
        </div>

        <p class="text-sm text-bad" role="alert" x-show="formError && !errors.name && !errors.shop_name && !errors.shop_description" x-text="formError"></p>
        <p class="text-sm text-ok" role="status" x-show="saved">{{ __('seller.profile.saved') }}</p>

        <div>
            <button type="submit" class="btn btn-primary" :disabled="busy" x-text="busy ? t('seller.profile.saving') : t('common.save')"></button>
        </div>
    </form>
</div>
@endsection
