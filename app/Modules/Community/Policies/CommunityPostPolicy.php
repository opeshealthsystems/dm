<?php

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\CommunityPost;
use Illuminate\Auth\Access\Response;

class CommunityPostPolicy
{
    /** Own post only, within the edit window, and not once the thread is locked (admins never edit others). */
    public function update(User $user, CommunityPost $post): Response
    {
        if (! CommunityThreadPolicy::participates($user) || $post->user_id !== $user->id || $post->trashed()) {
            return Response::deny(__('community.errors.not_allowed'));
        }
        if ($post->thread->is_locked && ! $user->isAdmin()) {
            return Response::deny(__('community.errors.thread_locked'));
        }
        if (! $post->isWithinEditWindow()) {
            return Response::deny(__('community.errors.edit_window', ['minutes' => config('community.edit_window_minutes')]));
        }

        return Response::allow();
    }

    /** The author (while the thread is open) or an admin. */
    public function delete(User $user, CommunityPost $post): Response
    {
        if (! CommunityThreadPolicy::participates($user) || $post->trashed()) {
            return Response::deny(__('community.errors.not_allowed'));
        }
        if ($user->isAdmin()) {
            return Response::allow();
        }
        if ($post->user_id !== $user->id) {
            return Response::deny(__('community.errors.not_allowed'));
        }

        return $post->thread->is_locked ? Response::deny(__('community.errors.thread_locked')) : Response::allow();
    }

    /** Anyone signed in may report someone else's visible post. */
    public function report(User $user, CommunityPost $post): Response
    {
        if (! CommunityThreadPolicy::participates($user) || $post->trashed()) {
            return Response::deny(__('community.errors.not_allowed'));
        }

        return $post->user_id === $user->id ? Response::deny(__('community.errors.own_post')) : Response::allow();
    }
}
