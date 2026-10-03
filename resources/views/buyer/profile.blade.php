@extends('layouts.dashboard')

@section('title', __('buyer.profile.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="buyerProfile()" x-init="load()" class="max-w-xl">
    <h1 class="mb-4 text-2xl font-medium">{{ __('buyer.profile.title') }}</h1>
    <p class="mb-4"><a class="btn btn-sm" href="{{ route('account.security') }}">{{ __('security.page.title') }}</a></p>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('buyer.shared.retry') }}</button>
    </div>

    <div x-show="loaded" x-cloak class="flex flex-col gap-4">
        <form class="card flex flex-col gap-3" @submit.prevent="saveProfile()">
            <h2 class="text-lg font-medium">{{ __('buyer.profile.details') }}</h2>
            <div>
                <label for="p-email">{{ __('buyer.profile.email') }}</label>
                <input id="p-email" type="email" :value="email" disabled>
            </div>
            <div>
                <label for="p-name">{{ __('buyer.profile.name') }}</label>
                <input id="p-name" type="text" x-model="form.name" maxlength="255" required autocomplete="name">
                <p class="mt-1 text-sm text-bad" x-show="pErrors.name" x-cloak x-text="pErrors.name?.[0]"></p>
            </div>
            <div>
                <label for="p-handle">{{ __('buyer.profile.handle') }}</label>
                <input id="p-handle" type="text" x-model="form.handle" maxlength="64" autocomplete="username">
                <p class="mt-1 text-xs text-ink-3">{{ __('buyer.profile.handle_help') }}</p>
                <p class="mt-1 text-sm text-bad" x-show="pErrors.handle" x-cloak x-text="pErrors.handle?.[0]"></p>
            </div>
            <p class="text-sm text-bad" x-show="pError" x-cloak role="alert" x-text="pError"></p>
            <p class="text-sm text-ok" x-show="pOk" x-cloak role="status">{{ __('buyer.profile.saved') }}</p>
            <button type="submit" class="btn btn-primary self-start" :disabled="pBusy">{{ __('common.save') }}</button>
        </form>

        <form class="card flex flex-col gap-3" @submit.prevent="savePassword()">
            <h2 class="text-lg font-medium">{{ __('buyer.profile.password') }}</h2>
            <div>
                <label for="pw-cur">{{ __('buyer.profile.current_password') }}</label>
                <input id="pw-cur" type="password" x-model="pw.current_password" required autocomplete="current-password">
                <p class="mt-1 text-sm text-bad" x-show="wErrors.current_password" x-cloak x-text="wErrors.current_password?.[0]"></p>
            </div>
            <div>
                <label for="pw-new">{{ __('buyer.profile.new_password') }}</label>
                <input id="pw-new" type="password" x-model="pw.password" required autocomplete="new-password">
                <p class="mt-1 text-xs text-ink-3">{{ __('common.auth.password_hint') }}</p>
                <p class="mt-1 text-sm text-bad" x-show="wErrors.password" x-cloak x-text="wErrors.password?.[0]"></p>
            </div>
            <div>
                <label for="pw-conf">{{ __('buyer.profile.confirm_password') }}</label>
                <input id="pw-conf" type="password" x-model="pw.password_confirmation" required autocomplete="new-password">
            </div>
            <p class="text-sm text-bad" x-show="wError" x-cloak role="alert" x-text="wError"></p>
            <p class="text-sm text-ok" x-show="wOk" x-cloak role="status">{{ __('buyer.profile.password_changed') }}</p>
            <button type="submit" class="btn btn-primary self-start" :disabled="wBusy">{{ __('buyer.profile.change_password') }}</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('buyerProfile', () => ({
        email: '', form: {name: '', handle: ''}, loading: false, loaded: false, error: '',
        pBusy: false, pError: '', pOk: false, pErrors: {},
        pw: {current_password: '', password: '', password_confirmation: ''},
        wBusy: false, wError: '', wOk: false, wErrors: {},
        async load() {
            this.loading = true; this.error = '';
            try {
                const u = (await api('auth/me')).data;
                this.email = u.email; this.form = {name: u.name, handle: u.handle ?? ''};
                this.loaded = true;
            } catch (e) { this.error = errText(e); }
            finally { this.loading = false; }
        },
        async saveProfile() {
            if (this.pBusy) return;
            this.pBusy = true; this.pError = ''; this.pOk = false; this.pErrors = {};
            try {
                const u = (await api('auth/me', {method: 'PUT', body: {name: this.form.name.trim(), handle: this.form.handle.trim() || null}})).data;
                this.form = {name: u.name, handle: u.handle ?? ''};
                this.pOk = true;
            } catch (e) { this.pErrors = e.errors ?? {}; this.pError = errText(e); }
            finally { this.pBusy = false; }
        },
        async savePassword() {
            if (this.wBusy) return;
            this.wBusy = true; this.wError = ''; this.wOk = false; this.wErrors = {};
            try {
                await api('auth/password', {method: 'POST', body: this.pw});
                this.pw = {current_password: '', password: '', password_confirmation: ''};
                this.wOk = true;
            } catch (e) { this.wErrors = e.errors ?? {}; this.wError = errText(e); }
            finally { this.wBusy = false; }
        },
    }));
});
</script>
@endsection
