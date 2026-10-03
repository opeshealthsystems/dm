@extends('layouts.dashboard')

@section('title', __('seller.wallet.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerWallet', () => ({
    loading: true, error: '',
    wallets: [], fees: { bond_paid: false, data: [] }, schedule: { default_commission_bps: 0, tiers: [] },
    types: ['sale_credit', 'platform_fee', 'payout_debit', 'payout_reversal'],
    ledger: [], ledgerType: '', ledgerMeta: { current_page: 1, last_page: 1, total: 0 }, ledgerPage: 1, ledgerLoading: false, ledgerError: '',
    payouts: [], payMeta: { current_page: 1, last_page: 1, total: 0 }, payPage: 1, payLoading: false, payError: '',
    form: { currency: 'EUR', amount: '', method: 'bitcoin', address: '' }, errors: {}, formError: '', formOk: false, busy: false,
    bondBusy: false, bondError: '',
    methods: ['bitcoin', 'monero'],
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            const [w, fees, sched] = await Promise.all([api('wallet'), api('fees'), api('fee-schedule')]);
            this.wallets = Object.entries(w.data || {}).map(([currency, v]) => ({ currency, ...v }));
            this.fees = fees; this.schedule = sched;
            if (this.wallets.length) this.form.currency = this.wallets[0].currency;
            await Promise.all([this.loadLedger(), this.loadPayouts()]);
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },
    async loadLedger() {
        this.ledgerLoading = true; this.ledgerError = '';
        try {
            const r = await api('wallet/entries', { query: { type: this.ledgerType, page: this.ledgerPage } });
            this.ledger = r.data; this.ledgerMeta = r.meta;
        } catch (e) { this.ledgerError = sellerUi.err(e); } finally { this.ledgerLoading = false; }
    },
    async loadPayouts() {
        this.payLoading = true; this.payError = '';
        try {
            const r = await api('payouts', { query: { page: this.payPage } });
            this.payouts = r.data; this.payMeta = r.meta;
        } catch (e) { this.payError = sellerUi.err(e); } finally { this.payLoading = false; }
    },
    ledgerGo(p) { this.ledgerPage = p; this.loadLedger(); },
    payGo(p) { this.payPage = p; this.loadPayouts(); },
    filterLedger() { this.ledgerPage = 1; this.loadLedger(); },
    get available() { const w = this.wallets.find(x => x.currency === this.form.currency); return w ? w.available_cents : 0; },
    pct(bps) { return (bps / 100).toFixed(2).replace(/\.?0+$/, '') + '%'; },
    badge(s) { return sellerUi.badge('payout', s); },
    /* Same rules as StorePayoutRequest on the server. */
    validAddress(method, a) {
        if (method === 'bitcoin') return /^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/.test(a) || /^bc1[ac-hj-np-z02-9]{11,71}$/.test(a);
        if (method === 'monero') return /^[48][0-9AB][1-9A-HJ-NP-Za-km-z]{93}$/.test(a) || /^4[0-9AB][1-9A-HJ-NP-Za-km-z]{104}$/.test(a);
        return false;
    },
    async requestPayout() {
        this.formError = ''; this.formOk = false;
        const e = {}; const f = this.form;
        const cents = sellerUi.toCents(f.amount);
        const address = f.address.trim();
        if (cents === null || cents < 1 || cents > 100000000000) e.amount = t('seller.wallet.err_amount');
        else if (cents > this.available) e.amount = t('seller.wallet.err_balance');
        if (!/^[A-Za-z]{3}$/.test(f.currency)) e.currency = t('seller.wallet.err_currency');
        if (!this.methods.includes(f.method)) e.method = t('seller.wallet.err_method');
        if (!address || address.length > 120) e.address = t('seller.wallet.err_address_required');
        else if (!e.method && !this.validAddress(f.method, address)) e.address = t('seller.wallet.err_address', { method: t('seller.method.' + f.method) });
        this.errors = e;
        if (Object.keys(e).length || this.busy) return;
        this.busy = true;
        try {
            await api('payouts', { method: 'POST', body: { amount_cents: cents, currency: f.currency.toUpperCase(), method: f.method, destination_address: address } });
            this.formOk = true; this.form.amount = ''; this.form.address = '';
            const w = await api('wallet');
            this.wallets = Object.entries(w.data || {}).map(([currency, v]) => ({ currency, ...v }));
            this.payPage = 1; this.ledgerPage = 1;
            await Promise.all([this.loadPayouts(), this.loadLedger()]);
        } catch (err) {
            const fe = sellerUi.fieldErrors(err);
            this.errors = { amount: fe.amount_cents, currency: fe.currency, method: fe.method, address: fe.destination_address };
            this.formError = sellerUi.err(err);
        } finally { this.busy = false; }
    },
    get hasOpenBond() { return this.fees.data.some(x => x.status === 'unpaid'); },
    async createBond() {
        if (this.bondBusy) return;
        this.bondBusy = true; this.bondError = '';
        try { this.fees = await api('fees', { method: 'POST', body: {} }).then(() => api('fees')); }
        catch (e) { this.bondError = sellerUi.err(e); } finally { this.bondBusy = false; }
    },
})));
</script>

