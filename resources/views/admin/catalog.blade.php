@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.catalog.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminCatalog()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('admin.catalog.title') }}</h1>
    <form class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px]" @submit.prevent="reset()">
        <div><label for="q">{{ __('common.search') }}</label><input id="q" type="search" x-model="filters.q" maxlength="100"></div>
        <div><label for="st">{{ __('admin.catalog.status') }}</label>
            <select id="st" x-model="filters.status" @change="reset()">
                <option value="">{{ __('admin.shared.all') }}</option>
                @foreach (['draft', 'active', 'archived'] as $s)<option value="{{ $s }}">{{ __("admin.catalog.statuses.$s") }}</option>@endforeach
            </select></div>
    </form>
    @include('admin._status')

    <div class="table-wrap" x-show="!loading" x-cloak>
        <table>
            <thead><tr><th>{{ __('admin.catalog.product') }}</th><th>{{ __('admin.catalog.vendor') }}</th><th>{{ __('admin.catalog.price') }}</th><th>{{ __('admin.catalog.status') }}</th><th><span class="sr-only">{{ __('admin.shared.actions') }}</span></th></tr></thead>
            <tbody>
            <template x-for="p in items" :key="p.id">
                <tr>
                    <td class="min-w-40"><div class="min-w-0"><div class="font-medium" x-text="p.title"></div><div class="text-sm text-ink-3" x-show="p.moderation_reason" x-text="p.moderation_reason"></div></div></td>
                    <td x-text="p.vendor?.handle"></td>
                    <td class="whitespace-nowrap" x-text="money(p.price_cents, p.currency)"></td>
                    <td><span class="badge" :class="p.status === 'active' ? 'badge-ok' : (p.status === 'archived' ? 'badge-bad' : '')" x-text="t('admin.catalog.statuses.' + p.status)"></span></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-danger" x-show="p.status !== 'archived'" @click="archive(p)">{{ __('admin.catalog.archive') }}</button>
                        <button type="button" class="btn btn-sm" x-show="p.status === 'archived'" @click="restore(p)">{{ __('admin.catalog.restore') }}</button>
                    </td>
                </tr>
            </template>
            </tbody>
        </table>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('common.empty') }}</p>
    @include('admin._pager')

    <section class="card mt-8">
        <h2 class="mb-3 font-medium">{{ __('admin.catalog.categories') }}</h2>
        <form class="mb-3 flex gap-2" @submit.prevent="addCategory()">
            <div class="grow"><label class="sr-only" for="cn">{{ __('admin.catalog.category_name') }}</label>
                <input id="cn" x-model="newCategory" required maxlength="120" placeholder="{{ __('admin.catalog.category_name') }}"></div>
            <button class="btn btn-primary" type="submit">{{ __('admin.catalog.add') }}</button>
        </form>
        <p x-show="catError" x-cloak class="mb-3 rounded-lg border border-bad p-3 text-bad" role="alert" x-text="catError"></p>
        <p x-show="catNotice" x-cloak class="mb-3 rounded-lg border border-ok p-3 text-ok" role="status" x-text="catNotice"></p>
        <ul>
            <template x-for="c in categories" :key="c.id">
                <li class="flex items-center justify-between gap-3 border-b border-line py-2 last:border-0">
                    <span><span x-text="c.name"></span> <span class="text-sm text-ink-3" x-text="t('admin.catalog.products_count', {count: c.products_count})"></span></span>
                    <button type="button" class="btn btn-sm btn-danger" @click="removeCategory(c)">{{ __('admin.catalog.delete') }}</button>
                </li>
            </template>
        </ul>
    </section>
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminCatalog', () => ({
        ...adminList('admin/products', {q: '', status: ''}),
        categories: [], newCategory: '', catError: '', catNotice: '',
        init() { this.load(); this.loadCategories(); },
        async loadCategories() { try { this.categories = (await api('admin/categories')).data; } catch (e) { this.catError = errText(e); } },
        async archive(p) {
            const reason = await Alpine.store('dlg').ask({title: t('admin.catalog.archive'), label: t('admin.catalog.reason_prompt'), required: true});
            if (reason) this.act('admin/products/' + p.id + '/archive', {reason});
        },
        async restore(p) {
            const reason = await Alpine.store('dlg').ask({title: t('admin.catalog.restore'), label: t('admin.shared.reason'), required: true});
            if (reason) this.act('admin/products/' + p.id + '/restore', {reason});
        },
        async addCategory() {
            try {
                this.catNotice = '';
                const name = this.newCategory.trim();
                await api('admin/categories', {method: 'POST', body: {name, slug: name.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'category-' + Date.now()}});
                this.catError = ''; this.newCategory = ''; this.catNotice = t('admin.shared.done'); this.loadCategories();
            } catch (e) { this.catError = errText(e); }
        },
        async removeCategory(c) {
            if (await Alpine.store('dlg').ask({title: t('admin.catalog.confirm_delete')}) === null) return;
            try { this.catError = ''; this.catNotice = ''; await api('admin/categories/' + c.id, {method: 'DELETE'}); this.catNotice = t('admin.shared.done'); await this.loadCategories(); document.getElementById('cn')?.focus(); } catch (e) { this.catError = errText(e); }
        },
    }));
});
</script>
@endsection
