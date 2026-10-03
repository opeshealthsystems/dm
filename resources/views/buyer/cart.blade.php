@extends('layouts.dashboard')

@section('title', __('buyer.cart.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="buyerCart()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('buyer.cart.title') }}</h1>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('buyer.shared.retry') }}</button>
    </div>
    <p x-show="actionError" x-cloak class="card mb-4 border-bad text-bad" role="alert" x-text="actionError"></p>

    {{-- Orders just created from a multi-vendor cart --}}
    <section x-show="placed.length > 1" x-cloak class="card mb-4">
        <h2 class="mb-2 text-lg font-medium">{{ __('buyer.cart.placed') }}</h2>
        <p class="mb-2 text-sm text-ink-2">{{ __('buyer.cart.placed_help') }}</p>
        <ul class="flex flex-col gap-2">
            <template x-for="o in placed" :key="o.id">
                <li class="flex flex-wrap items-center justify-between gap-2">
                    <span x-text="o.number + ' · ' + money(o.subtotal_cents, o.currency)"></span>
                    <a class="btn btn-primary btn-sm" :href="'/account/orders/' + o.id">{{ __('buyer.cart.pay_now') }}</a>
                </li>
            </template>
        </ul>
    </section>

    <p x-show="loaded && !loading && !error && lines.length === 0 && placed.length === 0" x-cloak class="empty">
        {{ __('buyer.cart.empty') }}
        <a class="btn btn-primary" href="{{ route('home') }}">{{ __('buyer.orders.browse') }}</a>
    </p>

    <div x-show="lines.length > 0" x-cloak class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
        <ul class="flex flex-col gap-3">
            <template x-for="l in lines" :key="l.product_id">
                <li class="card grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                    <div class="min-w-0">
                        <a :href="'/p/' + encodeURIComponent(l.slug)" class="font-medium" x-text="l.title"></a>
                        <div class="text-sm text-ink-2" x-text="money(l.unit_price_cents, l.currency)"></div>
                        <span class="badge badge-bad" x-show="!l.available">{{ __('buyer.cart.unavailable') }}</span>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-24">
                            <label :for="'q-' + l.product_id">{{ __('buyer.cart.quantity') }}</label>
                            <input :id="'q-' + l.product_id" type="number" min="1" max="1000" inputmode="numeric"
                                   :value="l.quantity" :disabled="busy" @change="setQty(l, $event.target.value)">
                        </div>
                        <div class="flex h-11 min-w-20 items-center font-medium" x-text="money(l.line_total_cents, l.currency)"></div>
                        <div class="flex h-11 items-center">
                            <button type="button" class="btn btn-sm btn-danger" :disabled="busy" @click="remove(l)">{{ __('buyer.cart.remove') }}</button>
                        </div>
                    </div>
                </li>
            </template>
        </ul>

        <form class="card flex flex-col gap-3 self-start" @submit.prevent="checkout()">
            <h2 class="text-lg font-medium">{{ __('buyer.cart.checkout') }}</h2>
            <div class="flex items-center justify-between">
                <span class="text-ink-2">{{ __('buyer.cart.total') }}</span>
                <span class="font-medium">
                    <template x-for="tt in totalList" :key="tt.cur"><span class="block text-end" x-text="money(tt.cents, tt.cur)"></span></template>
                </span>
            </div>
            <div>
                <label for="addr">{{ __('buyer.cart.address') }}</label>
                <textarea id="addr" x-model="address" minlength="10" maxlength="2000" required autocomplete="shipping street-address"
                          placeholder="{{ __('buyer.cart.address_placeholder') }}"></textarea>
                <p class="mt-1 text-xs text-ink-3">{{ __('buyer.cart.address_help') }}</p>
                <p class="mt-1 text-sm text-bad" x-show="fieldErrors.shipping_address" x-cloak x-text="fieldErrors.shipping_address?.[0]"></p>
            </div>
            <fieldset>
                <legend class="mb-1 text-sm text-ink-2">{{ __('buyer.cart.pay_with') }}</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label class="btn cursor-pointer !mb-0" :class="method === 'bitcoin' ? 'badge-accent' : ''">
                        <input type="radio" name="method" value="bitcoin" x-model="method" class="!min-h-0 !w-auto">
                        {{ __('buyer.shared.method_bitcoin') }}
                    </label>
                    <label class="btn cursor-pointer !mb-0" :class="method === 'monero' ? 'badge-accent' : ''">
                        <input type="radio" name="method" value="monero" x-model="method" class="!min-h-0 !w-auto">
                        {{ __('buyer.shared.method_monero') }}
                    </label>
                </div>
            </fieldset>
            <p class="text-sm text-warn" x-show="hasUnavailable" x-cloak>{{ __('buyer.cart.fix_unavailable') }}</p>
            <button type="submit" class="btn btn-primary" :disabled="busy || placing || hasUnavailable || address.trim().length < 10"
                    x-text="placing ? t('common.loading') : t('buyer.cart.place_order')"></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('buyerCart', () => ({
        lines: [], totals: {}, placed: [], address: '', method: 'bitcoin', fieldErrors: {},
        loading: false, loaded: false, busy: false, placing: false, error: '', actionError: '',
        get totalList() { return Object.entries(this.totals).map(([cur, cents]) => ({cur, cents})); },
        get hasUnavailable() { return this.lines.some(l => !l.available); },
        init() { this.load(); },
        apply(r) { this.lines = r.data; this.totals = r.totals ?? {}; },
        async load() {
            this.loading = true; this.error = '';
            try { this.apply(await api('cart')); }
            catch (e) { this.error = errText(e); }
            finally { this.loading = false; this.loaded = true; }
        },
        async mutate(fn) {
            if (this.busy) return;
            this.busy = true; this.actionError = '';
            try { await fn(); }
            catch (e) { this.actionError = errText(e); await this.load(); }
            finally { this.busy = false; }
        },
        setQty(l, value) {
            const q = Math.max(1, Math.min(1000, parseInt(value, 10) || 1));
            return this.mutate(async () => { this.apply(await api('cart/items/' + l.product_id, {method: 'PATCH', body: {quantity: q}})); });
        },
        remove(l) {
            return this.mutate(async () => { this.apply(await api('cart/items/' + l.product_id, {method: 'DELETE'})); });
        },
        async checkout() {
            if (this.placing) return;
            this.placing = true; this.actionError = ''; this.fieldErrors = {};
            try {
                const r = await api('orders', {method: 'POST', body: {shipping_address: this.address.trim(), payment_method: this.method}});
                this.placed = r.data; this.lines = []; this.totals = {}; this.address = '';
                if (r.data.length === 1) { window.location.href = '/account/orders/' + r.data[0].id; return; }
            } catch (e) {
                this.fieldErrors = e.errors ?? {};
                this.actionError = errText(e);
                if (e.status === 409 || e.status === 422) await this.load();
            } finally { this.placing = false; }
        },
    }));
});
</script>
@endsection
