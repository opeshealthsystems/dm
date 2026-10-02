<?php

namespace App\Modules\Orders\Policies;

use App\Models\User;
use App\Modules\Orders\Models\Order;

class OrderPolicy
{
    /** Buyer, the order's vendor, or an admin. Nobody else, ever. */
    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->buyer_id === $user->id || $order->vendor_id === $user->id;
    }

    public function ship(User $user, Order $order): bool
    {
        return $order->vendor_id === $user->id;
    }

    public function confirm(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id;
    }
}
