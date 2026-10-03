@extends('layouts.dashboard')

@section('title', __('seller.form.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerProductForm', () => ({
    id: @json($productId),
    listUrl: @json(route('seller.products')),
    loading: true, error: '', busy: false, saved: false, formError: '', errors: {},
    categories: [],
    currencies: ['EUR', 'USD', 'GBP', 'CHF'],
    statuses: ['draft', 'active', 'archived'],
    f: { title: '', description: '', price: '', currency: 'EUR', stock: '0', category_id: '', status: 'draft' },
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            const cats = await api('categories');
            this.categories = cats.data;
            if (this.id) {
                const p = (await api('vendor/products/' + this.id)).data;
                this.f = {
                    title: p.title, description: p.description || '', price: sellerUi.fromCents(p.price_cents),
                    currency: p.currency, stock: String(p.stock), category_id: p.category_id ? String(p.category_id) : '', status: p.status,
                };
                if (!this.currencies.includes(p.currency)) this.currencies.push(p.currency);
            }
        } catch (e) { this.error = e && e.status === 404 ? t('seller.form.not_found') : sellerUi.err(e); } finally { this.loading = false; }
    },
    /* Mirrors ProductRequest on the server. Returns the API body, or null when invalid. */
    validate() {
        const e = {}; const f = this.f;
        const title = f.title.trim();
        if (!title) e.title = t('seller.form.err_title'); else if (title.length > 255) e.title = t('seller.form.err_title_long');
        if (f.description.length > 20000) e.description = t('seller.form.err_description');
        const cents = sellerUi.toCents(f.price);
        if (cents === null || cents < 1 || cents > 100000000) e.price = t('seller.form.err_price');
        const cur = f.currency.trim().toUpperCase();
        if (!/^[A-Z]{3}$/.test(cur)) e.currency = t('seller.form.err_currency');
        if (!/^\d{1,7}$/.test(String(f.stock).trim()) || parseInt(f.stock, 10) > 1000000) e.stock = t('seller.form.err_stock');
        if (!this.statuses.includes(f.status)) e.status = t('seller.form.err_status');
        this.errors = e;
        if (Object.keys(e).length) return null;
        return {
            title, description: f.description.trim() || null, price_cents: cents, currency: cur,
            stock: parseInt(f.stock, 10), category_id: f.category_id ? parseInt(f.category_id, 10) : null, status: f.status,
        };
    },
    async save() {
        this.formError = ''; this.saved = false;
        const body = this.validate();
        if (!body || this.busy) return;
        this.busy = true;
        try {
            if (this.id) {
                await api('products/' + this.id, { method: 'PUT', body });
                this.saved = true;
            } else {
                await api('products', { method: 'POST', body });
                window.location.href = this.listUrl;
            }
        } catch (e) {
            this.errors = sellerUi.fieldErrors(e);
            if (e && e.errors && e.errors.price_cents) this.errors.price = this.errors.price_cents;
            this.formError = sellerUi.err(e);
        } finally { this.busy = false; }
    },
})));
</script>

<div x-data="sellerProductForm">
    <a href="{{ route('seller.products') }}" class="back-link">{{ __('common.back') }}</a>
    <h1 class="mb-4 text-2xl font-medium" x-text="id ? t('seller.form.edit_heading') : t('seller.form.new_heading')"></h1>

    @include('seller.partials.state')

    <form class="card flex max-w-2xl flex-col gap-4" x-show="!loading && !error" x-cloak @submit.prevent="save()" novalidate>
        <div>
            <label for="f-title">{{ __('seller.form.name') }}</label>
            <input id="f-title" type="text" x-model="f.title" maxlength="255" :aria-invalid="!!errors.title">
            <p class="mt-1 text-sm text-bad" x-show="errors.title" x-text="errors.title"></p>
        </div>
        <div>
            <label for="f-desc">{{ __('seller.form.description') }}</label>
            <textarea id="f-desc" x-model="f.description" maxlength="20000" rows="6"></textarea>
            <p class="mt-1 text-sm text-bad" x-show="errors.description" x-text="errors.description"></p>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="f-price">{{ __('seller.form.price') }}</label>
                <input id="f-price" type="text" inputmode="decimal" x-model="f.price" placeholder="0.00" :aria-invalid="!!errors.price">
                <p class="mt-1 text-sm text-bad" x-show="errors.price" x-text="errors.price"></p>
            </div>
            <div>
                <label for="f-cur">{{ __('seller.form.currency') }}</label>
                <select id="f-cur" x-model="f.currency">
                    <template x-for="c in currencies" :key="c"><option :value="c" x-text="c"></option></template>
                </select>
                <p class="mt-1 text-sm text-bad" x-show="errors.currency" x-text="errors.currency"></p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="f-stock">{{ __('seller.form.stock') }}</label>
                <input id="f-stock" type="text" inputmode="numeric" x-model="f.stock" :aria-invalid="!!errors.stock">
                <p class="mt-1 text-sm text-bad" x-show="errors.stock" x-text="errors.stock"></p>
            </div>
            <div>
                <label for="f-cat">{{ __('seller.form.category') }}</label>
                <select id="f-cat" x-model="f.category_id">
                    <option value="">{{ __('seller.form.no_category') }}</option>
                    <template x-for="c in categories" :key="c.id"><option :value="String(c.id)" x-text="c.name"></option></template>
                </select>
                <p class="mt-1 text-sm text-bad" x-show="errors.category_id" x-text="errors.category_id"></p>
            </div>
            <div>
                <label for="f-status">{{ __('seller.form.status') }}</label>
                <select id="f-status" x-model="f.status">
                    <template x-for="s in statuses" :key="s"><option :value="s" x-text="t('seller.products.status_' + s)"></option></template>
                </select>
                <p class="mt-1 text-sm text-bad" x-show="errors.status" x-text="errors.status"></p>
            </div>
        </div>

        <p class="text-sm text-bad" role="alert" x-show="formError" x-text="formError"></p>
        <p class="text-sm text-ok" role="status" x-show="saved">{{ __('seller.form.saved') }}</p>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary" :disabled="busy" x-text="busy ? t('seller.form.saving') : t('common.save')"></button>
            <a class="btn" href="{{ route('seller.products') }}">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
