<?php

namespace App\Modules\ContentSeo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\AuditLogger;
use App\Modules\ContentSeo\Models\SupportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Admin: Support
 */
class SupportAdminController extends Controller
{
    /**
     * List support requests, newest first. Requires `admin`.
     *
     * Filter with `status` (`new` or `handled`). Paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $page = SupportRequest::query()
            ->when(in_array($request->query('status'), [SupportRequest::STATUS_NEW, SupportRequest::STATUS_HANDLED], true),
                fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("case when status = 'new' then 0 else 1 end")
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $page->getCollection()->map(fn (SupportRequest $r) => $this->present($r))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Mark a support request as handled. Requires `admin`. */
    public function handle(Request $request, SupportRequest $supportRequest, AuditLogger $audit): JsonResponse
    {
        $this->authorize('admin');

        if ($supportRequest->status !== SupportRequest::STATUS_HANDLED) {
            $supportRequest->forceFill([
                'status' => SupportRequest::STATUS_HANDLED,
                'handled_by' => $request->user()->getKey(),
                'handled_at' => now(),
            ])->save();
            $audit->record('support.handled', 'support_request', $supportRequest->id);
        }

        return response()->json(['data' => $this->present($supportRequest)]);
    }

    private function present(SupportRequest $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'subject' => $r->subject,
            'message' => $r->message,
            'locale' => $r->locale,
            'status' => $r->status,
            'handled_at' => $r->handled_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
