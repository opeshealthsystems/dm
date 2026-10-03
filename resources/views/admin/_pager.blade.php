<nav class="mt-4 flex items-center justify-between gap-2" x-show="meta && meta.last_page > 1" x-cloak aria-label="{{ __('admin.shared.pagination') }}">
    <button type="button" class="btn btn-sm" :disabled="page <= 1" @click="go(page - 1)">{{ __('admin.shared.prev') }}</button>
    <span class="text-sm text-ink-2" x-text="t('admin.shared.page', {page: page, last: meta?.last_page ?? 1})"></span>
    <button type="button" class="btn btn-sm" :disabled="meta && page >= meta.last_page" @click="go(page + 1)">{{ __('admin.shared.next') }}</button>
</nav>
