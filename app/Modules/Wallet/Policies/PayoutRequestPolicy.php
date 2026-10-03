<?php

namespace App\Modules\Wallet\Policies;

use App\Models\User;
use App\Modules\Wallet\Models\PayoutRequest;

class PayoutRequestPolicy
{
    public function view(User $user, PayoutRequest $payout): bool
    {
        return $user->isAdmin() || $payout->user_id === $user->id;
    }

    /** Approve, reject, mark paid, list everyone's payouts: admins only. */
    public function manage(User $user, PayoutRequest|string|null $payout = null): bool
    {
        return $user->isAdmin();
    }
}
