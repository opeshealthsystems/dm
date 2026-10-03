<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\PayoutService;
use App\Modules\Wallet\Http\Requests\PayoutDecisionRequest;
use App\Modules\Wallet\Http\Requests\StorePayoutRequest;
use App\Modules\Wallet\Http\Resources\PayoutRequestResource;
use App\Modules\Wallet\Models\PayoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayoutController extends Controller
{
    public function __construct(private readonly PayoutService $payouts)
    {
    }

    /** List your payout requests, newest first. Requires `vendor:manage`. */
    public function index(Request $request): AnonymousResourceCollection
    {
        return PayoutRequestResource::collection(
            PayoutRequest::where('user_id', $request->user()->id)->latest('id')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    /**
     * Request a payout.
     *
     * The amount is deducted from your available balance immediately (so it cannot be spent twice)
     * and held until an admin approves and pays it, or rejects it and the amount is returned.
     * Fails with 409 when the amount exceeds your available balance. Requires `vendor:manage`.
     */
    public function store(StorePayoutRequest $request): JsonResponse
    {
        $payout = $this->payouts->request(
            $request->user(),
            $request->validated('currency'),
            $request->validated('amount_cents'),
            $request->validated('method'),
            $request->validated('destination_address'),
        );

        return (new PayoutRequestResource($payout))->response()->setStatusCode(201);
    }

    /** Show one of your payout requests. */
    public function show(PayoutRequest $payout): PayoutRequestResource
    {
        $this->authorize('view', $payout);

        return new PayoutRequestResource($payout);
    }

    /** Admin: list payout requests, optionally `status=pending|approved|paid|rejected`. Requires `admin`. */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', PayoutRequest::class);

        return PayoutRequestResource::collection(
            PayoutRequest::when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->oldest('id')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    /** Admin: approve a pending payout. Requires `admin`. */
    public function approve(PayoutDecisionRequest $request, PayoutRequest $payout): PayoutRequestResource
    {
        $this->authorize('manage', $payout);

        return new PayoutRequestResource($this->payouts->approve($payout, $request->user(), $request->validated('note')));
    }

    /** Admin: reject a pending or approved payout; the amount returns to the vendor's balance. Requires `admin`. */
    public function reject(PayoutDecisionRequest $request, PayoutRequest $payout): PayoutRequestResource
    {
        $this->authorize('manage', $payout);

        return new PayoutRequestResource($this->payouts->reject($payout, $request->user(), $request->validated('note')));
    }

    /** Admin: record that an approved payout was sent off-platform (txid required). Requires `admin`. */
    public function markPaid(PayoutDecisionRequest $request, PayoutRequest $payout): PayoutRequestResource
    {
        $this->authorize('manage', $payout);

        return new PayoutRequestResource($this->payouts->markPaid($payout, $request->user(), $request->validated('txid')));
    }
}
