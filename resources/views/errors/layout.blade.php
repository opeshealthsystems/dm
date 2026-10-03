@extends('layouts.base')

@section('robots', 'noindex,nofollow')
@section('title', __("pages.errors.$code.title") . ' | ' . config('app.name'))
@section('description', __("pages.errors.$code.message"))

@section('body')
<main class="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
    <a href="{{ url('/') }}" class="link-tap text-xl font-semibold text-ink no-underline">{{ config('app.name') }}</a>
    <div class="card w-full max-w-md">
        <p class="mb-1 text-sm text-ink-3">{{ __('pages.errors.code', ['code' => $code]) }}</p>
        <h1 class="mb-2">{{ __("pages.errors.$code.title") }}</h1>
        <p class="mb-4 text-ink-2">{{ __("pages.errors.$code.message") }}</p>
        <a class="btn btn-primary" href="{{ url('/') }}">{{ __('pages.errors.home') }}</a>
    </div>
</main>
@endsection
