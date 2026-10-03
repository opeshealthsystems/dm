@extends('layouts.dashboard')

@section('title', __('seller.messages.title') . ' | ' . config('app.name'))

@section('content')
@include('seller.partials.support')
<script>
document.addEventListener('alpine:init', () => Alpine.data('sellerMessages', () => ({
    loading: true, error: '', me: null,
    list: [], meta: { current_page: 1, last_page: 1, total: 0 }, page: 1,
    selected: null, messages: [], threadLoading: false, threadError: '',
    body: '', sendBusy: false, sendError: '',
    startOrder: new URLSearchParams(window.location.search).get('order'),
    init() { this.load(); },
    async load() {
        this.loading = true; this.error = '';
        try {
            if (!this.me) this.me = (await api('auth/me')).data;
            const r = await api('conversations', { query: { page: this.page } });
            this.list = r.data; this.meta = r.meta;
        } catch (e) { this.error = sellerUi.err(e); } finally { this.loading = false; }
    },
    go(p) { this.page = p; this.load(); },
    label(c) {
        if (c.subject) return c.subject;
        return c.order_id ? t('seller.messages.about_order', { id: c.order_id }) : t('seller.messages.conversation_n', { id: c.id });
    },
    async open(c) {
        this.selected = c; this.startOrder = null; this.sendError = ''; this.body = '';
        await this.loadThread();
    },
    async loadThread() {
        this.threadLoading = true; this.threadError = '';
        try {
            const id = this.selected.id;
            let r = await api('conversations/' + id, { query: { page: 1 } });
            if (r.meta.last_page > 1) r = await api('conversations/' + id, { query: { page: r.meta.last_page } });
            this.messages = r.data;
            this.selected.unread_count = 0;
            this.$nextTick(() => { const el = this.$refs.thread; if (el) el.scrollTop = el.scrollHeight; });
        } catch (e) { this.threadError = sellerUi.err(e); } finally { this.threadLoading = false; }
    },
    close() { this.selected = null; this.messages = []; },
    validBody() {
        const v = this.body.trim();
        if (!v) { this.sendError = t('seller.messages.err_required'); return null; }
        if (v.length > 5000) { this.sendError = t('seller.messages.err_long'); return null; }
        return v;
    },
    async send() {
        this.sendError = '';
        const v = this.validBody();
        if (v === null || this.sendBusy) return;
        this.sendBusy = true;
        try {
            if (this.selected) {
                await api('conversations/' + this.selected.id + '/messages', { method: 'POST', body: { body: v } });
                this.body = '';
                await this.loadThread();
            } else if (this.startOrder) {
                const res = await api('conversations', { method: 'POST', body: { order_id: parseInt(this.startOrder, 10), body: v } });
                this.body = ''; this.startOrder = null;
                await this.load();
                this.selected = this.list.find(c => c.id === res.data.id) || res.data;
                await this.loadThread();
            }
        } catch (e) { this.sendError = sellerUi.err(e); } finally { this.sendBusy = false; }
    },
    mine(m) { return this.me && m.sender_id === this.me.id; },
    get composing() { return !!(this.selected || this.startOrder); },
})));
</script>

<div x-data="sellerMessages">
    <h1 class="mb-4 text-2xl font-medium">{{ __('seller.messages.title') }}</h1>

    @include('seller.partials.state')

    <div x-show="!loading && !error" x-cloak class="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
        <section :class="composing ? 'hidden lg:block' : ''">
            <div class="card text-ink-2" x-show="list.length === 0">{{ __('seller.messages.empty') }}</div>
            <ul class="flex flex-col gap-2">
                <template x-for="c in list" :key="c.id">
                    <li>
                        <button type="button" class="card flex w-full min-h-[44px] items-center justify-between gap-2 text-start"
                                :class="selected && selected.id === c.id ? 'border-accent' : ''" @click="open(c)">
                            <span class="min-w-0">
                                <span class="block truncate font-medium" x-text="label(c)"></span>
                                <span class="block text-xs text-ink-3" x-text="c.last_message_at ? fmtDate(c.last_message_at) : ''"></span>
                            </span>
                            <span class="badge badge-accent" x-show="c.unread_count > 0" x-text="c.unread_count"></span>
                        </button>
                    </li>
                </template>
            </ul>
            @include('seller.partials.pager')
        </section>

        <section :class="composing ? '' : 'hidden lg:block'">
            <div class="card text-ink-2" x-show="!composing">{{ __('seller.messages.pick') }}</div>

            <div class="card flex flex-col gap-3" x-show="composing">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="min-w-0 truncate font-medium" x-text="selected ? label(selected) : t('seller.messages.new_about_order', { id: startOrder })"></h2>
                    <button type="button" class="btn btn-sm lg:hidden" @click="close(); startOrder = null" x-text="t('common.back')"></button>
                </div>

                <div x-show="selected">
                    <div class="text-ink-2" x-show="threadLoading" role="status">{{ __('common.loading') }}</div>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-bad" x-show="threadError" role="alert">
                        <span x-text="threadError"></span>
                        <button type="button" class="btn btn-sm" @click="loadThread()">{{ __('seller.shared.retry') }}</button>
                    </div>
                    <p class="text-ink-2" x-show="!threadLoading && !threadError && messages.length === 0">{{ __('seller.messages.thread_empty') }}</p>
                    <ul class="flex max-h-[50vh] flex-col gap-2 overflow-y-auto" x-ref="thread">
                        <template x-for="m in messages" :key="m.id">
                            <li class="max-w-[85%] rounded-lg p-2" :class="mine(m) ? 'self-end bg-accent-soft' : 'self-start bg-surface-2'">
                                <p class="whitespace-pre-line break-words" x-text="m.body"></p>
                                <div class="mt-1 text-xs text-ink-3" x-text="fmtDate(m.created_at)"></div>
                            </li>
                        </template>
                    </ul>
                </div>

                <form @submit.prevent="send()" novalidate>
                    <label for="msg-body">{{ __('seller.messages.write') }}</label>
                    <textarea id="msg-body" x-model="body" maxlength="5000"></textarea>
                    <p class="mt-1 text-sm text-bad" role="alert" x-show="sendError" x-text="sendError"></p>
                    <button type="submit" class="btn btn-primary mt-2" :disabled="sendBusy" x-text="sendBusy ? t('seller.messages.sending') : t('seller.messages.send')"></button>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection
