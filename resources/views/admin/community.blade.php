@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('community.admin.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
@include('community._i18n')
<div x-data="adminCommunity()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('community.admin.title') }}</h1>

    <div class="mb-4 flex gap-2" role="tablist">
        <button type="button" class="btn btn-sm" role="tab" :class="tab === 'reports' ? 'btn-primary' : ''" :aria-selected="tab === 'reports'" @click="tab = 'reports'">{{ __('community.admin.reports') }}</button>
        <button type="button" class="btn btn-sm" role="tab" :class="tab === 'categories' ? 'btn-primary' : ''" :aria-selected="tab === 'categories'" @click="tab = 'categories'; loadCategories()">{{ __('community.admin.categories') }}</button>
    </div>

    <section x-show="tab === 'reports'">
        <div class="mb-4 max-w-xs"><label for="cr-status">{{ __('community.admin.status') }}</label>
            <select id="cr-status" x-model="reports.filters.status" @change="reports.reset()">
                <option value="open">{{ __('community.admin.status_open') }}</option>
                <option value="dismissed">{{ __('community.admin.status_dismissed') }}</option>
                <option value="actioned">{{ __('community.admin.status_actioned') }}</option>
                <option value="all">{{ __('community.admin.status_all') }}</option>
            </select></div>

        <p x-show="reports.error" x-cloak class="card mb-3 border-bad text-bad" role="alert">
            <span x-text="reports.error"></span>
            <button type="button" class="btn btn-sm ms-2" @click="reports.load()">{{ __('admin.shared.retry') }}</button>
        </p>
        <p x-show="reports.notice" x-cloak class="card mb-3 border-ok text-ok" role="status" x-text="reports.notice"></p>
        <p x-show="reports.loading" class="mb-3 text-ink-2" role="status">{{ __('common.loading') }}</p>

        <div class="grid gap-3" x-show="!reports.loading" x-cloak>
            <template x-for="r in reports.items" :key="r.id">
                <article class="card">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium" x-text="t('community.report.reasons.' + r.reason)"></span>
                        <span class="badge" :class="r.status === 'open' ? 'badge-warn' : (r.status === 'actioned' ? 'badge-bad' : 'badge-ok')" x-text="t('community.admin.status_' + r.status)"></span>
                    </div>
                    <p class="mt-1 text-sm text-ink-3" x-text="t('community.admin.reporter', {name: cHandle(r.reporter)}) + (r.post ? ' · ' + t('community.admin.post_by', {name: r.post.author_handle ?? ''}) : '')"></p>
                    <p class="mt-2 whitespace-pre-line text-ink-2" x-show="r.note" x-text="r.note"></p>
                    <blockquote class="mt-2 border-s-2 border-line ps-3 text-sm text-ink-2" x-show="r.post">
                        <span class="whitespace-pre-line" x-text="r.post?.excerpt"></span>
                        <span class="badge badge-bad ms-1" x-show="r.post?.is_deleted">{{ __('community.admin.post_removed') }}</span>
                    </blockquote>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a class="btn btn-sm" x-show="r.post?.thread_path" :href="r.post?.thread_path + '#post-' + r.post?.id">{{ __('community.admin.view_thread') }}</a>
                        <template x-if="r.status === 'open'">
                            <span class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm" @click="resolve(r, 'dismiss')">{{ __('community.admin.dismiss') }}</button>
                                <button type="button" class="btn btn-sm btn-danger" @click="resolve(r, 'remove_post')">{{ __('community.admin.remove_post') }}</button>
                            </span>
                        </template>
                    </div>
                </article>
            </template>
        </div>
        <p x-show="!reports.loading && !reports.error && reports.items.length === 0" x-cloak class="card text-ink-2">{{ __('community.admin.no_reports') }}</p>
        <nav x-show="(reports.meta?.last_page ?? 1) > 1" x-cloak class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('community.pagination') }}">
            <button type="button" class="btn btn-sm" :disabled="reports.page <= 1 || reports.loading" @click="reports.go(reports.page - 1)">{{ __('community.prev') }}</button>
            <span class="text-sm text-ink-2" x-text="t('community.page_of', {page: reports.page, last: reports.meta?.last_page ?? 1})"></span>
            <button type="button" class="btn btn-sm" :disabled="reports.page >= (reports.meta?.last_page ?? 1) || reports.loading" @click="reports.go(reports.page + 1)">{{ __('community.next') }}</button>
        </nav>
    </section>

    <section x-show="tab === 'categories'" x-cloak>
        <p x-show="catError" x-cloak class="card mb-3 border-bad text-bad" role="alert" x-text="catError"></p>
        <p x-show="catNotice" x-cloak class="card mb-3 border-ok text-ok" role="status" x-text="catNotice"></p>
        <p x-show="catLoading" class="mb-3 text-ink-2" role="status">{{ __('common.loading') }}</p>

        <div class="grid gap-3">
            <template x-for="c in categories" :key="c.id">
                <form class="card grid gap-3 sm:grid-cols-2" @submit.prevent="saveCategory(c)">
                    <div><label :for="'cn-' + c.id">{{ __('community.admin.name') }}</label><input :id="'cn-' + c.id" x-model="c.edit.name" maxlength="80" required></div>
                    <div><label :for="'cs-' + c.id">{{ __('community.admin.slug') }}</label><input :id="'cs-' + c.id" x-model="c.edit.slug" maxlength="64" required></div>
                    <div class="sm:col-span-2"><label :for="'cd-' + c.id">{{ __('community.admin.description') }}</label><input :id="'cd-' + c.id" x-model="c.edit.description" maxlength="255"></div>
                    <div><label :for="'cp-' + c.id">{{ __('community.admin.position') }}</label><input :id="'cp-' + c.id" type="number" min="0" x-model.number="c.edit.position"></div>
                    <div class="flex items-end gap-2"><input :id="'co-' + c.id" type="checkbox" class="!w-auto !min-h-0" x-model="c.edit.staff_only"><label class="!mb-0" :for="'co-' + c.id">{{ __('community.admin.staff_only') }}</label></div>
                    <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('community.admin.save_category') }}</button>
                        <button type="button" class="btn btn-sm btn-danger" @click="removeCategory(c)">{{ __('community.admin.delete_category') }}</button>
                        <span class="text-sm text-ink-3" x-text="t('community.admin.threads_count', {count: c.threads_count ?? 0})"></span>
                    </div>
                </form>
            </template>

            <form class="card grid gap-3 sm:grid-cols-2" @submit.prevent="addCategory()">
                <h2 class="text-lg font-medium sm:col-span-2">{{ __('community.admin.add_category') }}</h2>
                <div><label for="nc-name">{{ __('community.admin.name') }}</label><input id="nc-name" x-model="fresh.name" maxlength="80" required></div>
                <div><label for="nc-slug">{{ __('community.admin.slug') }}</label><input id="nc-slug" x-model="fresh.slug" maxlength="64"></div>
                <div class="sm:col-span-2"><label for="nc-desc">{{ __('community.admin.description') }}</label><input id="nc-desc" x-model="fresh.description" maxlength="255"></div>
                <div class="flex items-end gap-2"><input id="nc-staff" type="checkbox" class="!w-auto !min-h-0" x-model="fresh.staff_only"><label class="!mb-0" for="nc-staff">{{ __('community.admin.staff_only') }}</label></div>
                <div class="sm:col-span-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('community.admin.add_category') }}</button></div>
            </form>
        </div>
    </section>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminCommunity', () => ({
        tab: 'reports',
        reports: adminList('community/admin/reports', {status: 'open'}),
        categories: [], catLoading: false, catError: '', catNotice: '',
        fresh: {name: '', slug: '', description: '', staff_only: false},
        init() { this.reports.load(); },
        async resolve(r, action) {
            if (action === 'remove_post' && !window.confirm(t('community.thread.confirm_delete'))) return;
            await this.reports.act('community/admin/reports/' + r.id + '/resolve', {action});
        },
        async loadCategories() {
            this.catLoading = true; this.catError = '';
            try {
                const rows = (await api('community/admin/categories')).data;
                // Show the translated name for built-in categories; saving replaces it with the typed text.
                this.categories = rows.map(c => {
                    const edit = {name: cName(c), slug: c.slug, description: cDesc(c) ?? '', position: c.position, staff_only: c.staff_only};
                    return {...c, edit, orig: {...edit}};
                });
            } catch (e) { this.catError = errText(e); }
            finally { this.catLoading = false; }
        },
        async saveCategory(c) {
            this.catError = ''; this.catNotice = '';
            // Send only what changed, so an untouched built-in name keeps its translations.
            const body = Object.fromEntries(Object.entries(c.edit).filter(([k, v]) => v !== c.orig[k]));
            try { await api('community/admin/categories/' + c.id, {method: 'PUT', body}); this.catNotice = t('community.admin.done'); await this.loadCategories(); }
            catch (e) { this.catError = errText(e); }
        },
        async removeCategory(c) {
            if (!window.confirm(t('community.admin.confirm_delete_category'))) return;
            this.catError = ''; this.catNotice = '';
            try { await api('community/admin/categories/' + c.id, {method: 'DELETE'}); this.catNotice = t('community.admin.done'); await this.loadCategories(); }
            catch (e) { this.catError = errText(e); }
        },
        async addCategory() {
            this.catError = ''; this.catNotice = '';
            const body = {name: this.fresh.name, description: this.fresh.description || null, staff_only: this.fresh.staff_only};
            if (this.fresh.slug) body.slug = this.fresh.slug;
            try {
                await api('community/admin/categories', {method: 'POST', body});
                this.fresh = {name: '', slug: '', description: '', staff_only: false};
                this.catNotice = t('community.admin.done'); await this.loadCategories();
            } catch (e) { this.catError = errText(e); }
        },
    }));
});
</script>
@endsection
