<?php

namespace App\Modules\Admin\Actions;

use App\Models\User;
use App\Modules\Admin\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/** Records admin mutations. Call it inside the same transaction as the change. */
class AuditLogger
{
    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public function record(
        string $action,
        string $targetType,
        int|string|null $targetId,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::guard('api')->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $log = new AuditLog;
        $log->forceFill([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'before' => $before,
            'after' => $after,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ])->save();

        return $log;
    }
}
