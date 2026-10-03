<div x-show="loading" x-cloak class="card text-ink-2" role="status" x-text="t('common.loading')"></div>
<div x-show="error" x-cloak class="card flex flex-wrap items-center justify-between gap-3 bg-bad-soft text-bad" role="alert">
    <span x-text="error"></span>
    <button type="button" class="btn btn-sm" @click="load()" x-text="t('seller.shared.retry')"></button>
</div>
