@extends('layouts.base')

@section('body')
<div class="flex min-h-screen flex-col">
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="link-tap me-auto text-xl font-semibold text-ink no-underline">{{ config('app.name') }}</a>
            <x-language-switcher />
            <div class="flex w-full gap-2 sm:w-auto [&>a]:flex-1 sm:[&>a]:flex-none">
                <a class="btn btn-sm" href="{{ route('buyer.cart') }}">{{ __('common.nav.cart') }}</a>
                @auth
                    <a class="btn btn-primary btn-sm" href="{{ route('dashboard') }}">{{ __('common.my_account') }}</a>
                @else
                    <a class="btn btn-sm" href="{{ route('login') }}">{{ __('common.auth.login') }}</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('register') }}">{{ __('common.auth.register') }}</a>
                @endauth
            </div>
        </div>
    </header>
    <main class="mx-auto w-full max-w-6xl min-w-0 flex-1 px-4 py-6 sm:px-6">
        @yield('content')
    </main>
    <footer class="border-t border-line px-4 py-4 text-center text-sm text-ink-3">{{ __('buyer.shared.footer') }}</footer>
</div>
<style>[x-cloak]{display:none !important}</style>
@endsection
