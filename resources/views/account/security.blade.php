@extends('layouts.dashboard')

@section('title', __('security.page.title') . ' | ' . config('app.name'))

@section('content')
<div x-data="securityPage()" x-init="load()" class="max-w-xl">
    <h1 class="mb-4 text-2xl font-medium">{{ __('security.page.title') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ok-soft p-3 text-sm text-ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('status_error'))
        <div class="mb-4 rounded-lg bg-bad-soft p-3 text-sm text-bad" role="alert">{{ session('status_error') }}</div>
    @endif

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card mb-4 border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="load()">{{ __('security.page.retry') }}</button>
    </div>

    <div x-show="loaded" x-cloak class="flex flex-col gap-4">
        {{-- E-mail verification --}}
        <section class="card flex flex-col gap-2">
            <h2 class="text-lg font-medium">{{ __('security.page.email_title') }}</h2>
            <p dir="ltr" class="text-sm text-ink-2 break-all rtl:text-end" x-text="me.email"></p>
            <p x-show="me.email_verified"><span class="badge badge-ok">{{ __('security.page.verified') }}</span></p>
            <div x-show="!me.email_verified" class="flex flex-col gap-2">
                <p><span class="badge badge-warn">{{ __('security.page.unverified') }}</span></p>
                <p class="text-sm text-ink-2">{{ __('security.page.unverified_help') }}</p>
                <button type="button" class="btn btn-sm self-start" :disabled="rBusy" @click="resend()">{{ __('security.verify.resend') }}</button>
                <p class="text-sm text-ok" x-show="rOk" x-cloak role="status">{{ __('security.verify.sent') }}</p>
                <p class="text-sm text-bad" x-show="rError" x-cloak role="alert" x-text="rError"></p>
            </div>
        </section>

        {{-- Two-factor authentication --}}
        <section class="card flex flex-col gap-3">
            <h2 class="text-lg font-medium">{{ __('security.twofa.title') }}</h2>
            <p class="text-sm text-ink-2">{{ __('security.twofa.intro') }}</p>
            @if (auth()->user()->isAdmin())
                <p class="rounded-lg bg-warn-soft p-3 text-sm text-warn" x-show="!tfa.enabled" x-cloak>{{ __('security.twofa.admin_nudge') }}</p>
            @endif

            {{-- Recovery codes (shown once) --}}
            <div x-show="codes.length" x-cloak class="rounded-lg border border-line p-3" role="status">
                <h3 class="font-medium">{{ __('security.twofa.codes_title') }}</h3>
                <p class="mb-2 text-sm text-ink-2">{{ __('security.twofa.codes_help') }}</p>
                <ul class="mb-3 grid grid-cols-2 gap-1 font-mono text-sm" dir="ltr">
                    <template x-for="c in codes" :key="c"><li x-text="c"></li></template>
                </ul>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm" @click="copy(codes.join('\n'))">{{ __('security.twofa.copy') }}</button>
                    <button type="button" class="btn btn-sm btn-primary" @click="codes = []">{{ __('security.twofa.saved') }}</button>
                </div>
                <p class="mt-2 text-sm text-ok" x-show="copied" x-cloak role="status">{{ __('security.twofa.copied') }}</p>
            </div>

            {{-- Disabled: setup --}}
            <div x-show="!tfa.enabled" x-cloak class="flex flex-col gap-3">
                <button type="button" class="btn btn-primary self-start" x-show="!setup" :disabled="sBusy" @click="startSetup()">{{ __('security.twofa.enable') }}</button>
                <div x-show="setup" x-cloak class="flex flex-col gap-3">
                    <p class="text-sm text-ink-2">{{ __('security.twofa.setup_help') }}</p>
                    <div>
                        <label>{{ __('security.twofa.secret') }}</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <code class="break-all rounded-lg bg-surface-2 px-3 py-2 font-mono text-sm" dir="ltr" x-text="setup ? setup.secret.replace(/(.{4})/g, '$1 ').trim() : ''"></code>
                            <button type="button" class="btn btn-sm" @click="copy(setup.secret)">{{ __('security.twofa.copy') }}</button>
                        </div>
                    </div>
                    <div>
                        <label>{{ __('security.twofa.uri') }}</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <code class="break-all rounded-lg bg-surface-2 px-3 py-2 font-mono text-xs" dir="ltr" x-text="setup ? setup.otpauth_uri : ''"></code>
                            <button type="button" class="btn btn-sm" @click="copy(setup.otpauth_uri)">{{ __('security.twofa.copy') }}</button>
                        </div>
                        <p class="mt-1 text-xs text-ink-3">{{ __('security.twofa.uri_help') }}</p>
                    </div>
                    <form class="flex flex-col gap-2" @submit.prevent="confirm()">
                        <label for="tf-confirm">{{ __('security.twofa.code') }}</label>
                        <input id="tf-confirm" type="text" inputmode="numeric" maxlength="8" autocomplete="one-time-code" x-model="confirmCode" required>
                        <p class="text-sm text-bad" x-show="cError" x-cloak role="alert" x-text="cError"></p>
                        <button type="submit" class="btn btn-primary self-start" :disabled="cBusy">{{ __('security.twofa.confirm') }}</button>
                    </form>
                </div>
                <p class="text-sm text-bad" x-show="sError" x-cloak role="alert" x-text="sError"></p>
            </div>

            {{-- Enabled: manage --}}
            <div x-show="tfa.enabled" x-cloak class="flex flex-col gap-4">
                <p><span class="badge badge-ok">{{ __('security.twofa.enabled') }}</span></p>
                <p class="text-sm text-ink-2" x-text="t('security.twofa.remaining', {count: tfa.recovery_codes_remaining})"></p>
                <div class="flex flex-col gap-2">
                    <h3 class="font-medium">{{ __('security.twofa.regenerate_title') }}</h3>
                    <p class="text-sm text-ink-2">{{ __('security.twofa.regenerate_help') }}</p>
                    <button type="button" class="btn btn-sm self-start" x-show="!mOpen" @click="mOpen = 'recovery-codes'">{{ __('security.twofa.regenerate') }}</button>
                </div>
                <button type="button" class="btn btn-sm btn-danger self-start" x-show="!mOpen" @click="mOpen = 'disable'">{{ __('security.twofa.disable') }}</button>
                <form x-show="mOpen" x-cloak class="flex flex-col gap-2 rounded-lg border border-line p-3" @submit.prevent="manage(mOpen)">
                    <p class="text-sm text-ink-2" x-show="mOpen === 'disable'">{{ __('security.twofa.disable_help') }}</p>
                    <p class="text-sm text-ink-2" x-show="mOpen === 'recovery-codes'">{{ __('security.twofa.regenerate_confirm') }}</p>
                    <div>
                        <label for="tf-pass">{{ __('common.auth.password') }}</label>
                        <input id="tf-pass" type="password" x-model="m.password" required autocomplete="current-password">
                    </div>
                    <div>
                        <label for="tf-mcode">{{ __('security.twofa.code') }}</label>
                        <input id="tf-mcode" type="text" inputmode="numeric" maxlength="8" x-model="m.code" required autocomplete="one-time-code">
                    </div>
                    <p class="text-sm text-bad" x-show="mError" x-cloak role="alert" x-text="mError"></p>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary" :disabled="mBusy">{{ __('common.confirm_ok') }}</button>
                        <button type="button" class="btn" @click="mOpen = ''; mError = ''">{{ __('common.cancel') }}</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('securityPage', () => ({
        loading: false, loaded: false, error: '',
        me: {email: '', email_verified: true},
        tfa: {enabled: false, pending_setup: false, recovery_codes_remaining: 0},
        rBusy: false, rOk: false, rError: '',
        setup: null, sBusy: false, sError: '',
        confirmCode: '', cBusy: false, cError: '',
        codes: [], copied: false,
        mOpen: '', m: {password: '', code: ''}, mBusy: false, mError: '',
        async load() {
            this.loading = true; this.error = '';
            try {
                this.me = (await api('auth/me')).data;
                this.tfa = (await api('auth/2fa')).data;
                this.loaded = true;
            } catch (e) { this.error = errText(e); }
            finally { this.loading = false; }
        },
        async resend() {
            if (this.rBusy) return;
            this.rBusy = true; this.rOk = false; this.rError = '';
            try { await api('auth/email/resend', {method: 'POST'}); this.rOk = true; }
            catch (e) { this.rError = errText(e); }
            finally { this.rBusy = false; }
        },
        async startSetup() {
            if (this.sBusy) return;
            this.sBusy = true; this.sError = '';
            try { this.setup = (await api('auth/2fa/setup', {method: 'POST'})).data; }
            catch (e) { this.sError = errText(e); }
            finally { this.sBusy = false; }
        },
        async confirm() {
            if (this.cBusy) return;
            this.cBusy = true; this.cError = '';
            try {
                const d = (await api('auth/2fa/confirm', {method: 'POST', body: {code: this.confirmCode.replace(/\s+/g, '')}})).data;
                this.codes = d.recovery_codes; this.setup = null; this.confirmCode = '';
                this.tfa = (await api('auth/2fa')).data; this.me.two_factor_enabled = true;
            } catch (e) { this.cError = errText(e); }
            finally { this.cBusy = false; }
        },
        async manage(kind) {
            if (this.mBusy) return;
            this.mBusy = true; this.mError = '';
            try {
                const d = (await api('auth/2fa/' + kind, {method: 'POST', body: {password: this.m.password, code: this.m.code.replace(/\s+/g, '')}})).data;
                if (kind === 'recovery-codes') this.codes = d.recovery_codes;
                this.m = {password: '', code: ''}; this.mOpen = '';
                this.tfa = (await api('auth/2fa')).data;
            } catch (e) { this.mError = errText(e); }
            finally { this.mBusy = false; }
        },
        async copy(text) {
            try { await navigator.clipboard.writeText(text); this.copied = true; setTimeout(() => this.copied = false, 2000); }
            catch (e) { /* clipboard unavailable: the text is selectable on screen */ }
        },
    }));
});
</script>
@endsection
