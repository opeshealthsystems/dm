@extends('layouts.base')

@section('body')
<div class="flex min-h-screen flex-col">
    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="link-tap me-auto text-xl font-semibold text-ink no-underline">{{ config('app.name') }}</a>
            <x-language-switcher />
            <div class="flex w-full gap-2 sm:w-auto [&>a]:flex-1 sm:[&>a]:flex-none">
                <a class="btn btn-sm" href="{{ route('community.index') }}">{{ __('community.link') }}</a>
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
    <footer class="border-t border-line bg-surface">
        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-4 py-8 sm:grid-cols-3 sm:px-6">
            <div>
                <p class="font-semibold text-ink">{{ config('app.name') }}</p>
                <p class="mt-1 max-w-xs text-sm text-ink-2">{{ __('pages.footer.tagline') }}</p>
            </div>
            <nav aria-labelledby="footer-company">
                <h2 id="footer-company" class="mb-1 text-sm font-semibold text-ink">{{ __('pages.footer.company') }}</h2>
                <ul class="flex flex-col">
                    <li><a class="link-tap text-sm" href="{{ route('pages.about') }}">{{ __('pages.about.nav') }}</a></li>
                    <li><a class="link-tap text-sm" href="{{ route('pages.how') }}">{{ __('pages.how.nav') }}</a></li>
                    <li><a class="link-tap text-sm" href="{{ route('community.index') }}">{{ __('community.link') }}</a></li>
                    <li><a class="link-tap text-sm" href="{{ route('pages.sellers') }}">{{ __('pages.sellers.nav') }}</a></li>
                    <li><a class="link-tap text-sm" href="{{ route('pages.contact') }}">{{ __('pages.contact.nav') }}</a></li>
                </ul>
            </nav>
            <nav aria-labelledby="footer-help">
                <h2 id="footer-help" class="mb-1 text-sm font-semibold text-ink">{{ __('pages.footer.help') }}</h2>
                <ul class="flex flex-col">
                    <li><a class="link-tap text-sm" href="{{ route('pages.faq') }}">{{ __('pages.faq.nav') }}</a></li>
                    <li><a class="link-tap text-sm" href="{{ route('pages.terms') }}">{{ __('pages.terms.nav') }}</a></li>
                    <li><a class="link-tap text-sm" href="{{ route('pages.privacy') }}">{{ __('pages.privacy.nav') }}</a></li>
                    <li><a class="link-tap text-sm" href="{{ url('/docs/api') }}">{{ __('pages.footer.api_docs') }}</a></li>
                </ul>
            </nav>
        </div>
        <div class="border-t border-line px-4 py-4 text-center text-sm text-ink-3">
            <p>{{ __('buyer.shared.footer') }}</p>
            @auth<p><a class="link-tap text-sm" href="{{ route('account.security') }}">{{ __('security.page.title') }}</a></p>@endauth
            <p>{{ __('pages.footer.copyright', ['year' => date('Y'), 'name' => config('app.name')]) }}</p>
        </div>
    </footer>
</div>
<style>[x-cloak]{display:none !important}</style>
@endsection
