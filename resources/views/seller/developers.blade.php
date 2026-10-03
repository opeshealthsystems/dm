@extends('layouts.dashboard')

@section('title', __('seller.developers.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerDevelopers', () => ({
    loading: true, error: '',
    keys: [], hooks: [], usage: { rows: [], total: 0 },
    scopeList: ['catalog:read', 'orders:read'],
    eventList: ['order.placed', 'order.paid', 'order.shipped', 'order.completed', 'order.cancelled', 'order.refunded'],

    keyForm: { name: '', scopes: ['catalog:read'], expires_at: '' }, keyErrors: {}, keyError: '', keyBusy: false, newKey: '',
    hookForm: { url: '', events: ['order.paid'] }, hookErrors: {}, hookError: '', hookBusy: false, newSecret: '',
    pending: null, pendingBusy: false, pendingError: '',
    copied: '',
    open: null, deliveries: [], dMeta: { current_page: 1, last_page: 1 }, dLoading: false, dError: '',

    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            const [k, h, u] = await Promise.all([api('developer/keys'), api('developer/webhooks'), api('developer/usage', { query: { days: 30 } })]);
            this.keys = k.data; this.hooks = h.data;
            this.usage = { rows: u.data, total: u.total_requests };
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },

    /* Keys */
    keyStatus(k) {
        if (k.revoked_at) return 'revoked';
        if (k.expires_at && new Date(k.expires_at) < new Date()) return 'expired';
        return 'active';
    },
    keyBadge(k) { return 'badge ' + ({ active: 'badge-ok', revoked: 'badge-bad', expired: 'badge-warn' }[this.keyStatus(k)]); },
    async createKey() {
        this.keyError = ''; this.keyErrors = {}; this.newKey = '';
        const f = this.keyForm; const e = {};
        const name = f.name.trim();
        if (!name) e.name = t('seller.developers.err_name'); else if (name.length > 100) e.name = t('seller.developers.err_name_long');
        if (!f.scopes.length) e.scopes = t('seller.developers.err_scopes');
        if (f.expires_at && new Date(f.expires_at + 'T23:59:59') <= new Date()) e.expires_at = t('seller.developers.err_expiry');
        this.keyErrors = e;
        if (Object.keys(e).length || this.keyBusy) return;
        this.keyBusy = true;
        try {
            const body = { name, scopes: f.scopes };
            if (f.expires_at) body.expires_at = f.expires_at + 'T23:59:59';
            const r = await api('developer/keys', { method: 'POST', body });
            this.newKey = r.key;
            this.keyForm = { name: '', scopes: ['catalog:read'], expires_at: '' };
            this.keys = (await api('developer/keys')).data;
        } catch (err) { this.keyErrors = sellerUi.fieldErrors(err); this.keyError = sellerUi.err(err); } finally { this.keyBusy = false; }
    },

    /* Webhooks */
    async createHook() {
        this.hookError = ''; this.hookErrors = {}; this.newSecret = '';
        const f = this.hookForm; const e = {};
        const url = f.url.trim();
        if (!/^https?:\/\/[^\s]+$/i.test(url) || url.length > 500) e.url = t('seller.developers.err_url');
        if (!f.events.length) e.events = t('seller.developers.err_events');
        this.hookErrors = e;
        if (Object.keys(e).length || this.hookBusy) return;
        this.hookBusy = true;
        try {
            const r = await api('developer/webhooks', { method: 'POST', body: { url, events: f.events } });
            this.newSecret = r.secret;
            this.hookForm = { url: '', events: ['order.paid'] };
            this.hooks = (await api('developer/webhooks')).data;
        } catch (err) { this.hookErrors = sellerUi.fieldErrors(err); this.hookError = sellerUi.err(err); } finally { this.hookBusy = false; }
    },
    async toggleDeliveries(h) {
        if (this.open === h.id) { this.open = null; return; }
        this.open = h.id; this.dMeta = { current_page: 1, last_page: 1 };
        await this.loadDeliveries(h.id, 1);
    },
    async loadDeliveries(id, page) {
        this.dLoading = true; this.dError = '';
        try {
            const r = await api('developer/webhooks/' + id + '/deliveries', { query: { page } });
            this.deliveries = r.data; this.dMeta = { current_page: r.current_page, last_page: r.last_page };
        } catch (e) { this.dError = sellerUi.err(e); } finally { this.dLoading = false; }
    },
    deliveryBadge(s) { return sellerUi.badge('delivery', s); },

    /* Confirm revoke / delete */
    ask(type, item) {
        this.trigger = document.activeElement; this.pending = { type, item }; this.pendingError = '';
        this.$nextTick(() => { const b = this.$refs.confirmBox; if (b) { b.scrollIntoView({ block: 'center' }); b.focus(); } });
    },
    cancel() { this.pending = null; this.trigger?.focus?.(); },
    async confirm() {
        if (!this.pending || this.pendingBusy) return;
        this.pendingBusy = true; this.pendingError = '';
        const { type, item } = this.pending;
        try {
            if (type === 'key') {
                await api('developer/keys/' + item.id, { method: 'DELETE' });
                this.keys = (await api('developer/keys')).data;
            } else {
                await api('developer/webhooks/' + item.id, { method: 'DELETE' });
                if (this.open === item.id) this.open = null;
                this.hooks = (await api('developer/webhooks')).data;
            }
            this.pending = null;
        } catch (e) { this.pendingError = sellerUi.err(e); } finally { this.pendingBusy = false; }
    },

    /* Copy to clipboard, with a fallback for non-secure contexts. */
    async copy(text, which) {
        try {
            if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text);
            else {
                const ta = document.createElement('textarea');
                ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.insetInlineStart = '-9999px';
                document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
            }
            this.copied = which;
            setTimeout(() => { if (this.copied === which) this.copied = ''; }, 2000);
        } catch (e) { this.copied = ''; }
    },

    /* Usage */
    get days() {
        const by = {};
        this.usage.rows.forEach(r => { by[r.day] = (by[r.day] || 0) + r.requests; });
        return Object.entries(by).sort((a, b) => b[0].localeCompare(a[0])).map(([day, n]) => ({ day, n }));
    },
    get maxDay() { return Math.max(1, ...this.days.map(d => d.n)); },
    get perKey() {
        const by = {};
        this.usage.rows.forEach(r => { by[r.prefix] = (by[r.prefix] || 0) + r.requests; });
        return Object.entries(by).map(([prefix, n]) => ({ prefix, n }));
    },
})));
</script>

