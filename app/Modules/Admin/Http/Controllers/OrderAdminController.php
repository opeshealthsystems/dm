<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Admin: Orders
 */
class OrderAdminController extends Controller
{
    /**
     * List all orders (read-only). Requires `admin`.
     *
     * Filters: `status`, `escrow_status`, `currency`, `buyer_id`, `vendor_id`, `number`, `from`, `to` (dates).
     * The response carries `totals`: filtered order count and subtotal sum per currency.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('admin');

        $query = Order::query()
            ->when($request->string('status')->value(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('escrow_status')->value(), fn ($q, $v) => $q->where('escrow_status', $v))
            ->when($request->string('currency')->upper()->value(), fn ($q, $v) => $q->where('currency', $v))
            ->when($request->integer('buyer_id'), fn ($q, $v) => $q->where('buyer_id', $v))
            ->when($request->integer('vendor_id'), fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->string('number')->trim()->value(), fn ($q, $v) => $q->where('number', $v))
            ->when($request->date('from'), fn ($q, $v) => $q->where('created_at', '>=', $v->startOfDay()))
            ->when($request->date('to'), fn ($q, $v) => $q->where('created_at', '<=', $v->endOfDay()));

        $totals = (clone $query)->reorder()->selectRaw('currency, COUNT(*) as orders, SUM(subtotal_cents) as subtotal_cents')
            ->groupBy('currency')->get()
            ->map(fn ($r) => ['currency' => $r->currency, 'orders' => (int) $r->orders, 'subtotal_cents' => (int) $r->subtotal_cents])
            ->values();

        $orders = $query->with(['buyer:id,handle', 'vendor:id,handle'])->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return AdminOrderResource::collection($orders)->additional(['totals' => $totals]);
    }

    /** Show one order with its items. Shipping address is deliberately not exposed. Requires `admin`. */
    public function show(Order $order): AdminOrderResource
    {
        $this->authorize('admin');

        return new AdminOrderResource($order->load(['buyer:id,handle', 'vendor:id,handle', 'items']));
    }
}
