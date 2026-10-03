<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * @tags Admin: Dashboard
 */
class StatsController extends Controller
{
    /**
     * Dashboard statistics. Requires `admin`.
     *
     * Users by role, orders by status, GMV (sum of subtotals of paid, shipped and completed orders)
     * per currency in cents, and users registered in the last 7 days. All are indexed aggregate queries.
     */
    public function __invoke(): JsonResponse
    {
        $this->authorize('admin');

        return response()->json(['data' => [
            'users_by_role' => User::query()->selectRaw('role, COUNT(*) as c')->groupBy('role')->pluck('c', 'role')
                ->map(fn ($c) => (int) $c)->all(),
            'orders_by_status' => Order::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')
                ->map(fn ($c) => (int) $c)->all(),
            'gmv_by_currency' => Order::query()
                ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED])
                ->selectRaw('currency, SUM(subtotal_cents) as s')->groupBy('currency')->pluck('s', 'currency')
                ->map(fn ($s) => (int) $s)->all(),
            'new_users_7d' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
        ]]);
    }
}
