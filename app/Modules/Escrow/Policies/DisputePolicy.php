<?php

namespace App\Modules\Escrow\Policies;

use App\Models\User;
use App\Modules\Escrow\Models\Dispute;
use App\Modules\Orders\Models\Order;

class DisputePolicy
{
    /** Only the order's buyer or vendor may open a dispute on it. */
    public function open(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id || $order->vendor_id === $user->id;
    }

    /** The two parties or an admin. */
    public function view(User $user, Dispute $dispute): bool
    {
        return $user->isAdmin() || $dispute->involves($user);
    }

    public function message(User $user, Dispute $dispute): bool
    {
        return $this->view($user, $dispute);
    }

    public function resolve(User $user, Dispute|string|null $dispute = null): bool
    {
        return $user->isAdmin();
    }
}
