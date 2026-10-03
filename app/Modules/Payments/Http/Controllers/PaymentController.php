<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\PaymentService;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    /**
     * Payment instructions and live status for an order.
     *
     * Visible to the order's buyer, its vendor and admins. Returns the receiving address, the exact
     * amount in the smallest unit (satoshi / piconero), confirmations and status
     * (pending | detected | confirmed | expired | underpaid).
     */
    public function show(Order $order, PaymentService $payments): PaymentResource|JsonResponse
    {
        $this->authorize('view', $order);
        // The deposit address is for the paying buyer (and support), not the vendor.
        abort_unless($order->buyer_id === request()->user()->id || request()->user()->isAdmin(), 403);

        $payment = Payment::where('order_id', $order->id)->where('method', $order->payment_method)->first();
        if (! $payment) {
            if ($order->status !== \App\Modules\Orders\Models\Order::STATUS_PENDING_PAYMENT) {
                return response()->json(['message' => 'Payment is not available for this order.'], 404);
            }
            try {
                $payment = $payments->createForOrder($order);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Payment is not available for this order.'], 404);
            }
        }

        return new PaymentResource($payment);
    }
}
