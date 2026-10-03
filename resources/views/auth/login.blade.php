@extends('layouts.guest')

@section('title', __('common.auth.login') . ' | ' . config('app.name'))

@section('content')
<h1 class="mb-4 text-xl font-medium">{{ __('common.auth.login') }}</h1>
@if (session('status'))<div class="mb-4 rounded-lg bg-ok-soft p-3 text-sm text-ok" role="status">{{ session('status') }}</div>@endif
@if (session('status_error'))<div class="mb-4 rounded-lg bg-bad-soft p-3 text-sm text-bad" role="alert">{{ session('status_error') }}</div>@endif
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
    <div class="-mt-2 text-end text-sm"><a href="{{ route('password.request') }}">{{ __('security.forgot.link') }}</a></div>
    <label class="flex items-center gap-2 !mb-0"><input type="checkbox" name="remember" value="1" class="!w-auto !min-h-0"> {{ __('common.auth.remember') }}</label>
    <button class="btn btn-primary" type="submit">{{ __('common.auth.login') }}</button>
</form>
<p class="mt-4 text-center text-sm text-ink-2">{{ __('common.auth.no_account') }} <a href="{{ route('register') }}">{{ __('common.auth.register') }}</a></p>

@if (\App\Http\Controllers\Web\DemoLoginController::enabled())
    <section class="mt-6 border-t border-line pt-4" aria-labelledby="demo-title">
        <h2 id="demo-title" class="text-sm font-medium">{{ __('demo.title') }}</h2>
        <p class="mb-3 text-xs text-ink-3">{{ __('demo.hint') }}</p>
        <div class="grid gap-2 sm:grid-cols-3">
            @foreach (['admin', 'seller', 'buyer'] as $demoRole)
                <form method="POST" action="{{ route('demo-login', $demoRole) }}">@csrf
                    <button type="submit" class="btn btn-sm w-full">{{ __("demo.$demoRole") }}</button>
                </form>
            @endforeach
        </div>
    </section>
@endif
@endsection
