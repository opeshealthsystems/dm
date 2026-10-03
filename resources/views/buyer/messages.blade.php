@extends('layouts.dashboard')

@section('title', __('buyer.messages.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="buyerMessages()" x-on:beforeunload.window="stop()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('buyer.messages.title') }}</h1>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="init()">{{ __('buyer.shared.retry') }}</button>
    </div>
    <p x-show="loaded && !loading && !error && list.length === 0" x-cloak class="card text-ink-2">{{ __('buyer.messages.empty') }}</p>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-[300px_minmax(0,1fr)]" x-show="list.length > 0" x-cloak>
        {{-- Conversation list --}}
        <ul class="flex flex-col gap-2" :class="active ? 'hidden md:flex' : ''" aria-label="{{ __('buyer.messages.conversations') }}">
            <template x-for="c in list" :key="c.id">
                <li>
                    <button type="button" class="card w-full text-start hover:bg-surface-2" :class="active && active.id === c.id ? 'border-accent' : ''"
                            :aria-current="active && active.id === c.id ? 'true' : null" @click="open(c)">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate font-medium" x-text="who(c)"></span>
                            <span class="badge badge-accent" x-show="c.unread_count > 0" x-text="c.unread_count"></span>
                        </div>
                        <div class="truncate text-sm text-ink-2" x-text="c.order_number ? t('buyer.messages.about_order', {number: c.order_number}) : (c.subject || t('buyer.messages.no_subject'))"></div>
                        <div class="text-xs text-ink-3" x-text="fmtDate(c.last_message_at)"></div>
                    </button>
                </li>
            </template>
        </ul>

        {{-- Thread --}}
        <section class="card flex min-h-[320px] flex-col gap-3" :class="active ? '' : 'hidden md:flex'" aria-live="polite">
            <p x-show="!active" class="m-auto text-ink-3">{{ __('buyer.messages.pick') }}</p>
            <template x-if="active">
                <div class="flex min-h-0 flex-1 flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn btn-sm md:hidden" @click="active = null">{{ __('common.back') }}</button>
                        <div class="min-w-0">
                            <div class="truncate font-medium" x-text="who(active)"></div>
                            <a class="text-sm" x-show="active.order_id" :href="'/account/orders/' + active.order_id" x-text="t('buyer.messages.about_order', {number: active.order_number ?? active.order_id})"></a>
                        </div>
                    </div>
                    <p x-show="threadLoading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
                    <p x-show="threadError" x-cloak class="text-bad" role="alert" x-text="threadError"></p>
                    <ul class="flex max-h-[50vh] flex-1 flex-col gap-2 overflow-y-auto" x-ref="thread">
                        <template x-for="m in messages" :key="m.id">
                            <li class="max-w-[85%] rounded-lg px-3 py-2"
                                :class="m.sender_id === me ? 'self-end bg-accent-soft text-ink' : 'self-start bg-surface-2 text-ink'">
                                <p class="whitespace-pre-line break-words" x-text="m.body"></p>
                                <div class="text-xs text-ink-3" x-text="fmtDate(m.created_at)"></div>
                            </li>
                        </template>
                    </ul>
                    <form class="flex flex-col gap-2" @submit.prevent="send()">
                        <label for="reply">{{ __('buyer.messages.reply') }}</label>
                        <textarea id="reply" x-model="draft" maxlength="5000" required @keydown.ctrl.enter.prevent="send()"></textarea>
                        <p class="text-sm text-bad" x-show="sendError" x-cloak role="alert" x-text="sendError"></p>
                        <button type="submit" class="btn btn-primary self-start" :disabled="sending || !draft.trim()">{{ __('buyer.order.send') }}</button>
                    </form>
                </div>
            </template>
        </section>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('buyerMessages', () => ({
        list: [], active: null, messages: [], me: null, draft: '', timer: null,
        loading: false, loaded: false, error: '', threadLoading: false, threadError: '', sending: false, sendError: '',
        who(c) {
            const other = (c.participants ?? []).find(p => p.user_id !== this.me);
            return other?.display_name || t('buyer.messages.unknown');
        },
        async init() {
            this.loading = true; this.error = '';
            try {
                this.me = (await api('auth/me')).data.id;
                await this.loadList();
                const want = parseInt(new URLSearchParams(location.search).get('c') ?? '0', 10);
                const c = this.list.find(x => x.id === want);
                if (c) this.open(c);
            } catch (e) { this.error = errText(e); }
            finally { this.loading = false; this.loaded = true; }
            if (!this.timer) this.timer = setInterval(() => this.poll(), 15000);
        },
        stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
        destroy() { this.stop(); },
        async loadList() {
            this.list = (await api('conversations', {query: {per_page: 50}})).data;
            if (this.active) this.active = this.list.find(x => x.id === this.active.id) ?? this.active;
        },
        async poll() {
            if (document.hidden) return;
            try { await this.loadList(); if (this.active && !this.threadLoading) await this.loadThread(false); } catch (e) { /* retry next tick */ }
        },
        open(c) {
            this.active = c; this.messages = []; this.sendError = '';
            history.replaceState(null, '', location.pathname + '?c=' + c.id);
            return this.loadThread(true);
        },
        async loadThread(spinner) {
            const id = this.active.id;
            if (spinner) this.threadLoading = true;
            this.threadError = '';
            try {
                let r = await api('conversations/' + id, {query: {per_page: 100}});
                if ((r.meta?.last_page ?? 1) > 1) r = await api('conversations/' + id, {query: {per_page: 100, page: r.meta.last_page}});
                if (this.active?.id !== id) return;
                this.messages = r.data;
                this.active.unread_count = 0;
                this.$nextTick(() => { const el = this.$refs.thread; if (el) el.scrollTop = el.scrollHeight; });
            } catch (e) { this.threadError = errText(e); }
            finally { this.threadLoading = false; }
        },
        async send() {
            if (this.sending || !this.draft.trim()) return;
            this.sending = true; this.sendError = '';
            try {
                const r = await api('conversations/' + this.active.id + '/messages', {method: 'POST', body: {body: this.draft.trim()}});
                this.messages.push(r.data);
                this.draft = '';
                this.$nextTick(() => { const el = this.$refs.thread; if (el) el.scrollTop = el.scrollHeight; });
            } catch (e) { this.sendError = errText(e); }
            finally { this.sending = false; }
        },
    }));
});
</script>
@endsection
