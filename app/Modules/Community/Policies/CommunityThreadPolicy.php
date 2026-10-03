<?php

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\CommunityCategory;
use App\Modules\Community\Models\CommunityThread;
use Illuminate\Auth\Access\Response;

class CommunityThreadPolicy
{
    /** Signed-in, not suspended, and a known marketplace role. */
    public static function participates(User $user): bool
    {
        return ! $user->isSuspended()
            && in_array($user->role, [User::ROLE_BUYER, User::ROLE_VENDOR, User::ROLE_ADMIN], true);
    }

    public function create(User $user, CommunityCategory $category): Response
    {
        if (! self::participates($user)) {
            return Response::deny(__('community.errors.not_allowed'));
        }

        return ! $category->staff_only || $user->isAdmin()
            ? Response::allow()
            : Response::deny(__('community.errors.staff_only'));
    }

    /** Locked threads accept replies from admins only. */
    public function reply(User $user, CommunityThread $thread): Response
    {
        if (! self::participates($user)) {
            return Response::deny(__('community.errors.not_allowed'));
        }

        return ! $thread->is_locked || $user->isAdmin()
            ? Response::allow()
            : Response::deny(__('community.errors.thread_locked'));
    }

    /** Subscribe, unsubscribe, mark read. */
    public function interact(User $user, CommunityThread $thread): bool
    {
        return self::participates($user);
    }

    /** Pin, lock and delete threads, manage categories and the report queue. */
    public function moderate(User $user): bool
    {
        return $user->isAdmin() && ! $user->isSuspended();
    }
}