<div x-data="sellerWallet">
    <h1 class="mb-4 text-2xl font-medium">{{ __('seller.wallet.title') }}</h1>

    @include('seller.partials.state')

    <div x-show="!loading && !error" x-cloak class="flex flex-col gap-8">

        <section>
            <div class="grid gap-4 md:grid-cols-2" x-show="wallets.length">
                <template x-for="w in wallets" :key="w.currency">
                    <div class="card">
                        <h2 class="mb-3 font-medium" x-text="w.currency"></h2>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="stat"><div class="text-sm text-ink-3">{{ __('seller.wallet.available') }}</div><div class="text-xl font-medium" x-text="money(w.available_cents, w.currency)"></div></div>
                            <div class="stat"><div class="text-sm text-ink-3">{{ __('seller.wallet.escrow') }}</div><div class="text-xl font-medium" x-text="money(w.pending_escrow_cents, w.currency)"></div></div>
                            <div class="stat"><div class="text-sm text-ink-3">{{ __('seller.wallet.sales') }}</div><div class="font-medium" x-text="money(w.total_sales_cents, w.currency)"></div></div>
                            <div class="stat"><div class="text-sm text-ink-3" x-text="t('seller.wallet.commission', { rate: pct(w.commission_bps) })"></div><div class="font-medium" x-text="money(w.commission_cents, w.currency)"></div></div>
                            <div class="stat"><div class="text-sm text-ink-3">{{ __('seller.wallet.requested') }}</div><div class="font-medium" x-text="money(w.payouts_requested_cents, w.currency)"></div></div>
                            <div class="stat"><div class="text-sm text-ink-3">{{ __('seller.wallet.fees_paid') }}</div><div class="font-medium" x-text="money(w.fees_paid_cents, w.currency)"></div></div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="card text-ink-2" x-show="!wallets.length">{{ __('seller.wallet.no_funds') }}</div>
        </section>

        <section class="card">
            <h2 class="mb-1 text-lg font-medium">{{ __('seller.wallet.payout_title') }}</h2>
            <p class="mb-4 text-sm text-ink-2">{{ __('seller.wallet.payout_hint') }}</p>
            <form class="grid max-w-2xl gap-4 sm:grid-cols-2" @submit.prevent="requestPayout()" novalidate>
                <div>
                    <label for="po-amount">{{ __('seller.wallet.amount') }}</label>
                    <input id="po-amount" type="text" inputmode="decimal" x-model="form.amount" placeholder="0.00" :aria-invalid="!!errors.amount">
                    <p class="mt-1 text-sm text-ink-3" x-text="t('seller.wallet.max', { amount: money(available, form.currency) })"></p>
                    <p class="mt-1 text-sm text-bad" x-show="errors.amount" x-text="errors.amount"></p>
                </div>
                <div>
                    <label for="po-currency">{{ __('seller.wallet.currency') }}</label>
                    <input id="po-currency" type="text" x-model="form.currency" maxlength="3" list="po-currencies" autocomplete="off">
                    <datalist id="po-currencies"><template x-for="w in wallets" :key="w.currency"><option :value="w.currency"></option></template></datalist>
                    <p class="mt-1 text-sm text-bad" x-show="errors.currency" x-text="errors.currency"></p>
                </div>
                <div>
                    <label for="po-method">{{ __('seller.wallet.method') }}</label>
                    <select id="po-method" x-model="form.method">
                        <template x-for="m in methods" :key="m"><option :value="m" x-text="t('seller.method.' + m)"></option></template>
                    </select>
                    <p class="mt-1 text-sm text-bad" x-show="errors.method" x-text="errors.method"></p>
                </div>
                <div class="sm:col-span-2">
                    <label for="po-address">{{ __('seller.wallet.address') }}</label>
                    <input id="po-address" type="text" x-model="form.address" maxlength="120" autocomplete="off" spellcheck="false" :aria-invalid="!!errors.address">
                    <p class="mt-1 text-sm text-bad" x-show="errors.address" x-text="errors.address"></p>
                </div>
                <div class="sm:col-span-2">
                    <p class="mb-2 text-sm text-bad" role="alert" x-show="formError && !errors.amount && !errors.address" x-text="formError"></p>
                    <p class="mb-2 text-sm text-ok" role="status" x-show="formOk">{{ __('seller.wallet.requested_ok') }}</p>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="busy" x-text="busy ? t('seller.wallet.sending') : t('seller.wallet.request')"></button>
                </div>
            </form>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-medium">{{ __('seller.wallet.payouts_title') }}</h2>
            <div class="card mb-3 bg-bad-soft text-bad" role="alert" x-show="payError" x-text="payError"></div>
            <div class="card text-ink-2" x-show="!payLoading && !payError && !payouts.length">{{ __('seller.wallet.payouts_empty') }}</div>
            <div class="card table-wrap" x-show="payouts.length">
                <table class="min-w-[560px]">
                    <thead><tr>
                        <th>{{ __('seller.wallet.col_date') }}</th>
                        <th>{{ __('seller.wallet.col_amount') }}</th>
                        <th>{{ __('seller.wallet.method') }}</th>
                        <th>{{ __('seller.wallet.col_status') }}</th>
                        <th>{{ __('seller.wallet.col_note') }}</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="p in payouts" :key="p.id">
                            <tr>
                                <td x-text="fmtDate(p.created_at)"></td>
                                <td x-text="money(p.amount_cents, p.currency)"></td>
                                <td x-text="t('seller.method.' + p.method)"></td>
                                <td><span :class="badge(p.status)" x-text="t('seller.wallet.status_' + p.status)"></span></td>
                                <td class="max-w-[14rem] break-all text-sm text-ink-2" x-text="p.admin_note || p.txid || ''"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div x-show="payMeta.last_page > 1" x-cloak class="mt-4 flex items-center justify-between gap-2">
                <button type="button" class="btn" :disabled="payLoading || payMeta.current_page <= 1" @click="payGo(payMeta.current_page - 1)" x-text="t('seller.shared.prev')"></button>
                <span class="text-sm text-ink-2" x-text="t('seller.shared.page_of', { page: payMeta.current_page, total: payMeta.last_page })"></span>
                <button type="button" class="btn" :disabled="payLoading || payMeta.current_page >= payMeta.last_page" @click="payGo(payMeta.current_page + 1)" x-text="t('seller.shared.next')"></button>
            </div>
        </section>

        <section>
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-lg font-medium">{{ __('seller.wallet.ledger_title') }}</h2>
                <div class="w-full sm:w-56">
                    <label for="led-type">{{ __('seller.wallet.type') }}</label>
                    <select id="led-type" x-model="ledgerType" @change="filterLedger()">
                        <option value="">{{ __('seller.wallet.all_types') }}</option>
                        <template x-for="ty in types" :key="ty"><option :value="ty" x-text="t('seller.wallet.type_' + ty)"></option></template>
                    </select>
                </div>
            </div>
            <div class="card mb-3 bg-bad-soft text-bad" role="alert" x-show="ledgerError" x-text="ledgerError"></div>
            <div class="card text-ink-2" x-show="ledgerLoading" role="status">{{ __('common.loading') }}</div>
            <div class="card text-ink-2" x-show="!ledgerLoading && !ledgerError && !ledger.length">{{ __('seller.wallet.ledger_empty') }}</div>
            <div class="card table-wrap" x-show="ledger.length">
                <table class="min-w-[560px]">
                    <thead><tr>
                        <th>{{ __('seller.wallet.col_date') }}</th>
                        <th>{{ __('seller.wallet.type') }}</th>
                        <th>{{ __('seller.wallet.col_amount') }}</th>
                        <th>{{ __('seller.wallet.col_balance') }}</th>
                        <th>{{ __('seller.wallet.col_description') }}</th>
                    </tr></thead>
                    <tbody>
                        <template x-for="e in ledger" :key="e.id">
                            <tr>
                                <td x-text="fmtDate(e.created_at)"></td>
                                <td x-text="t('seller.wallet.type_' + e.type)"></td>
                                <td :class="e.amount_cents < 0 ? 'text-bad' : 'text-ok'" x-text="money(e.amount_cents, e.currency)"></td>
                                <td x-text="money(e.balance_after_cents, e.currency)"></td>
                                <td class="max-w-[14rem] truncate text-sm text-ink-2" x-text="e.description"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div x-show="ledgerMeta.last_page > 1" x-cloak class="mt-4 flex items-center justify-between gap-2">
                <button type="button" class="btn" :disabled="ledgerLoading || ledgerMeta.current_page <= 1" @click="ledgerGo(ledgerMeta.current_page - 1)" x-text="t('seller.shared.prev')"></button>
                <span class="text-sm text-ink-2" x-text="t('seller.shared.page_of', { page: ledgerMeta.current_page, total: ledgerMeta.last_page })"></span>
                <button type="button" class="btn" :disabled="ledgerLoading || ledgerMeta.current_page >= ledgerMeta.last_page" @click="ledgerGo(ledgerMeta.current_page + 1)" x-text="t('seller.shared.next')"></button>
            </div>
        </section>

        <section class="card">
            <h2 class="mb-3 text-lg font-medium">{{ __('seller.wallet.fees_title') }}</h2>
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <span class="badge" :class="fees.bond_paid ? 'badge-ok' : 'badge-warn'" x-text="fees.bond_paid ? t('seller.wallet.bond_paid') : t('seller.wallet.bond_unpaid')"></span>
                <button type="button" class="btn btn-sm" x-show="!fees.bond_paid && !hasOpenBond" :disabled="bondBusy" @click="createBond()" x-text="t('seller.wallet.bond_create')"></button>
                <span class="text-sm text-ink-2" x-show="hasOpenBond">{{ __('seller.wallet.bond_waiting') }}</span>
            </div>
            <p class="mb-2 text-sm text-bad" role="alert" x-show="bondError" x-text="bondError"></p>
            <p class="mb-2 text-sm text-ink-2" x-text="t('seller.wallet.default_rate', { rate: pct(schedule.default_commission_bps) })"></p>
            <ul class="flex flex-col gap-1 text-sm" x-show="schedule.tiers.length">
                <template x-for="tier in schedule.tiers" :key="tier.id">
                    <li x-text="t('seller.wallet.tier', { name: tier.name, from: money(tier.min_sales_cents), rate: pct(tier.commission_bps) })"></li>
                </template>
            </ul>
        </section>
    </div>
</div>
@endsection
