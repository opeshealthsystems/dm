<?php

namespace App\Modules\Catalog\Policies;

use App\Models\User;
use App\Modules\Catalog\Models\Product;

class ProductPolicy
{
    public function create(User $user): bool
    {
        return $user->isVendor() || $user->isAdmin();
    }

    /** A vendor may only change their own products; admins may change any. */
    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || ($user->isVendor() && $product->vendor_id === $user->id);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
