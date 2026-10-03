{{-- Shared admin helpers: an accessible ask-for-text dialog and a paginated list component. --}}
<dialog x-data x-ref="dlg" id="ask-dialog" class="m-auto w-[min(92vw,26rem)] rounded-xl border border-line bg-surface p-5 text-ink backdrop:bg-black/40"
        @close="$store.dlg.finish(null)">
    <form method="dialog" @submit.prevent="$store.dlg.finish($store.dlg.value)" class="grid gap-3">
        <h2 class="text-lg font-medium" x-text="$store.dlg.title"></h2>
        <template x-if="$store.dlg.label">
            <div>
                <label for="ask-input" x-text="$store.dlg.label"></label>
                <input id="ask-input" x-model="$store.dlg.value" :required="$store.dlg.required" maxlength="500" autocomplete="off">
            </div>
        </template>
        <div class="flex justify-end gap-2">
            <button type="button" class="btn" @click="$store.dlg.finish(null)">{{ __('common.cancel') }}</button>
            <button type="submit" class="btn btn-primary">{{ __('common.confirm_ok') }}</button>
        </div>
    </form>
</dialog>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('dlg', {
        title: '', label: '', value: '', required: false, resolve: null,
        ask({ title, label = '', required = false }) {
            this.title = title; this.label = label; this.required = required; this.value = '';
            const d = document.getElementById('ask-dialog');
            d.showModal();
            Alpine.nextTick(() => document.getElementById('ask-input')?.focus());
            return new Promise(r => { this.resolve = r; });
        },
        finish(v) {
            const d = document.getElementById('ask-dialog');
            if (d.open) d.close();
            if (this.resolve) { const r = this.resolve; this.resolve = null; r(v); }
        },
    });

    /** Paginated list: Alpine.data('adminList', () => adminList('users', {search: ''})) */
    window.adminList = (endpoint, filters = {}) => ({
        items: [], meta: null, page: 1, loading: true, error: '', notice: '', filters,
        init() { this.load(); },
        async load() {
            this.loading = true; this.error = '';
            try {
                const r = await api(endpoint, { query: { ...this.filters, page: this.page, per_page: 20 } });
                this.items = r.data; this.meta = r.meta ?? null;
            } catch (e) { this.error = errText(e); } finally { this.loading = false; }
        },
        go(n) { this.page = n; this.notice = ''; this.load(); },
        reset() { this.page = 1; this.notice = ''; this.load(); },
        /** Run a mutation, then reload; shows errors inline. */
        async act(path, body = null, method = 'POST') {
            this.error = ''; this.notice = '';
            try { await api(path, { method, body }); this.notice = t('admin.shared.done'); await this.load(); }
            catch (e) { this.error = errText(e); }
        },
    });
});
</script>
<style>[x-cloak]{display:none !important}</style>
