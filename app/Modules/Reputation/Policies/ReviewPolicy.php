<?php

namespace App\Modules\Reputation\Policies;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Reputation\Models\Review;

class ReviewPolicy
{
    /** Only the buyer who placed the order may review its products. */
    public function createFromOrder(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id;
    }

    /** Only the reviewed vendor may reply. */
    public function reply(User $user, Review $review): bool
    {
        return $user->isVendor() && $review->vendor_id === $user->id;
    }

    /** Anyone but the author may mark a review helpful. */
    public function vote(User $user, Review $review): bool
    {
        return $review->buyer_id !== $user->id;
    }
}
