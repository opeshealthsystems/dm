<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Exceptions\AccountSafetyException;
use App\Modules\Identity\Mail\PasswordResetMail;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Forgot / reset password on top of Laravel's password broker: tokens are stored hashed,
 * single use, and expire after auth.passwords.users.expire minutes (60).
 */
class PasswordReset
{
    public function __construct(private readonly SessionRevoker $sessions)
    {
    }

    /**
     * Send a reset link if the e-mail belongs to an account. Always returns normally so the
     * caller can answer identically whether or not the account exists.
     */
    public function request(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }

    /** Called by User::sendPasswordResetNotification(). */
    public function mail(User $user, string $token): void
    {
        $url = url('/reset-password/' . $token) . '?email=' . rawurlencode($user->email);
        Mail::to($user)->locale(app()->getLocale())->send(
            new PasswordResetMail($url, (int) config('auth.passwords.users.expire', 60))
        );
    }

    /**
     * Set a new password using a token. Revokes every token and session of the user.
     *
     * @throws AccountSafetyException invalid_token (422) for a wrong, used or expired token
     */
    public function reset(string $email, string $token, string $password): void
    {
        $status = Password::broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save(); // 'hashed' cast
                $this->sessions->revokeAll($user);
                event(new PasswordResetEvent($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Same answer for unknown e-mail, bad token and expired token.
            throw new AccountSafetyException(__('security.reset.invalid_token'), 'invalid_token', field: 'email');
        }
    }
}
