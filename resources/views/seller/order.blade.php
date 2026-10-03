@extends('layouts.dashboard')

@section('title', __('seller.order.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerOrder', () => ({
    id: @json($orderId),
    o: null, dispute: null, loading: true, error: '',
    tracking: '', shipBusy: false, shipError: '', shipFields: {},
    evidence: '', evBusy: false, evError: '',
    messagesUrl: @json(route('seller.messages')),
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            const [order, disputes] = await Promise.all([
                api('orders/' + this.id),
                api('disputes', { query: { per_page: 100 } }),
            ]);
            this.o = order.data;
            const mine = disputes.data.filter(d => d.order_id === this.o.id);
            const open = mine.find(d => d.status === 'open') || mine[0] || null;
            this.dispute = open ? (await api('disputes/' + open.id)).data : null;
        } catch (e) { this.error = e && e.status === 404 ? t('seller.order.not_found') : sellerUi.err(e); } finally { this.loading = false; }
    },
    get paid() { return !!(this.o && this.o.paid_at); },
    get frozen() { return !!(this.o && (this.o.status === 'disputed' || (this.dispute && this.dispute.status === 'open'))); },
    get canShip() { return !!(this.o && this.o.status === 'paid' && !this.frozen); },
    badge(s) { return sellerUi.badge('order', s); },
    chat() { return this.messagesUrl + '?order=' + this.id; },
    async ship() {
        this.shipError = ''; this.shipFields = {};
        const v = this.tracking.trim();
        if (!v) { this.shipFields = { tracking_number: t('seller.order.tracking_required') }; return; }
        if (v.length > 255) { this.shipFields = { tracking_number: t('seller.order.tracking_too_long') }; return; }
        if (this.shipBusy) return;
        this.shipBusy = true;
        try {
            this.o = (await api('orders/' + this.id + '/ship', { method: 'POST', body: { tracking_number: v } })).data;
        } catch (e) { this.shipError = sellerUi.err(e); this.shipFields = sellerUi.fieldErrors(e); } finally { this.shipBusy = false; }
    },
    async sendEvidence() {
        this.evError = '';
        const v = this.evidence.trim();
        if (!v) { this.evError = t('seller.order.evidence_required'); return; }
        if (this.evBusy) return;
        this.evBusy = true;
        try {
            await api('disputes/' + this.dispute.id + '/messages', { method: 'POST', body: { body: v, kind: 'evidence' } });
            this.evidence = '';
            this.dispute = (await api('disputes/' + this.dispute.id)).data;
        } catch (e) { this.evError = sellerUi.err(e); } finally { this.evBusy = false; }
    },
})));
</script>

