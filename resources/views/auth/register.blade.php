@extends('layouts.guest')

@section('title', __('common.auth.register') . ' | ' . config('app.name'))

@section('content')
<h1 class="mb-4 text-xl font-medium">{{ __('common.auth.register') }}</h1>
<form method="POST" action="{{ url('/register') }}" class="grid gap-4" x-data="{ role: '{{ old('role', 'buyer') }}' }">@csrf
    <div>
        <label for="name">{{ __('common.auth.name') }}</label>
        <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
        @error('name')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="email">{{ __('common.auth.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        @error('email')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password">{{ __('common.auth.password') }}</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <p class="mt-1 text-xs text-ink-3">{{ __('common.auth.password_hint') }}</p>
        @error('password')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="role">{{ __('common.auth.account_type') }}</label>
        <select id="role" name="role" x-model="role">
            <option value="buyer">{{ __('common.auth.buyer') }}</option>
            <option value="vendor">{{ __('common.auth.seller') }}</option>
        </select>
    </div>
    <div x-show="role === 'vendor'" x-cloak>
        <label for="shop_name">{{ __('common.auth.shop_name') }}</label>
        <input id="shop_name" name="shop_name" value="{{ old('shop_name') }}">
        @error('shop_name')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
    </div>
    <button class="btn btn-primary" type="submit">{{ __('common.auth.register') }}</button>
</form>
<p class="mt-4 text-center text-sm text-ink-2">{{ __('common.auth.have_account') }} <a href="{{ route('login') }}">{{ __('common.auth.login') }}</a></p>
<style>[x-cloak]{display:none !important}</style>
@endsection