<div x-data="sellerDevelopers">
    <h1 class="mb-4 text-2xl font-medium">{{ __('seller.developers.title') }}</h1>

    @include('seller.partials.state')

    <div class="flex flex-col gap-8" x-show="!loading && !error" x-cloak>

        <div class="card" role="alertdialog" x-ref="confirmBox" tabindex="-1" x-show="pending" x-cloak @keydown.escape="cancel()">
            <p class="font-medium" x-text="pending ? t(pending.type === 'key' ? 'seller.developers.confirm_revoke' : 'seller.developers.confirm_delete', { name: pending.type === 'key' ? pending.item.name : pending.item.url }) : ''"></p>
            <p class="mt-1 text-sm text-bad" role="alert" x-show="pendingError" x-text="pendingError"></p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="btn btn-danger" :disabled="pendingBusy" @click="confirm()" x-text="pending && pending.type === 'key' ? t('seller.developers.revoke') : t('seller.developers.delete')"></button>
                <button type="button" class="btn" :disabled="pendingBusy" @click="cancel()">{{ __('common.cancel') }}</button>
            </div>
        </div>

        {{-- API keys --}}
        <section class="flex flex-col gap-4">
            <div>
                <h2 class="text-lg font-medium">{{ __('seller.developers.keys_title') }}</h2>
                <p class="text-sm text-ink-2">{{ __('seller.developers.keys_hint') }}</p>
            </div>

            <div class="card border-accent" role="status" x-show="newKey">
                <p class="font-medium">{{ __('seller.developers.key_once') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="min-w-0 flex-1 break-all rounded-lg bg-surface-2 p-2" x-text="newKey"></code>
                    <button type="button" class="btn" @click="copy(newKey, 'key')" x-text="copied === 'key' ? t('seller.developers.copied') : t('seller.developers.copy')"></button>
                    <button type="button" class="btn" @click="newKey = ''">{{ __('seller.developers.dismiss') }}</button>
                </div>
            </div>

            <form class="card grid max-w-2xl gap-4 sm:grid-cols-2" @submit.prevent="createKey()" novalidate>
                <div>
                    <label for="k-name">{{ __('seller.developers.key_name') }}</label>
                    <input id="k-name" type="text" x-model="keyForm.name" maxlength="100" :aria-invalid="!!keyErrors.name">
                    <p class="mt-1 text-sm text-bad" x-show="keyErrors.name" x-text="keyErrors.name"></p>
                </div>
                <div>
                    <label for="k-exp">{{ __('seller.developers.key_expires') }}</label>
                    <input id="k-exp" type="date" x-model="keyForm.expires_at">
                    <p class="mt-1 text-sm text-bad" x-show="keyErrors.expires_at" x-text="keyErrors.expires_at"></p>
                </div>
                <fieldset class="sm:col-span-2">
                    <legend class="mb-1 text-sm text-ink-2">{{ __('seller.developers.scopes') }}</legend>
                    <div class="flex flex-wrap gap-4">
                        <template x-for="s in scopeList" :key="s">
                            <label class="mb-0 flex min-h-[44px] items-center gap-2 text-ink">
                                <input type="checkbox" class="!min-h-0 !w-auto" :value="s" x-model="keyForm.scopes">
                                <span x-text="s"></span>
                            </label>
                        </template>
                    </div>
                    <p class="mt-1 text-sm text-bad" x-show="keyErrors.scopes" x-text="keyErrors.scopes"></p>
                </fieldset>
                <div class="sm:col-span-2">
                    <p class="mb-2 text-sm text-bad" role="alert" x-show="keyError && !keyErrors.name && !keyErrors.scopes && !keyErrors.expires_at" x-text="keyError"></p>
                    <button type="submit" class="btn btn-primary" :disabled="keyBusy" x-text="keyBusy ? t('seller.developers.creating') : t('seller.developers.key_create')"></button>
                </div>
            </form>

            <div class="card text-ink-2" x-show="!keys.length">{{ __('seller.developers.keys_empty') }}</div>
            <div class="card table-wrap" x-show="keys.length">
                <table class="min-w-[560px]">
                    <thead><tr>
                        <th>{{ __('seller.developers.key_name') }}</th>
                        <th>{{ __('seller.developers.prefix') }}</th>
                        <th>{{ __('seller.developers.last_used') }}</th>
                        <th>{{ __('seller.developers.status') }}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="k in keys" :key="k.id">
                            <tr>
                                <td x-text="k.name"></td>
                                <td><code x-text="k.prefix"></code></td>
                                <td x-text="k.last_used_at ? fmtDate(k.last_used_at) : t('seller.developers.never')"></td>
                                <td><span :class="keyBadge(k)" x-text="t('seller.developers.key_' + keyStatus(k))"></span></td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-danger" x-show="keyStatus(k) === 'active'" @click="ask('key', k)" x-text="t('seller.developers.revoke')"></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Webhooks --}}
        <section class="flex flex-col gap-4">
            <div>
                <h2 class="text-lg font-medium">{{ __('seller.developers.hooks_title') }}</h2>
                <p class="text-sm text-ink-2">{{ __('seller.developers.hooks_hint') }}</p>
            </div>

            <div class="card border-accent" role="status" x-show="newSecret">
                <p class="font-medium">{{ __('seller.developers.secret_once') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="min-w-0 flex-1 break-all rounded-lg bg-surface-2 p-2" x-text="newSecret"></code>
                    <button type="button" class="btn" @click="copy(newSecret, 'secret')" x-text="copied === 'secret' ? t('seller.developers.copied') : t('seller.developers.copy')"></button>
                    <button type="button" class="btn" @click="newSecret = ''">{{ __('seller.developers.dismiss') }}</button>
                </div>
            </div>

            <form class="card grid max-w-2xl gap-4" @submit.prevent="createHook()" novalidate>
                <div>
                    <label for="h-url">{{ __('seller.developers.hook_url') }}</label>
                    <input id="h-url" type="url" x-model="hookForm.url" maxlength="500" placeholder="https://" :aria-invalid="!!hookErrors.url">
                    <p class="mt-1 text-sm text-bad" x-show="hookErrors.url" x-text="hookErrors.url"></p>
                </div>
                <fieldset>
                    <legend class="mb-1 text-sm text-ink-2">{{ __('seller.developers.events') }}</legend>
                    <div class="flex flex-wrap gap-x-4">
                        <template x-for="ev in eventList" :key="ev">
                            <label class="mb-0 flex min-h-[44px] items-center gap-2 text-ink">
                                <input type="checkbox" class="!min-h-0 !w-auto" :value="ev" x-model="hookForm.events">
                                <span x-text="ev"></span>
                            </label>
                        </template>
                    </div>
                    <p class="mt-1 text-sm text-bad" x-show="hookErrors.events" x-text="hookErrors.events"></p>
                </fieldset>
                <div>
                    <p class="mb-2 text-sm text-bad" role="alert" x-show="hookError && !hookErrors.url && !hookErrors.events" x-text="hookError"></p>
                    <button type="submit" class="btn btn-primary" :disabled="hookBusy" x-text="hookBusy ? t('seller.developers.creating') : t('seller.developers.hook_create')"></button>
                </div>
            </form>

            <div class="card text-ink-2" x-show="!hooks.length">{{ __('seller.developers.hooks_empty') }}</div>
            <div class="flex flex-col gap-3">
                <template x-for="h in hooks" :key="h.id">
                    <article class="card">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="break-all font-medium" x-text="h.url"></div>
                                <div class="text-sm text-ink-3" x-text="(h.events || []).join(', ')"></div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge" :class="h.active ? 'badge-ok' : ''" x-text="h.active ? t('seller.developers.hook_active') : t('seller.developers.hook_inactive')"></span>
                                <button type="button" class="btn btn-sm" @click="toggleDeliveries(h)" :aria-expanded="open === h.id" x-text="open === h.id ? t('seller.developers.hide_log') : t('seller.developers.show_log')"></button>
                                <button type="button" class="btn btn-sm btn-danger" @click="ask('hook', h)" x-text="t('seller.developers.delete')"></button>
                            </div>
                        </div>

                        <div class="mt-3" x-show="open === h.id">
                            <div class="text-ink-2" x-show="dLoading" role="status">{{ __('common.loading') }}</div>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-bad" x-show="dError" role="alert">
                                <span x-text="dError"></span>
                                <button type="button" class="btn btn-sm" @click="loadDeliveries(h.id, dMeta.current_page)">{{ __('seller.shared.retry') }}</button>
                            </div>
                            <p class="text-ink-2" x-show="!dLoading && !dError && !deliveries.length">{{ __('seller.developers.log_empty') }}</p>
                            <div class="table-wrap" x-show="deliveries.length">
                                <table class="min-w-[520px]">
                                    <thead><tr>
                                        <th>{{ __('seller.developers.log_event') }}</th>
                                        <th>{{ __('seller.developers.status') }}</th>
                                        <th>{{ __('seller.developers.log_attempts') }}</th>
                                        <th>{{ __('seller.developers.log_response') }}</th>
                                        <th>{{ __('seller.developers.log_when') }}</th>
                                    </tr></thead>
                                    <tbody>
                                        <template x-for="d in deliveries" :key="d.id">
                                            <tr>
                                                <td x-text="d.event"></td>
                                                <td><span :class="deliveryBadge(d.status)" x-text="d.status"></span></td>
                                                <td x-text="d.attempts"></td>
                                                <td class="break-all text-sm" x-text="d.response_code || d.error || ''"></td>
                                                <td x-text="fmtDate(d.delivered_at || d.created_at)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-2" x-show="dMeta.last_page > 1">
                                <button type="button" class="btn btn-sm" :disabled="dLoading || dMeta.current_page <= 1" @click="loadDeliveries(h.id, dMeta.current_page - 1)" x-text="t('seller.shared.prev')"></button>
                                <span class="text-sm text-ink-2" x-text="t('seller.shared.page_of', { page: dMeta.current_page, total: dMeta.last_page })"></span>
                                <button type="button" class="btn btn-sm" :disabled="dLoading || dMeta.current_page >= dMeta.last_page" @click="loadDeliveries(h.id, dMeta.current_page + 1)" x-text="t('seller.shared.next')"></button>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        {{-- Usage --}}
        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">{{ __('seller.developers.usage_title') }}</h2>
            <div class="stat max-w-xs">
                <div class="text-sm text-ink-3">{{ __('seller.developers.usage_total') }}</div>
                <div class="text-xl font-medium" x-text="usage.total"></div>
            </div>
            <div class="card text-ink-2" x-show="!days.length">{{ __('seller.developers.usage_empty') }}</div>
            <div class="grid gap-4 lg:grid-cols-2" x-show="days.length">
                <div class="card table-wrap">
                    <table>
                        <thead><tr><th>{{ __('seller.developers.usage_day') }}</th><th>{{ __('seller.developers.usage_requests') }}</th><th class="w-1/2"></th></tr></thead>
                        <tbody>
                            <template x-for="d in days" :key="d.day">
                                <tr>
                                    <td x-text="d.day"></td>
                                    <td x-text="d.n"></td>
                                    <td><div class="h-2 rounded bg-accent" :style="'width:' + Math.max(2, Math.round(d.n / maxDay * 100)) + '%'"></div></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="card table-wrap">
                    <table>
                        <thead><tr><th>{{ __('seller.developers.prefix') }}</th><th>{{ __('seller.developers.usage_requests') }}</th></tr></thead>
                        <tbody>
                            <template x-for="k in perKey" :key="k.prefix">
                                <tr><td><code x-text="k.prefix"></code></td><td x-text="k.n"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
