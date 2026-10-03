@extends('layouts.guest')

@section('title', __('common.auth.login') . ' | ' . config('app.name'))

@section('content')
<h1 class="mb-4 text-xl font-medium">{{ __('common.auth.login') }}</h1>
<form method="POST" action="{{ url('/login') }}" class="grid gap-4">@csrf
    <div>
        <label for="email">{{ __('common.auth.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        @error('email')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password">{{ __('common.auth.password') }}</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
    </div>
    <label class="flex items-center gap-2 !mb-0"><input type="checkbox" name="remember" value="1" class="!w-auto !min-h-0"> {{ __('common.auth.remember') }}</label>
    <button class="btn btn-primary" type="submit">{{ __('common.auth.login') }}</button>
</form>
<p class="mt-4 text-center text-sm text-ink-2">{{ __('common.auth.no_account') }} <a href="{{ route('register') }}">{{ __('common.auth.register') }}</a></p>
@endsection
