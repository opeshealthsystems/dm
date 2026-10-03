@extends('layouts.store')

@section('title', $thread->title . ' | ' . __('community.title') . ' | ' . config('app.name'))
@section('description', $description)
@section('canonical_url', $canonical)
@section('og_type', 'article')

@section('content')
@include('community._i18n')
<div x-data="communityThread(@js(['id' => $thread->id, 'signedIn' => $signedIn, 'subscribed' => $subscribed, 'category' => $thread->category->slug, 'markRead' => $signedIn && ! $posts->hasMorePages(), 'pinned' => $thread->is_pinned, 'locked' => $thread->is_locked]))">
    <a href="{{ route('community.category', $thread->category->slug) }}" class="back-link">{{ __('community.back_to_category', ['name' => $thread->category->displayName()]) }}</a>

    <header class="mb-4">
        <h1 class="mb-2">{{ $thread->title }}</h1>
        <div class="flex flex-wrap items-center gap-2 text-sm text-ink-3">
            @if ($thread->is_pinned)<span class="badge badge-warn">{{ __('community.thread.pinned') }}</span>@endif
            @if ($thread->is_locked)<span class="badge">{{ __('community.thread.locked') }}</span>@endif
            <span>{{ __('community.thread.by', ['name' => $thread->author->handle ?? '#' . $thread->user_id]) }}</span>
            <span aria-hidden="true">{{ '·' }}</span>
            <span>{{ __('community.thread.replies', ['count' => max(0, $thread->posts_count - 1)]) }}</span>
        </div>
        @if ($thread->product)
            <p class="mt-2 text-sm">{{ __('community.thread.about_product') }}
                <a href="{{ route('store.product', $thread->product->slug) }}">{{ $thread->product->title }}</a></p>
        @endif
    </header>

    <div class="mb-4 flex flex-wrap gap-2">
        @if ($signedIn)
            <button type="button" class="btn btn-sm" :disabled="busy" @click="toggleSubscribe()"
                    x-text="subscribed ? t('community.thread.unfollow') : t('community.thread.follow')"></button>
        @endif
        @if ($isAdmin)
            <button type="button" class="btn btn-sm" :disabled="busy" @click="moderate('pin')" x-text="pinned ? t('community.thread.unpin') : t('community.thread.pin')"></button>
            <button type="button" class="btn btn-sm" :disabled="busy" @click="moderate('lock')" x-text="locked ? t('community.thread.unlock') : t('community.thread.lock')"></button>
            <button type="button" class="btn btn-sm btn-danger" :disabled="busy" @click="removeThread()">{{ __('community.thread.delete_thread') }}</button>
        @endif
    </div>
    <p class="mb-3 text-sm text-ok" x-show="notice" x-cloak role="status" x-text="notice"></p>
    <p class="mb-3 text-sm text-bad" x-show="error" x-cloak role="alert" x-text="error"></p>

    <div class="flex flex-col gap-3">
        @forelse ($posts as $post)
            @include('community._post', ['post' => $post])
        @empty
            <p class="empty">{{ __('community.category.empty') }}</p>
        @endforelse
    </div>

    @if ($posts->lastPage() > 1)
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('community.pagination') }}">
            @if ($posts->onFirstPage())
                <span class="btn btn-sm" aria-disabled="true">{{ __('community.prev') }}</span>
            @else
                <a class="btn btn-sm" rel="prev" href="{{ $posts->previousPageUrl() }}">{{ __('community.prev') }}</a>
            @endif
            <span class="text-sm text-ink-2">{{ __('community.page_of', ['page' => $posts->currentPage(), 'last' => $posts->lastPage()]) }}</span>
            @if ($posts->hasMorePages())
                <a class="btn btn-sm" rel="next" href="{{ $posts->nextPageUrl() }}">{{ __('community.next') }}</a>
            @else
                <span class="btn btn-sm" aria-disabled="true">{{ __('community.next') }}</span>
            @endif
        </nav>
    @endif

    <section class="mt-6" aria-labelledby="reply-h">
        <h2 id="reply-h" class="mb-2 text-xl font-medium">{{ __('community.thread.reply_heading') }}</h2>
        @if (! $signedIn)
            <a class="btn btn-primary" href="{{ route('login') }}">{{ __('community.thread.login_to_reply') }}</a>
        @elseif (! $canReply)
            <p class="card text-ink-2">{{ __('community.thread.locked_notice') }}</p>
        @else
            <form class="card grid gap-3" @submit.prevent="reply()">
                <div>
                    <label for="reply-body">{{ __('community.thread.reply_label') }}</label>
                    <textarea id="reply-body" x-model="body" rows="5" maxlength="10000" required></textarea>
                    <p class="mt-1 text-sm text-ink-3">{{ __('community.thread.help', ['max' => config('community.new_account_max_links')]) }}</p>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" :disabled="posting" x-text="posting ? t('community.thread.posting') : t('community.thread.reply_submit')"></button>
                </div>
            </form>
        @endif
    </section>
