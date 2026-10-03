<?php

namespace App\Modules\Admin\Actions;

use App\Models\User;
use App\Modules\Admin\Exceptions\AdminException;
use Illuminate\Support\Facades\DB;

/** Admin mutations on user accounts. Every change is audited in the same transaction. */
class UserModeration
{
    public function __construct(private AuditLogger $audit) {}

    public function suspend(User $actor, User $target, ?string $reason = null): User
    {
        return DB::transaction(function () use ($actor, $target, $reason) {
            $target = $this->lock($target);
            if ($actor->is($target)) {
                throw new AdminException('You cannot suspend yourself.', 403);
            }
            if ($target->isSuspended()) {
                throw new AdminException('User is already suspended.', 409);
            }
            $this->guardLastAdmin($target, 'suspend');

            $before = $this->snapshot($target);
            $target->forceFill(['suspended_at' => now()])->save();
            $this->revokeTokens($target);

            $this->audit->record('user.suspended', 'user', $target->id, $before,
                $this->snapshot($target) + ['reason' => $reason], $actor);

            return $target;
        });
    }

    public function unsuspend(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target) {
            $target = $this->lock($target);
            if (! $target->isSuspended()) {
                throw new AdminException('User is not suspended.', 409);
            }
            $before = $this->snapshot($target);
            $target->forceFill(['suspended_at' => null])->save();
            $this->audit->record('user.unsuspended', 'user', $target->id, $before, $this->snapshot($target), $actor);

            return $target;
        });
    }

    public function setVendorVerified(User $actor, User $target, bool $verified): User
    {
        return DB::transaction(function () use ($actor, $target, $verified) {
            $target = $this->lock($target);
            if (! $target->isVendor()) {
                throw new AdminException('Only vendors can be verified.', 422);
            }
            $before = $this->snapshot($target);
            $target->forceFill(['is_verified_vendor' => $verified])->save();
            $this->audit->record($verified ? 'user.vendor_verified' : 'user.vendor_unverified', 'user',
                $target->id, $before, $this->snapshot($target), $actor);

            return $target;
        });
    }

    /** Change a role. Only buyer <-> vendor are valid destinations; admins are never created here. */
    public function changeRole(User $actor, User $target, string $role): User
    {
        if (! in_array($role, [User::ROLE_BUYER, User::ROLE_VENDOR], true)) {
            throw new AdminException('Role must be buyer or vendor.', 422);
        }

        return DB::transaction(function () use ($actor, $target, $role) {
            $target = $this->lock($target);
            if ($actor->is($target)) {
                throw new AdminException('You cannot change your own role.', 403);
            }
            $this->guardLastAdmin($target, 'demote');
            if ($target->role === $role) {
                throw new AdminException("User already has role [$role].", 409);
            }

            $before = $this->snapshot($target);
            $target->forceFill([
                'role' => $role,
                'is_verified_vendor' => $role === User::ROLE_VENDOR ? $target->is_verified_vendor : false,
            ])->save();
            // Tokens carry the old role's scopes; force re-login.
            $this->revokeTokens($target);

            $this->audit->record('user.role_changed', 'user', $target->id, $before, $this->snapshot($target), $actor);

            return $target;
        });
    }

    private function lock(User $user): User
    {
        return User::query()->lockForUpdate()->findOrFail($user->getKey());
    }

    /** Refuse to take away the last remaining active admin. */
    private function guardLastAdmin(User $target, string $verb): void
    {
        if (! $target->isAdmin() || $target->isSuspended()) {
            return;
        }
        $others = User::query()->where('role', User::ROLE_ADMIN)
            ->whereNull('suspended_at')->where('id', '!=', $target->id)->lockForUpdate()->count();
        if ($others === 0) {
            throw new AdminException("Cannot $verb the last active admin.", 422);
        }
    }

    private function revokeTokens(User $user): void
    {
        $ids = DB::table('oauth_access_tokens')->where('user_id', $user->id)->pluck('id');
        DB::table('oauth_access_tokens')->where('user_id', $user->id)->update(['revoked' => true]);
        if ($ids->isNotEmpty()) {
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $ids)->update(['revoked' => true]);
        }
    }

    /** @return array<string,mixed> */
    private function snapshot(User $u): array
    {
        return [
            'role' => $u->role,
            'is_verified_vendor' => (bool) $u->is_verified_vendor,
            'suspended_at' => $u->suspended_at?->toIso8601String(),
        ];
    }
}
