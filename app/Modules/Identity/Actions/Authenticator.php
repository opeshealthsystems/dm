<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Exceptions\AccountSafetyException;
use Illuminate\Support\Facades\Hash;

/**
 * Password + optional second factor, with per-account lockout. Shared by POST /auth/login
 * and the web login so both enforce the same rules. OAuth flows never call this.
 */
class Authenticator
{
    public function __construct(private readonly AttemptGuard $guard, private readonly TwoFactorAuthentication $twoFactor)
    {
    }

    /**
     * Check e-mail + password. Counts failures per account (unknown e-mails too).
     * Does NOT clear the lockout counter: call complete() once the whole login succeeded.
     *
     * @throws AccountSafetyException invalid_credentials (422), account_locked (429) or suspended (403)
     */
    public function checkPassword(string $email, string $password): User
    {
        $lock = $this->guard->key('login', $email);
        $this->guard->ensureNotLocked($lock);

        $user = User::where('email', $email)->first();
        // Hash even for unknown accounts so timing does not reveal them.
        $valid = $user ? Hash::check($password, $user->password) : Hash::check($password, Hash::make('dummy-password'));

        if (! $user || ! $valid) {
            $this->guard->fail($lock);
            throw new AccountSafetyException(__('common.auth.failed'), 'invalid_credentials', field: 'email');
        }
        if ($user->isSuspended()) {
            throw new AccountSafetyException(__('common.auth.suspended'), 'suspended', 403);
        }

        return $user;
    }

    /**
     * Check the second factor for a user who passed checkPassword().
     *
     * @throws AccountSafetyException account_locked (429) or invalid_code (422)
     */
    public function checkSecondFactor(User $user, ?string $code, ?string $recoveryCode): void
    {
        $lock = $this->guard->key('login', $user->email);
        $this->guard->ensureNotLocked($lock);

        if (! $this->twoFactor->verify($user, $code, $recoveryCode)) {
            $this->guard->fail($lock);
            throw new AccountSafetyException(__('security.api.invalid_code'), 'invalid_code', field: 'code');
        }
    }

    /** The login fully succeeded: reset the failure counter. */
    public function complete(User $user): void
    {
        $this->guard->clear($this->guard->key('login', $user->email));
    }
}
