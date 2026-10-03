@extends('layouts.base')

@section('body')
<div class="flex min-h-screen flex-col items-center justify-center p-4">
    <div class="mb-4 self-end"><x-language-switcher /></div>
    <div class="w-full max-w-md">
        <a href="{{ route('home') }}" class="mb-6 block text-center text-2xl font-medium text-ink no-underline">{{ config('app.name') }}</a>
        <div class="card">@yield('content')</div>
    </div>
</div>
@endsection
