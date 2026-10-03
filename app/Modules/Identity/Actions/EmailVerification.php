<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Exceptions\AccountSafetyException;
use App\Modules\Identity\Mail\VerifyEmailMail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Signed-link e-mail verification. */
class EmailVerification
{
    public const LINK_MINUTES = 60;

    public function send(User $user): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(self::LINK_MINUTES), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        Mail::to($user)->locale(app()->getLocale())->send(new VerifyEmailMail($url, self::LINK_MINUTES));
    }

    /**
     * Mark the account verified (idempotent). The signature itself is checked by the `signed`
     * middleware; here the hash must still match the current e-mail address.
     *
     * @throws AccountSafetyException invalid_link (403)
     */
    public function verify(int $id, string $hash): User
    {
        $user = User::find($id);
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AccountSafetyException(__('security.verify.invalid_link'), 'invalid_link', 403);
        }

        DB::transaction(function () use (&$user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        });

        return $user;
    }
}
