<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Exceptions\AccountSafetyException;
use App\Modules\Identity\Models\RecoveryCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** TOTP two-factor lifecycle: setup, confirm, verify at login, recovery codes, disable. */
class TwoFactorAuthentication
{
    public const RECOVERY_CODES = 10;

    public function __construct(private readonly Totp $totp, private readonly AttemptGuard $guard)
    {
    }

    /**
     * Start (or restart) setup: stores a fresh unconfirmed secret and returns what the app shows.
     *
     * @return array{secret: string, otpauth_uri: string}
     */
    public function begin(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasTwoFactorEnabled()) {
                throw new AccountSafetyException(__('security.api.already_enabled'), 'two_factor_enabled', 409);
            }
            $secret = $this->totp->generateSecret();
            $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();

            return [
                'secret' => $secret,
                'otpauth_uri' => $this->totp->uri($secret, $user->email, config('app.name')),
            ];
        });
    }

    /**
     * Confirm setup with a first valid code. Returns the recovery codes: the only time they are visible.
     *
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        $lock = $this->guard->key('2fa', $user->id);
        $this->guard->ensureNotLocked($lock);

        $codes = DB::transaction(function () use ($user, $code) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasTwoFactorEnabled()) {
                throw new AccountSafetyException(__('security.api.already_enabled'), 'two_factor_enabled', 409);
            }
            if ($user->two_factor_secret === null) {
                throw new AccountSafetyException(__('security.api.not_started'), 'two_factor_not_started', 409);
            }
            $step = $this->totp->matchingStep($user->two_factor_secret, $code);
            if ($step === null) {
                return null;
            }
            $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();

            return $this->issueRecoveryCodes($user);
        });

        // The lockout counter lives in the cache, so it is updated outside the transaction.
        if ($codes === null) {
            $this->guard->fail($lock);
            throw new AccountSafetyException(__('security.api.invalid_code'), 'invalid_code', field: 'code');
        }
        $this->guard->clear($lock);

        return $codes;
    }

    /**
     * Check a login/management challenge: a TOTP code (+-1 step, never reused) OR a recovery code (single use).
     * Failures count against the per-account lockout supplied by the caller.
     */
    public function verify(User $user, ?string $code, ?string $recoveryCode): bool
    {
        return DB::transaction(function () use ($user, $code, $recoveryCode) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->hasTwoFactorEnabled()) {
                return false;
            }

            if ($code !== null && $code !== '') {
                $step = $this->totp->matchingStep($locked->two_factor_secret, $code);
                // A step at or before the last accepted one is a replay.
                if ($step !== null && ($locked->two_factor_last_step === null || $step > (int) $locked->two_factor_last_step)) {
                    $locked->forceFill(['two_factor_last_step' => $step])->save();

                    return true;
                }

                return false;
            }

            if ($recoveryCode !== null && $recoveryCode !== '') {
                return $this->consumeRecoveryCode($locked, $recoveryCode);
            }

            return false;
        });
    }

    /** Disable 2FA: needs the password AND a current code (or recovery code). */
    public function disable(User $user, string $password, ?string $code, ?string $recoveryCode): void
    {
        $lock = $this->guard->key('2fa', $user->id);
        $this->guard->ensureNotLocked($lock);

        if (! Hash::check($password, $user->password) || ! $this->verify($user, $code, $recoveryCode)) {
            $this->guard->fail($lock);
            throw new AccountSafetyException(__('security.api.disable_failed'), 'invalid_credentials', field: 'code');
        }

        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
            RecoveryCode::where('user_id', $user->id)->delete();
        });
        $this->guard->clear($lock);
    }

    /**
     * Replace all recovery codes (old ones stop working). Needs password + a current TOTP code.
     *
     * @return list<string>
     */
    public function regenerate(User $user, string $password, ?string $code): array
    {
        $lock = $this->guard->key('2fa', $user->id);
        $this->guard->ensureNotLocked($lock);

        if (! $user->hasTwoFactorEnabled() || ! Hash::check($password, $user->password) || ! $this->verify($user, $code, null)) {
            $this->guard->fail($lock);
            throw new AccountSafetyException(__('security.api.disable_failed'), 'invalid_credentials', field: 'code');
        }
        $this->guard->clear($lock);

        return DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $this->issueRecoveryCodes($locked);
        });
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return RecoveryCode::where('user_id', $user->id)->whereNull('used_at')->count();
    }

    /** @return list<string> */
    private function issueRecoveryCodes(User $user): array
    {
        RecoveryCode::where('user_id', $user->id)->delete();
        $plain = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $code = strtolower(Str::random(5) . '-' . Str::random(5));
            $plain[] = $code;
            RecoveryCode::create(['user_id' => $user->id, 'code_hash' => $this->hashCode($code)]);
        }

        return $plain;
    }

    private function consumeRecoveryCode(User $user, string $input): bool
    {
        $normalised = strtolower(trim($input));
        // Atomic: exactly one request can flip used_at from null, so a code never works twice.
        return RecoveryCode::where('user_id', $user->id)
            ->where('code_hash', $this->hashCode($normalised))
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
