<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Resources\AuditLogResource;
use App\Modules\Admin\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Admin: Audit log
 */
class AuditLogController extends Controller
{
    /**
     * List audit log entries, newest first. Requires `admin`.
     *
     * Filters: `actor_id`, `action` (exact, or prefix with `*` e.g. `user.*`), `target_type`, `target_id`,
     * `from`, `to` (dates). Paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('admin');

        $logs = AuditLog::query()->with('actor:id,email')
            ->when($request->integer('actor_id'), fn ($q, $v) => $q->where('actor_id', $v))
            ->when($request->string('action')->trim()->value(), function ($q, $v) {
                str_ends_with($v, '*')
                    ? $q->where('action', 'like', addcslashes(rtrim($v, '*'), '%_\\') . '%')
                    : $q->where('action', $v);
            })
            ->when($request->string('target_type')->value(), fn ($q, $v) => $q->where('target_type', $v))
            ->when($request->string('target_id')->value(), fn ($q, $v) => $q->where('target_id', $v))
            ->when($request->date('from'), fn ($q, $v) => $q->where('created_at', '>=', $v->startOfDay()))
            ->when($request->date('to'), fn ($q, $v) => $q->where('created_at', '<=', $v->endOfDay()))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100));

        return AuditLogResource::collection($logs);
    }
}
