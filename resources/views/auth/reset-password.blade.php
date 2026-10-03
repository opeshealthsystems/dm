@extends('layouts.guest')

@section('title', __('security.reset.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="resetPassword(@js($token), @js($email))">
    <h1 class="mb-2 text-xl font-medium">{{ __('security.reset.title') }}</h1>

    <div x-show="done" x-cloak>
        <div class="mb-4 rounded-lg bg-ok-soft p-3 text-sm text-ok" role="status">{{ __('security.reset.done') }}</div>
        <a class="btn btn-primary w-full" href="{{ route('login') }}">{{ __('common.auth.login') }}</a>
    </div>

    <form x-show="!done" class="grid gap-4" @submit.prevent="submit()">
        <p class="text-sm text-ink-2">{{ __('security.reset.intro') }}</p>
        <div x-show="error" x-cloak class="rounded-lg bg-bad-soft p-3 text-sm text-bad" role="alert">
            <span x-text="error"></span>
            <a class="ms-1" href="{{ route('password.request') }}">{{ __('security.reset.request_new') }}</a>
        </div>
        <div>
            <label for="rp-email">{{ __('common.auth.email') }}</label>
            <input id="rp-email" type="email" x-model="email" required autocomplete="email">
        </div>
        <div>
            <label for="rp-pass">{{ __('security.reset.password') }}</label>
            <input id="rp-pass" type="password" x-model="password" required autocomplete="new-password">
            <p class="mt-1 text-xs text-ink-3">{{ __('common.auth.password_hint') }}</p>
        </div>
        <div>
            <label for="rp-conf">{{ __('security.reset.confirm') }}</label>
            <input id="rp-conf" type="password" x-model="confirmation" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary" type="submit" :disabled="busy">
            <span x-show="!busy">{{ __('security.reset.submit') }}</span>
            <span x-show="busy" x-cloak>{{ __('common.loading') }}</span>
        </button>
    </form>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('resetPassword', (token, email) => ({
    token, email, password: '', confirmation: '', busy: false, done: false, error: '',
    async submit() {
        if (this.busy) return;
        this.busy = true; this.error = '';
        try {
            await api('auth/reset-password', {method: 'POST', body: {
                token: this.token, email: this.email.trim(), password: this.password, password_confirmation: this.confirmation,
            }});
            this.done = true; this.password = ''; this.confirmation = '';
        } catch (e) { this.error = errText(e); }
        finally { this.busy = false; }
    },
})));
</script>
@endsection
