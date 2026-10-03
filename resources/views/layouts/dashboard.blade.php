@extends('layouts.base')

@section('robots', 'noindex,nofollow')

@section('body')
@php($items = config("navigation.$area"))
<div x-data="{ open: false, desk: window.matchMedia('(min-width: 1024px)').matches }"
     x-init="const mq = window.matchMedia('(min-width: 1024px)'); mq.addEventListener('change', e => { desk = e.matches; open = false })"
     @keydown.escape.window="if (open) { open = false; $refs.menuBtn.focus() }"
     class="min-h-screen lg:grid lg:grid-cols-[250px_minmax(0,1fr)]">

    <header class="sticky top-0 z-30 flex items-center justify-between gap-2 border-b border-line bg-surface px-4 py-2 lg:hidden">
        <button type="button" class="btn btn-sm" x-ref="menuBtn" @click="open = !open; if (open) $nextTick(() => $refs.sidebar.querySelector('a')?.focus())" :aria-expanded="open" aria-controls="sidebar">
            {{ __('common.menu') }}
        </button>
        <span class="font-medium">{{ config('app.name') }}</span>
        <x-language-switcher id="lang-select-top" />
    </header>

    <div x-show="open" x-cloak class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="open = false"></div>

    <aside id="sidebar" x-ref="sidebar"
           class="fixed inset-y-0 start-0 z-40 w-[250px] -translate-x-full overflow-y-auto border-e border-line bg-surface p-3 transition-transform rtl:translate-x-full lg:static lg:translate-x-0 lg:rtl:translate-x-0"
           :class="open ? '!translate-x-0' : ''" :inert="!open && !desk">
        <div class="px-3 pb-4 pt-2">
            <div class="text-lg font-medium">{{ config('app.name') }}</div>
            <div class="text-sm text-ink-3">{{ __("common.area.$area") }}</div>
        </div>
        <nav class="flex flex-col gap-1" aria-label="{{ __('common.menu') }}">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}" class="nav-link"
                   @if (request()->routeIs($item['route'] . '*')) aria-current="page" @endif>{{ __('common.' . $item['label']) }}</a>
            @endforeach
        </nav>
        <div class="mt-6 border-t border-line px-3 pt-4">
            <div class="mb-3 hidden lg:block"><x-language-switcher /></div>
            <div class="mb-2 truncate text-sm text-ink-2">{{ auth()->user()->name }}</div>
            @if ($area !== 'buyer')
                <a class="link-tap mb-1 text-sm" href="{{ route('buyer.orders') }}">{{ __('common.area.buyer') }}</a>
            @endif
            <a class="link-tap mb-1 text-sm" href="{{ route('account.security') }}">{{ __('security.page.title') }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn btn-sm w-full" type="submit">{{ __('common.logout') }}</button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-5xl">
            @include('partials.account-banners')
            @yield('content')
        </div>
    </main>
</div>
<style>[x-cloak]{display:none !important}</style>
@endsection
