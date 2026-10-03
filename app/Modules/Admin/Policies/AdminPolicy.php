<?php

namespace App\Modules\Admin\Policies;

use App\Models\User;

/**
 * Single policy for admin-managed resources (User and Category models).
 * Everything requires an admin role; the route layer additionally requires the `admin` scope.
 */
class AdminPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }

    public function suspend(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }

    public function verifyVendor(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }

    public function changeRole(User $user, mixed $model = null): bool
    {
        return $user->isAdmin();
    }
}
