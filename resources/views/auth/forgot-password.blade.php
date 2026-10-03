@extends('layouts.guest')

@section('title', __('security.forgot.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="forgotPassword()">
    <h1 class="mb-2 text-xl font-medium">{{ __('security.forgot.title') }}</h1>
    <p class="mb-4 text-sm text-ink-2">{{ __('security.forgot.intro') }}</p>

    <div x-show="done" x-cloak class="mb-4 rounded-lg bg-ok-soft p-3 text-sm text-ok" role="status">{{ __('security.forgot.sent') }}</div>
    <div x-show="error" x-cloak class="mb-4 rounded-lg bg-bad-soft p-3 text-sm text-bad" role="alert" x-text="error"></div>

    <form class="grid gap-4" @submit.prevent="submit()">
        <div>
            <label for="fp-email">{{ __('common.auth.email') }}</label>
            <input id="fp-email" type="email" x-model="email" required autocomplete="email" autofocus>
        </div>
        <button class="btn btn-primary" type="submit" :disabled="busy">
            <span x-show="!busy">{{ __('security.forgot.submit') }}</span>
            <span x-show="busy" x-cloak>{{ __('common.loading') }}</span>
        </button>
    </form>
    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}">{{ __('security.forgot.back') }}</a></p>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('forgotPassword', () => ({
    email: '', busy: false, done: false, error: '',
    async submit() {
        if (this.busy) return;
        this.busy = true; this.error = ''; this.done = false;
        try {
            await api('auth/forgot-password', {method: 'POST', body: {email: this.email.trim()}});
            this.done = true;
        } catch (e) { this.error = errText(e); }
        finally { this.busy = false; }
    },
})));
</script>
@endsection
