@auth
@php($acct = auth()->user())
@if (! $acct->hasVerifiedEmail())
    <div x-data="{ busy: false, ok: false, err: '' }" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-warn-soft p-3 text-sm text-warn" role="status">
        <span class="min-w-0 flex-1 wrap-anywhere">{{ __('security.verify.banner') }}</span>
        <button type="button" class="btn btn-sm" :disabled="busy || ok"
                @click="busy = true; err = ''; api('auth/email/resend', {method: 'POST'}).then(() => ok = true).catch(e => err = errText(e)).finally(() => busy = false)">
            {{ __('security.verify.resend') }}
        </button>
        <span x-show="ok" x-cloak>{{ __('security.verify.sent') }}</span>
        <span x-show="err" x-cloak class="text-bad" role="alert" x-text="err"></span>
    </div>
@endif
@if ($acct->isAdmin() && ! $acct->hasTwoFactorEnabled())
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-warn-soft p-3 text-sm text-warn" role="status">
        <span class="min-w-0 flex-1 wrap-anywhere">{{ __('security.twofa.admin_banner') }}</span>
        <a class="btn btn-sm" href="{{ route('account.security') }}">{{ __('security.twofa.enable') }}</a>
    </div>
@endif
@endauth
