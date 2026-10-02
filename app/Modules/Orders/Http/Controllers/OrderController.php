<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Actions\CheckoutService;
use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Http\Requests\CheckoutRequest;
use App\Modules\Orders\Http\Requests\ShipOrderRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private readonly OrderLifecycle $lifecycle)
    {
    }

    /**
     * List your orders.
     *
     * `role=buyer` (default) lists orders you placed; `role=vendor` lists orders placed with
     * your shop. Optional `status` filter. Paginated, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $column = $request->query('role') === 'vendor' ? 'vendor_id' : 'buyer_id';

        $orders = Order::with('items')->where($column, $user->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(min($request->integer('per_page', 20), 100));

        return OrderResource::collection($orders);
    }

    /** Show one of your orders. */
    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load('items'));
    }

    /**
     * Check out the cart.
     *
     * Creates one order per vendor, reserves stock, and returns the new orders awaiting payment.
     */
    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $orders = $checkout->placeOrders(
            $request->user(),
            $request->validated('shipping_address'),
            $request->validated('payment_method'),
        );

        return OrderResource::collection($orders->each->load('items'))->response()->setStatusCode(201);
    }

    /** Vendor marks a paid order as shipped. Requires `vendor:manage`. */
    public function ship(ShipOrderRequest $request, Order $order): OrderResource
    {
        $this->authorize('ship', $order);

        return new OrderResource($this->lifecycle->ship($order, $request->validated('tracking_number'))->load('items'));
    }

    /** Buyer confirms receipt; escrow is released to the vendor. */
    public function confirm(Order $order): OrderResource
    {
        $this->authorize('confirm', $order);

        return new OrderResource($this->lifecycle->confirmReceipt($order)->load('items'));
    }

    /** Buyer cancels an order that has not been paid yet. */
    public function cancel(Order $order): OrderResource
    {
        $this->authorize('cancel', $order);

        return new OrderResource($this->lifecycle->cancel($order)->load('items'));
    }
}
