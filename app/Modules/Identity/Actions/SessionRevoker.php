<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/** Signs a user out everywhere: OAuth access + refresh tokens, browser sessions, remember-me. */
class SessionRevoker
{
    public function revokeAll(User $user): void
    {
        $ids = Passport::token()->where('user_id', $user->getAuthIdentifier())->pluck('id');
        Passport::token()->whereIn('id', $ids)->update(['revoked' => true]);
        foreach ($ids->chunk(500) as $chunk) {
            Passport::refreshToken()->whereIn('access_token_id', $chunk)->update(['revoked' => true]);
        }

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
