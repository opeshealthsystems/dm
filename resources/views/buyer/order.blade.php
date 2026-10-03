@extends('layouts.dashboard')

@section('title', __('buyer.order.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="buyerOrder(@js($orderId))" x-on:beforeunload.window="stop()">
    <a href="{{ route('buyer.orders') }}" class="back-link">{{ __('buyer.order.back') }}</a>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="init()">{{ __('buyer.shared.retry') }}</button>
    </div>

    <template x-if="order">
        <div class="flex flex-col gap-4">
            <header class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <h1 class="text-2xl font-medium" x-text="t('buyer.order.heading', {number: order.number})"></h1>
                    <p class="text-sm text-ink-3" x-text="fmtDate(order.created_at)"></p>
                </div>
                <span class="badge" :class="statusClass(order.status)" x-text="t('common.status.' + order.status)"></span>
            </header>

            <p x-show="notice" x-cloak class="card border-ok text-ok" role="status" x-text="notice"></p>
            <p x-show="actionError" x-cloak class="card border-bad text-bad" role="alert" x-text="actionError"></p>

            {{-- Progress tracker --}}
            <section class="card" aria-label="{{ __('buyer.order.progress') }}">
                <ol class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <template x-for="(s, i) in steps" :key="s.key">
                        <li class="flex items-center gap-2">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-sm"
                                  :class="i <= stepIndex ? 'border-accent bg-accent text-on-accent' : 'border-line text-ink-3'"
                                  x-text="i < stepIndex || order.status === 'completed' ? '✓' : i + 1"></span>
                            <span class="text-sm" :class="i === stepIndex ? 'font-medium text-ink' : 'text-ink-2'" x-text="s.label"></span>
                        </li>
                    </template>
                </ol>
                <p x-show="order.status === 'cancelled'" x-cloak class="mt-3 text-sm text-ink-2">{{ __('buyer.order.cancelled_note') }}</p>
                <p x-show="order.status === 'disputed'" x-cloak class="mt-3 text-sm text-bad">{{ __('buyer.order.disputed_note') }}</p>
            </section>

            {{-- Payment instructions --}}
            <section class="card" x-show="order.status === 'pending_payment'" x-cloak aria-labelledby="pay-h">
                <h2 id="pay-h" class="mb-2 text-lg font-medium">{{ __('buyer.order.payment') }}</h2>
                <p x-show="payLoading && !payment" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
                <div x-show="payUnavailable && !payment && !payLoading" x-cloak class="flex flex-wrap items-center gap-3 rounded-lg bg-surface-2 p-3 text-ink-2" role="status">
                    <span class="min-w-0 flex-1 basis-48 wrap-anywhere">{{ __('buyer.order.pay_unavailable') }}</span>
                    <button type="button" class="btn btn-sm" @click="loadPayment()">{{ __('buyer.shared.retry') }}</button>
                </div>
                <div x-show="payError" x-cloak class="flex flex-wrap items-center gap-3">
                    <p class="min-w-0 flex-1 basis-48 text-bad" role="alert" x-text="payError"></p>
                    <button type="button" class="btn btn-sm" @click="loadPayment()">{{ __('buyer.shared.retry') }}</button>
                </div>
                <template x-if="payment">
                    <div class="flex flex-col gap-3">
                        <p class="text-ink-2" x-text="t('buyer.order.pay_intro', {method: t('buyer.shared.method_' + payment.method)})"></p>
                        <div>
                            <div class="text-sm text-ink-3">{{ __('buyer.order.amount') }}</div>
                            <div class="break-all text-xl font-medium" x-text="fmtCrypto(payment.expected_atomic, payment.method) + ' ' + unit(payment.method)"></div>
                            <div class="text-sm text-ink-3" x-text="'≈ ' + money(order.subtotal_cents, order.currency)"></div>
                        </div>
                        <div>
                            <div class="text-sm text-ink-3">{{ __('buyer.order.address') }}</div>
                            <div class="flex flex-wrap items-center gap-2">
                                <code dir="ltr" class="min-w-0 break-all rounded bg-surface-2 px-2 py-1" x-text="payment.address"></code>
                                <button type="button" class="btn btn-sm" @click="copy(payment.address)" x-text="copied ? t('buyer.order.copied') : t('buyer.order.copy')"></button>
                            </div>
                        </div>
                        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="stat">
                                <dt class="text-xs text-ink-3">{{ __('buyer.order.pay_status_label') }}</dt>
                                <dd><span class="badge" :class="payment.status === 'confirmed' ? 'badge-ok' : (payment.status === 'expired' || payment.status === 'underpaid' ? 'badge-bad' : 'badge-warn')"
                                          x-text="payStatusLabel(payment.status)"></span></dd>
                            </div>
                            <div class="stat">
                                <dt class="text-xs text-ink-3">{{ __('buyer.order.confirmations') }}</dt>
                                <dd class="font-medium" x-text="payment.confirmations ?? 0"></dd>
                            </div>
                            <div class="stat">
                                <dt class="text-xs text-ink-3">{{ __('buyer.order.received') }}</dt>
                                <dd class="break-all font-medium" x-text="fmtCrypto(payment.detected_atomic, payment.method) + ' ' + unit(payment.method)"></dd>
                            </div>
                            <div class="stat" x-show="payment.expires_at">
                                <dt class="text-xs text-ink-3">{{ __('buyer.order.expires') }}</dt>
                                <dd class="font-medium" x-text="fmtDate(payment.expires_at)"></dd>
                            </div>
                        </dl>
                        <p class="text-sm text-ink-3">{{ __('buyer.order.auto_refresh') }}</p>
                    </div>
                </template>
            </section>

            {{-- Items and totals --}}
            <section class="card" aria-labelledby="items-h">
                <h2 id="items-h" class="mb-2 text-lg font-medium">{{ __('buyer.order.items') }}</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>{{ __('buyer.order.col_item') }}</th>
                            <th>{{ __('buyer.order.col_price') }}</th>
                            <th>{{ __('buyer.order.col_qty') }}</th>
                            <th>{{ __('buyer.order.col_total') }}</th>
                        </tr></thead>
                        <tbody>
                            <template x-for="i in order.items" :key="i.product_id">
                                <tr>
                                    <td x-text="i.title"></td>
                                    <td x-text="money(i.unit_price_cents, order.currency)"></td>
                                    <td x-text="i.quantity"></td>
                                    <td x-text="money(i.line_total_cents, order.currency)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center justify-between font-medium">
                    <span>{{ __('buyer.order.total') }}</span>
                    <span x-text="money(order.subtotal_cents, order.currency)"></span>
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div x-show="order.vendor">
                        <dt class="text-xs text-ink-3">{{ __('buyer.order.vendor') }}</dt>
                        <dd><a :href="'/v/' + order.vendor_id" x-text="order.vendor ? (order.vendor.shop_name || order.vendor.handle) : ''"></a></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-ink-3">{{ __('buyer.order.pay_method') }}</dt>
                        <dd x-text="t('buyer.shared.method_' + order.payment_method)"></dd>
                    </div>
                    <div x-show="order.tracking_number">
                        <dt class="text-xs text-ink-3">{{ __('buyer.order.tracking') }}</dt>
                        <dd class="break-all font-medium" x-text="order.tracking_number"></dd>
                    </div>
                    <div class="sm:col-span-3" x-show="order.shipping_address">
                        <dt class="text-xs text-ink-3">{{ __('buyer.order.ship_to') }}</dt>
                        <dd class="whitespace-pre-line" x-text="order.shipping_address"></dd>
                    </div>
                </dl>
            </section>

            {{-- Actions --}}
            <section class="card flex flex-col gap-3" x-show="canConfirm || canCancel" x-cloak aria-label="{{ __('buyer.order.actions') }}">
                <div x-show="canConfirm" class="flex flex-col gap-2">
                    <p class="text-sm text-ink-2">{{ __('buyer.order.confirm_help') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary" x-show="confirming !== 'confirm'" @click="confirming = 'confirm'">{{ __('buyer.order.confirm') }}</button>
                        <template x-if="confirming === 'confirm'">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm">{{ __('buyer.order.confirm_sure') }}</span>
                                <button type="button" class="btn btn-primary" :disabled="busy" @click="act('confirm')">{{ __('buyer.order.confirm_yes') }}</button>
                                <button type="button" class="btn" :disabled="busy" @click="confirming = ''">{{ __('common.cancel') }}</button>
                            </div>
                        </template>
                    </div>
                </div>
                <div x-show="canCancel" class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-danger" x-show="confirming !== 'cancel'" @click="confirming = 'cancel'">{{ __('buyer.order.cancel') }}</button>
                    <template x-if="confirming === 'cancel'">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm">{{ __('buyer.order.cancel_sure') }}</span>
                            <button type="button" class="btn btn-danger" :disabled="busy" @click="act('cancel')">{{ __('buyer.order.cancel_yes') }}</button>
                            <button type="button" class="btn" :disabled="busy" @click="confirming = ''">{{ __('buyer.order.keep') }}</button>
                        </div>
                    </template>
                </div>
            </section>

            {{-- Dispute --}}
            <section class="card" x-show="dispute || canDispute" x-cloak aria-labelledby="disp-h">
                <h2 id="disp-h" class="mb-2 text-lg font-medium">{{ __('buyer.order.dispute') }}</h2>
                <template x-if="dispute">
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge" :class="dispute.status === 'open' ? 'badge-bad' : 'badge-ok'" x-text="dispute.status === 'open' ? t('buyer.order.dispute_open') : t('buyer.order.dispute_resolved')"></span>
                            <span class="text-sm text-ink-3" x-text="fmtDate(dispute.created_at)"></span>
                        </div>
                        <p class="whitespace-pre-line" x-text="dispute.reason"></p>
                        <p x-show="dispute.resolution" class="text-sm text-ink-2" x-text="t('buyer.order.resolution', {text: dispute.resolution ?? ''})"></p>
                        <ul class="flex flex-col gap-2">
                            <template x-for="m in (dispute.messages ?? [])" :key="m.id">
                                <li class="rounded-lg bg-surface-2 p-2 text-sm">
                                    <div class="text-xs text-ink-3" x-text="(m.user_id === me ? t('buyer.order.you') : t('buyer.order.other_party')) + ' · ' + fmtDate(m.created_at)"></div>
                                    <p class="whitespace-pre-line" x-text="m.body"></p>
                                </li>
                            </template>
                        </ul>
                        <form x-show="dispute.status === 'open'" class="flex flex-col gap-2" @submit.prevent="sendDisputeMessage()">
                            <label for="dmsg">{{ __('buyer.order.dispute_add') }}</label>
                            <textarea id="dmsg" x-model="disputeMsg" maxlength="5000" required></textarea>
                            <button type="submit" class="btn self-start" :disabled="busy || !disputeMsg.trim()">{{ __('buyer.order.send') }}</button>
                        </form>
                    </div>
                </template>
                <template x-if="!dispute && canDispute">
                    <form class="flex flex-col gap-2" @submit.prevent="openDispute()">
                        <p class="text-sm text-ink-2">{{ __('buyer.order.dispute_help') }}</p>
                        <label for="reason">{{ __('buyer.order.dispute_reason') }}</label>
                        <textarea id="reason" x-model="reason" minlength="10" maxlength="5000" required></textarea>
                        <button type="submit" class="btn btn-danger self-start" :disabled="busy || reason.trim().length < 10">{{ __('buyer.order.dispute_open_btn') }}</button>
                    </form>
                </template>
            </section>

            {{-- Reviews --}}
            <section class="card" x-show="order.status === 'completed'" x-cloak aria-labelledby="rev-h">
                <h2 id="rev-h" class="mb-2 text-lg font-medium">{{ __('buyer.order.review') }}</h2>
                <div class="flex flex-col gap-4">
                    <template x-for="i in order.items" :key="i.product_id">
                        <div class="border-b border-line pb-4 last:border-0 last:pb-0" x-data="{ r: reviewFor(i.product_id) }">
                            <div class="mb-2 font-medium" x-text="i.title"></div>
                            <p x-show="r.done" class="text-sm text-ok" role="status">{{ __('buyer.order.review_thanks') }}</p>
                            <form x-show="!r.done" class="flex flex-col gap-2" @submit.prevent="sendReview(i.product_id)">
                                <div>
                                    <label :for="'rating-' + i.product_id">{{ __('buyer.order.rating') }}</label>
                                    <select :id="'rating-' + i.product_id" x-model.number="r.rating">
                                        <template x-for="n in [5,4,3,2,1]" :key="n">
                                            <option :value="n" x-text="t('buyer.shared.stars', {count: n})"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label :for="'body-' + i.product_id">{{ __('buyer.order.review_body') }}</label>
                                    <textarea :id="'body-' + i.product_id" x-model="r.body" maxlength="5000"></textarea>
                                </div>
                                <p class="text-sm text-bad" x-show="r.error" x-cloak role="alert" x-text="r.error"></p>
                                <button type="submit" class="btn btn-primary self-start" :disabled="r.busy">{{ __('buyer.order.review_send') }}</button>
                            </form>
                        </div>
                    </template>
                </div>
            </section>

            {{-- Message the vendor --}}
            <section class="card" aria-labelledby="msg-h">
                <h2 id="msg-h" class="mb-2 text-lg font-medium">{{ __('buyer.order.message_vendor') }}</h2>
                <form class="flex flex-col gap-2" @submit.prevent="sendMessage()">
                    <label for="mbody">{{ __('buyer.order.message_label') }}</label>
                    <textarea id="mbody" x-model="message" maxlength="5000" required></textarea>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="btn" :disabled="busy || !message.trim()">{{ __('buyer.order.send') }}</button>
                        <a :href="'{{ route('buyer.messages') }}?c=' + sentConversation" x-show="sentConversation" x-cloak>{{ __('buyer.order.open_thread') }}</a>
                    </div>
                </form>
            </section>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('buyerOrder', (id) => ({
        id, order: null, payment: null, dispute: null, me: null,
        loading: true, error: '', payLoading: false, payError: '', payUnavailable: false,
        busy: false, notice: '', actionError: '', confirming: '',
        reason: '', disputeMsg: '', message: '', sentConversation: '', copied: false,
        reviews: {}, timer: null,

        get steps() {
            return [
                {key: 'pending_payment', label: t('buyer.order.step_payment')},
                {key: 'paid', label: t('buyer.order.step_paid')},
                {key: 'shipped', label: t('buyer.order.step_shipped')},
                {key: 'completed', label: t('buyer.order.step_done')},
            ];
        },
        get stepIndex() {
            const i = ['pending_payment', 'paid', 'shipped', 'completed'].indexOf(this.order.status);
            if (i >= 0) return i;
            if (this.order.status === 'disputed') return this.order.shipped_at ? 2 : (this.order.paid_at ? 1 : 0);
            return -1;
        },
        get canConfirm() { return this.order?.status === 'shipped'; },
        get canCancel() { return this.order?.status === 'pending_payment'; },
        get canDispute() { return ['paid', 'shipped'].includes(this.order?.status); },
        unit(method) { return method === 'monero' ? t('buyer.order.unit_xmr') : t('buyer.order.unit_btc'); },
        payStatusLabel(s) {
            const labels = {
                pending: t('buyer.order.ps_pending'), detected: t('buyer.order.ps_detected'), confirmed: t('buyer.order.ps_confirmed'),
                expired: t('buyer.order.ps_expired'), underpaid: t('buyer.order.ps_underpaid'),
            };
            return labels[s] ?? s;
        },
        reviewFor(pid) {
            return this.reviews[pid] ??= {rating: 5, body: '', busy: false, done: false, error: ''};
        },

        async init() {
            this.stop();
            this.loading = true; this.error = '';
            try {
                this.me = (await api('auth/me')).data?.id ?? null;
            } catch (e) { /* only used to label dispute messages */ }
            try {
                this.order = (await api('orders/' + this.id)).data;
            } catch (e) {
                this.error = e.status === 404 || e.status === 403 ? t('buyer.order.not_found') : errText(e);
                this.loading = false;
                return;
            }
            this.loading = false;
            this.afterLoad();
            this.loadDispute();
        },
        afterLoad() {
            if (this.order.status === 'pending_payment') {
                this.loadPayment();
                if (!this.timer) this.timer = setInterval(() => this.tick(), 15000);
            } else {
                this.stop();
            }
        },
        stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
        destroy() { this.stop(); },
        async tick() {
            if (document.hidden || this.busy) return;
            try {
                this.order = (await api('orders/' + this.id)).data;
            } catch (e) { return; }
            this.afterLoad();
        },
        async loadPayment() {
            this.payLoading = true; this.payError = '';
            try {
                this.payment = (await api('orders/' + this.id + '/payment')).data;
                this.payUnavailable = false;
            } catch (e) {
                this.payUnavailable = e.status === 404;
                this.payError = e.status === 404 ? '' : errText(e);
            }
            finally { this.payLoading = false; }
        },
        async loadDispute() {
            try {
                const r = await api('disputes', {query: {per_page: 100}});
                const found = r.data.find(d => d.order_id === this.order.id);
                this.dispute = found ? (await api('disputes/' + found.id)).data : null;
            } catch (e) { /* disputes are optional on this page */ }
        },
        async refresh() {
            this.order = (await api('orders/' + this.id)).data;
            this.afterLoad();
        },
        async run(fn, okText) {
            if (this.busy) return;
            this.busy = true; this.actionError = ''; this.notice = '';
            try { await fn(); if (okText) this.notice = okText; }
            catch (e) { this.actionError = errText(e); }
            finally { this.busy = false; this.confirming = ''; }
        },
        act(kind) {
            return this.run(async () => {
                await api('orders/' + this.id + '/' + kind, {method: 'POST'});
                await this.refresh();
            }, kind === 'confirm' ? t('buyer.order.confirmed') : t('buyer.order.cancelled'));
        },
        openDispute() {
            return this.run(async () => {
                await api('orders/' + this.id + '/disputes', {method: 'POST', body: {reason: this.reason.trim()}});
                this.reason = '';
                await this.refresh();
                await this.loadDispute();
            }, t('buyer.order.dispute_opened'));
        },
        sendDisputeMessage() {
            return this.run(async () => {
                await api('disputes/' + this.dispute.id + '/messages', {method: 'POST', body: {body: this.disputeMsg.trim()}});
                this.disputeMsg = '';
                await this.loadDispute();
            });
        },
        sendMessage() {
            return this.run(async () => {
                const r = await api('conversations', {method: 'POST', body: {order_id: this.order.id, body: this.message.trim()}});
                this.sentConversation = r.data.id;
                this.message = '';
            }, t('buyer.order.message_sent'));
        },
        async sendReview(pid) {
            const r = this.reviewFor(pid);
            if (r.busy) return;
            r.busy = true; r.error = '';
            try {
                await api('products/' + pid + '/reviews', {method: 'POST', body: {order_id: this.order.id, rating: r.rating, body: r.body.trim() || null}});
                r.done = true;
            } catch (e) { r.error = errText(e); }
            finally { r.busy = false; }
        },
        async copy(text) {
            try { await navigator.clipboard.writeText(text); this.copied = true; setTimeout(() => { this.copied = false; }, 2000); }
            catch (e) { /* clipboard blocked: the address is selectable */ }
        },
    }));
});
</script>
@endsection
