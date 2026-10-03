@extends('layouts.guest')

@section('title', __('security.challenge.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="{ recovery: false }">
    <h1 class="mb-2 text-xl font-medium">{{ __('security.challenge.title') }}</h1>
    <p class="mb-4 text-sm text-ink-2" x-show="!recovery">{{ __('security.challenge.intro') }}</p>
    <p class="mb-4 text-sm text-ink-2" x-show="recovery" x-cloak>{{ __('security.challenge.recovery_intro') }}</p>

    <form method="POST" action="{{ url('/two-factor-challenge') }}" class="grid gap-4">@csrf
        <div x-show="!recovery">
            <label for="tf-code">{{ __('security.challenge.code') }}</label>
            <input id="tf-code" name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" maxlength="8" autocomplete="one-time-code" autofocus :disabled="recovery">
        </div>
        <div x-show="recovery" x-cloak>
            <label for="tf-recovery">{{ __('security.challenge.recovery_code') }}</label>
            <input id="tf-recovery" name="recovery_code" type="text" maxlength="32" autocomplete="off" :disabled="!recovery">
        </div>
        @error('code')<p class="text-sm text-bad" role="alert">{{ $message }}</p>@enderror
        @error('recovery_code')<p class="text-sm text-bad" role="alert">{{ $message }}</p>@enderror
        <button class="btn btn-primary" type="submit">{{ __('security.challenge.submit') }}</button>
    </form>
    <div class="mt-4 flex flex-col items-center gap-1 text-sm">
        <button type="button" class="link-tap bg-transparent text-accent underline" x-show="!recovery" @click="recovery = true">{{ __('security.challenge.use_recovery') }}</button>
        <button type="button" class="link-tap bg-transparent text-accent underline" x-show="recovery" x-cloak @click="recovery = false">{{ __('security.challenge.use_code') }}</button>
        <a href="{{ route('login') }}">{{ __('common.cancel') }}</a>
    </div>
</div>
@endsection
