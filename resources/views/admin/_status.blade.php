<p x-show="error" x-cloak class="card sticky top-16 z-20 mb-3 border-bad text-bad lg:top-2" role="alert">
    <span x-text="error"></span>
    <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('admin.shared.retry') }}</button>
</p>
<p x-show="notice" x-cloak class="card sticky top-16 z-20 mb-3 border-ok text-ok lg:top-2" role="status" x-text="notice"></p>
<p x-show="loading" class="mb-3 text-ink-2" role="status">{{ __('common.loading') }}</p>