<div x-data="sellerOrder">
    <a href="{{ route('seller.orders') }}" class="back-link">{{ __('common.back') }}</a>

    @include('seller.partials.state')

    <div x-show="o" x-cloak>
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-medium" x-text="t('seller.order.heading', { number: o ? o.number : '' })"></h1>
            <span :class="o ? badge(o.status) : 'badge'" x-text="o ? t('common.status.' + o.status) : ''"></span>
        </div>

        <div x-show="dispute" class="card mb-4 bg-bad-soft text-bad" role="alert">
            <div class="font-medium" x-text="dispute && dispute.status === 'open' ? t('seller.order.dispute_open') : t('seller.order.dispute_closed')"></div>
            <p class="mt-1" x-show="dispute && dispute.status === 'open'">{{ __('seller.order.dispute_frozen') }}</p>
            <p class="mt-1" x-text="dispute ? dispute.reason : ''"></p>
            <p class="mt-1" x-show="dispute && dispute.resolution" x-text="dispute ? dispute.resolution : ''"></p>
            <ul class="mt-3 flex flex-col gap-2 text-ink" x-show="dispute && dispute.messages && dispute.messages.length">
                <template x-for="m in (dispute ? dispute.messages : [])" :key="m.id">
                    <li class="rounded-lg bg-surface p-2">
                        <div class="text-xs text-ink-3" x-text="fmtDate(m.created_at)"></div>
                        <div x-text="m.body"></div>
                    </li>
                </template>
            </ul>
            <form class="mt-3 text-ink" x-show="dispute && dispute.status === 'open'" @submit.prevent="sendEvidence()">
                <label for="evidence">{{ __('seller.order.evidence') }}</label>
                <textarea id="evidence" x-model="evidence" maxlength="5000"></textarea>
                <p class="mt-1 text-sm text-bad" x-show="evError" x-text="evError" role="alert"></p>
                <button type="submit" class="btn mt-2" :disabled="evBusy" x-text="t('seller.order.evidence_send')"></button>
            </form>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <section class="card">
                    <h2 class="mb-2 font-medium">{{ __('seller.order.items') }}</h2>
                    <div class="table-wrap">
                        <table class="min-w-[420px]">
                            <thead><tr>
                                <th>{{ __('seller.order.col_item') }}</th>
                                <th>{{ __('seller.order.col_qty') }}</th>
                                <th>{{ __('seller.order.col_price') }}</th>
                                <th>{{ __('seller.order.col_total') }}</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="i in (o ? o.items : [])" :key="i.product_id">
                                    <tr>
                                        <td x-text="i.title"></td>
                                        <td x-text="i.quantity"></td>
                                        <td x-text="money(i.unit_price_cents, o.currency)"></td>
                                        <td x-text="money(i.line_total_cents, o.currency)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-end font-medium" x-text="t('seller.order.subtotal', { amount: o ? money(o.subtotal_cents, o.currency) : '' })"></div>
                </section>

                <section class="card">
                    <h2 class="mb-2 font-medium">{{ __('seller.order.delivery') }}</h2>
                    <p x-show="paid" class="whitespace-pre-line" x-text="o ? o.shipping_address : ''"></p>
                    <p x-show="!paid" class="text-ink-2">{{ __('seller.order.address_hidden') }}</p>
                </section>
            </div>

            <div class="flex flex-col gap-4">
                <section class="card">
                    <h2 class="mb-2 font-medium">{{ __('seller.order.shipping') }}</h2>

                    <div x-show="o && o.tracking_number">
                        <div class="text-sm text-ink-3">{{ __('seller.order.tracking') }}</div>
                        <div class="break-all" x-text="o ? o.tracking_number : ''"></div>
                        <div class="mt-1 text-sm text-ink-3" x-text="o && o.shipped_at ? t('seller.order.shipped_on', { date: fmtDate(o.shipped_at) }) : ''"></div>
                    </div>

                    <form x-show="canShip" @submit.prevent="ship()" novalidate>
                        <label for="tracking">{{ __('seller.order.tracking') }}</label>
                        <input id="tracking" type="text" x-model="tracking" maxlength="255" autocomplete="off" :aria-invalid="!!shipFields.tracking_number">
                        <p class="mt-1 text-sm text-bad" x-show="shipFields.tracking_number" x-text="shipFields.tracking_number" role="alert"></p>
                        <p class="mt-2 text-sm text-bad" x-show="shipError && !shipFields.tracking_number" x-text="shipError" role="alert"></p>
                        <button type="submit" class="btn btn-primary mt-3 w-full" :disabled="shipBusy" x-text="shipBusy ? t('seller.order.saving') : t('seller.order.mark_shipped')"></button>
                    </form>

                    <p class="text-ink-2" x-show="o && o.status === 'pending_payment'">{{ __('seller.order.wait_payment') }}</p>
                    <p class="text-ink-2" x-show="o && o.status === 'paid' && frozen">{{ __('seller.order.cannot_ship_dispute') }}</p>
                </section>

                <section class="card">
                    <h2 class="mb-2 font-medium">{{ __('seller.order.details') }}</h2>
                    <dl class="grid grid-cols-2 gap-y-2 text-sm">
                        <dt class="text-ink-3">{{ __('seller.order.placed') }}</dt>
                        <dd x-text="o ? fmtDate(o.created_at) : ''"></dd>
                        <dt class="text-ink-3">{{ __('seller.order.paid_at') }}</dt>
                        <dd x-text="o && o.paid_at ? fmtDate(o.paid_at) : t('seller.order.not_yet')"></dd>
                        <dt class="text-ink-3">{{ __('seller.order.payment_method') }}</dt>
                        <dd x-text="o && o.payment_method ? t('seller.method.' + o.payment_method) : ''"></dd>
                        <dt class="text-ink-3">{{ __('seller.order.escrow') }}</dt>
                        <dd x-text="o ? t('seller.order.escrow_' + o.escrow_status) : ''"></dd>
                    </dl>
                    <a class="btn mt-3 w-full" :href="chat()">{{ __('seller.order.message_buyer') }}</a>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
