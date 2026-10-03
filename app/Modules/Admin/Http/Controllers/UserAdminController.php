<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Actions\UserModeration;
use App\Modules\Admin\Http\Requests\ChangeRoleRequest;
use App\Modules\Admin\Http\Requests\ReasonRequest;
use App\Modules\Admin\Http\Resources\AdminUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Admin: Users
 */
class UserAdminController extends Controller
{
    public function __construct(private UserModeration $moderation) {}

    /**
     * List and search users. Requires `admin`.
     *
     * Filters: `q` (name, handle or email), `role`, `suspended` (bool), `verified_vendor` (bool). Paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->string('q')->trim()->value(), function ($q, $v) {
                $like = '%' . addcslashes($v, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('email', 'like', $like)->orWhere('name', 'like', $like)->orWhere('handle', 'like', $like));
            })
            ->when($request->string('role')->value(), fn ($q, $v) => $q->where('role', $v))
            ->when($request->has('suspended'), fn ($q) => $request->boolean('suspended')
                ? $q->whereNotNull('suspended_at') : $q->whereNull('suspended_at'))
            ->when($request->has('verified_vendor'), fn ($q) => $q->where('is_verified_vendor', $request->boolean('verified_vendor')))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return AdminUserResource::collection($users);
    }

    /** Show one user. Requires `admin`. */
    public function show(User $user): AdminUserResource
    {
        $this->authorize('view', $user);

        return new AdminUserResource($user);
    }

    /**
     * Suspend a user and revoke all their tokens. Requires `admin`.
     *
     * You cannot suspend yourself or the last active admin.
     */
    public function suspend(ReasonRequest $request, User $user): AdminUserResource
    {
        $this->authorize('suspend', $user);

        return new AdminUserResource($this->moderation->suspend($request->user(), $user, $request->validated('reason')));
    }

    /** Lift a suspension. Requires `admin`. */
    public function unsuspend(User $user, Request $request): AdminUserResource
    {
        $this->authorize('suspend', $user);

        return new AdminUserResource($this->moderation->unsuspend($request->user(), $user));
    }

    /** Mark a vendor as verified. Requires `admin`. */
    public function verify(User $user, Request $request): AdminUserResource
    {
        $this->authorize('verifyVendor', $user);

        return new AdminUserResource($this->moderation->setVendorVerified($request->user(), $user, true));
    }

    /** Remove a vendor's verified mark. Requires `admin`. */
    public function unverify(User $user, Request $request): AdminUserResource
    {
        $this->authorize('verifyVendor', $user);

        return new AdminUserResource($this->moderation->setVendorVerified($request->user(), $user, false));
    }

    /**
     * Change a user's role between `buyer` and `vendor`. Requires `admin`.
     *
     * Admins cannot change their own role, and admin accounts can only be created with `php artisan admin:create`.
     */
    public function changeRole(ChangeRoleRequest $request, User $user): AdminUserResource
    {
        $this->authorize('changeRole', $user);

        return new AdminUserResource($this->moderation->changeRole($request->user(), $user, $request->validated('role')));
    }
}
