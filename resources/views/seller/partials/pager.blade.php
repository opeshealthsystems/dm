<div x-show="meta.last_page > 1" x-cloak class="mt-4 flex items-center justify-between gap-2">
    <button type="button" class="btn" :disabled="loading || meta.current_page <= 1" @click="go(meta.current_page - 1)" x-text="t('seller.shared.prev')"></button>
    <span class="text-sm text-ink-2" x-text="t('seller.shared.page_of', { page: meta.current_page, total: meta.last_page })"></span>
    <button type="button" class="btn" :disabled="loading || meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)" x-text="t('seller.shared.next')"></button>
</div>
