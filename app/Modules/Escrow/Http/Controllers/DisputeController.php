<?php

namespace App\Modules\Escrow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Escrow\Actions\DisputeService;
use App\Modules\Escrow\Http\Requests\DisputeMessageRequest;
use App\Modules\Escrow\Http\Requests\OpenDisputeRequest;
use App\Modules\Escrow\Http\Requests\ResolveDisputeRequest;
use App\Modules\Escrow\Http\Resources\DisputeResource;
use App\Modules\Escrow\Models\Dispute;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputes)
    {
    }

    /** List disputes you opened or that were opened against you, newest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $id = $request->user()->id;

        return DisputeResource::collection(
            Dispute::where(fn ($q) => $q->where('opened_by', $id)->orWhere('against_user', $id))
                ->latest('id')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    /** Show a dispute with its messages. Parties and admins only. */
    public function show(Dispute $dispute): DisputeResource
    {
        $this->authorize('view', $dispute);

        return new DisputeResource($dispute->load('messages'));
    }

    /**
     * Open a dispute on an order.
     *
     * The buyer or the vendor of the order can open one, once, while the order's funds are held
     * (paid or shipped). The order is frozen: it cannot be confirmed or shipped until an admin
     * resolves the dispute. Requires `orders:write` (buyers) or `vendor:manage` (vendors).
     */
    public function store(OpenDisputeRequest $request, Order $order): JsonResponse
    {
        $this->authorize('open', [Dispute::class, $order]);

        $dispute = $this->disputes->open($request->user(), $order, $request->validated('reason'));

        return (new DisputeResource($dispute))->response()->setStatusCode(201);
    }

    /** Add a message or evidence text to an open dispute. Parties and admins only. */
    public function message(DisputeMessageRequest $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('message', $dispute);

        $m = $this->disputes->addMessage($request->user(), $dispute, $request->validated('body'), $request->validated('kind', 'message'));

        return response()->json(['data' => [
            'id' => $m->id, 'user_id' => $m->user_id, 'kind' => $m->kind, 'body' => $m->body,
            'created_at' => $m->created_at?->toIso8601String(),
        ]], 201);
    }

    /** Admin: list open disputes, oldest first. Requires `admin`. */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $this->authorize('resolve', Dispute::class);

        return DisputeResource::collection(
            Dispute::when($request->query('status', 'open'), fn ($q, $s) => $q->where('status', $s))
                ->oldest('id')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    /**
     * Admin: resolve a dispute.
     *
     * `outcome=buyer` refunds the held funds to the buyer; `outcome=vendor` releases them to the
     * vendor (the vendor wallet is credited, minus commission). A resolution note is required.
     * Funds move exactly once; resolving again returns 409. Requires `admin`.
     */
    public function resolve(ResolveDisputeRequest $request, Dispute $dispute): DisputeResource
    {
        $this->authorize('resolve', $dispute);

        $resolved = $this->disputes->resolve($request->user(), $dispute, $request->validated('outcome'), $request->validated('resolution'));

        return new DisputeResource($resolved->load('messages'));
    }
}
