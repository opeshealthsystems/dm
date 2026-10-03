<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Exceptions\AccountSafetyException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-account failure lockout: 5 failures in 15 minutes locks the key until the window
 * passes. Keyed by e-mail (login) or user id (2FA management), never by IP, so it also
 * stops distributed guessing. Works for unknown e-mails too, so it never reveals accounts.
 */
class AttemptGuard
{
    public const MAX_ATTEMPTS = 5;
    public const DECAY_SECONDS = 900;

    public function key(string $scope, string|int $id): string
    {
        return 'acct-lock|' . $scope . '|' . strtolower(trim((string) $id));
    }

    public function ensureNotLocked(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw new AccountSafetyException(
                __('security.lockout.message', ['minutes' => max(1, (int) ceil($seconds / 60))]),
                'account_locked',
                429,
                $seconds,
            );
        }
    }

    public function fail(string $key): void
    {
        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }
}
