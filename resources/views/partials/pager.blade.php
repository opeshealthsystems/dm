{{-- Alpine pager. Defaults: page, lastPage, loading, go(n); override with include variables. --}}
@php
    $pg = $pg ?? 'page'; $last = $last ?? 'lastPage'; $ld = $ld ?? 'loading'; $goFn = $goFn ?? 'go';
@endphp
<nav x-show="{{ $last }} > 1" x-cloak class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('buyer.shared.pagination') }}">
    <button type="button" class="btn btn-sm" :disabled="{{ $pg }} <= 1 || {{ $ld }}" @click="{{ $goFn }}({{ $pg }} - 1)">{{ __('buyer.shared.prev') }}</button>
    <span class="text-sm text-ink-2" x-text="t('buyer.shared.page_of', {page: {{ $pg }}, last: {{ $last }}})"></span>
    <button type="button" class="btn btn-sm" :disabled="{{ $pg }} >= {{ $last }} || {{ $ld }}" @click="{{ $goFn }}({{ $pg }} + 1)">{{ __('buyer.shared.next') }}</button>
</nav>