</div>

<script>
document.addEventListener('alpine:init', () => {
    /** Thread-level actions. Posts themselves are server-rendered; this only calls the API. */
    Alpine.data('communityThread', (cfg) => ({
        id: cfg.id, subscribed: cfg.subscribed, pinned: cfg.pinned, locked: cfg.locked,
        body: '', posting: false, busy: false, error: '', notice: '',
        async init() {
            if (cfg.markRead) {
                try { await api('community/threads/' + this.id + '/read', {method: 'POST'}); }
                catch (e) { /* the unread marker is a convenience: ignore failures */ }
            }
        },
        async toggleSubscribe() {
            this.busy = true; this.error = ''; this.notice = '';
            try {
                await api('community/threads/' + this.id + '/subscription', {method: this.subscribed ? 'DELETE' : 'PUT'});
                this.subscribed = !this.subscribed;
                if (this.subscribed) this.notice = t('community.thread.followed');
            } catch (e) { this.error = errText(e); }
            finally { this.busy = false; }
        },
        async moderate(what) {
            this.busy = true; this.error = '';
            const on = what === 'pin' ? !this.pinned : !this.locked;
            try {
                await api('community/threads/' + this.id + '/' + what, {method: on ? 'POST' : 'DELETE'});
                window.location.reload();
            } catch (e) { this.error = errText(e); this.busy = false; }
        },
        async removeThread() {
            if (!window.confirm(t('community.thread.confirm_delete_thread'))) return;
            this.busy = true; this.error = '';
            try {
                await api('community/threads/' + this.id, {method: 'DELETE'});
                window.location.href = '/community/' + encodeURIComponent(cfg.category);
            } catch (e) { this.error = errText(e); this.busy = false; }
        },
        async reply() {
            if (this.posting) return;
            this.posting = true; this.error = '';
            try {
                const r = await api('community/threads/' + this.id + '/posts', {method: 'POST', body: {body: this.body}});
                const target = r.meta.thread_path + (r.meta.last_page > 1 ? '?page=' + r.meta.last_page : '') + '#post-' + r.data.id;
                window.location.href = target;
                if (target.split('#')[0] === window.location.href.split('#')[0]) window.location.reload();
            } catch (e) { this.error = errText(e); this.posting = false; }
        },
    }));

    /** Per-post actions: edit (15 minute window, enforced by the API), delete, report. */
    Alpine.data('communityPost', (id, isFirst, category) => ({
        id, editing: false, draft: '', saving: false, err: '', reporting: false, reason: 'spam', note: '', sending: false, reported: false,
        async startEdit() {
            this.err = '';
            try { this.draft = (await api('community/posts/' + this.id)).data.body; this.editing = true; }
            catch (e) { this.err = errText(e); }
        },
        async save() {
            this.saving = true; this.err = '';
            try { await api('community/posts/' + this.id, {method: 'PUT', body: {body: this.draft}}); window.location.reload(); }
            catch (e) { this.err = errText(e); this.saving = false; }
        },
        async remove() {
            if (!window.confirm(t('community.thread.confirm_delete'))) return;
            this.err = '';
            try {
                await api('community/posts/' + this.id, {method: 'DELETE'});
                if (isFirst) window.location.href = '/community/' + encodeURIComponent(category);
                else window.location.reload();
            } catch (e) { this.err = errText(e); }
        },
        async sendReport() {
            this.sending = true; this.err = '';
            try {
                await api('community/posts/' + this.id + '/report', {method: 'POST', body: {reason: this.reason, note: this.note || null}});
                this.reported = true; this.reporting = false;
            } catch (e) { this.err = errText(e); }
            finally { this.sending = false; }
        },
    }));
});
</script>
@endsection
