<?php

namespace App\Modules\Escrow\Actions;

use App\Models\User;
use App\Modules\Escrow\Exceptions\EscrowException;
use App\Modules\Escrow\Models\Dispute;
use App\Modules\Escrow\Models\DisputeMessage;
use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Dispute workflow, ported from legacy Dispute / DisputeController:
 *  - a participant of the order opens one dispute; the order is frozen ('disputed');
 *  - only an admin resolves it, with a mandatory note, for the buyer (refund) or the vendor (release);
 *  - escrow moves only through OrderLifecycle, so it can happen exactly once.
 */
class DisputeService
{
    public function __construct(private readonly OrderLifecycle $lifecycle)
    {
    }

    public function open(User $user, Order $order, string $reason): Dispute
    {
        try {
            return DB::transaction(function () use ($user, $order, $reason) {
                $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->buyer_id !== $user->id && $locked->vendor_id !== $user->id) {
                    throw new EscrowException('You are not a party to this order.', 403);
                }
                if (Dispute::where('order_id', $locked->id)->exists()) {
                    throw new EscrowException('A dispute already exists for this order.');
                }

                $before = $locked->status;
                $this->lifecycle->markDisputed($locked); // throws unless funds are held and order paid/shipped

                $dispute = new Dispute();
                $dispute->forceFill([
                    'order_id' => $locked->id,
                    'opened_by' => $user->id,
                    'against_user' => $user->id === $locked->buyer_id ? $locked->vendor_id : $locked->buyer_id,
                    'reason' => $reason,
                    'status' => Dispute::OPEN,
                    'order_status_before' => $before,
                ])->save();

                return $dispute->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw new EscrowException('A dispute already exists for this order.');
        }
    }

    /** Add a message or evidence text. Participants and admins; not once resolved. */
    public function addMessage(User $user, Dispute $dispute, string $body, string $kind = 'message'): DisputeMessage
    {
        return DB::transaction(function () use ($user, $dispute, $body, $kind) {
            $fresh = Dispute::whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status !== Dispute::OPEN) {
                throw new EscrowException('This dispute is already resolved.');
            }
            $m = new DisputeMessage();
            $m->forceFill(['dispute_id' => $fresh->id, 'user_id' => $user->id, 'kind' => $kind, 'body' => $body])->save();

            return $m->refresh();
        });
    }

    /**
     * Admin decision. `buyer` refunds the held funds, `vendor` releases them to the vendor
     * (which fires OrderCompleted, so the wallet is credited). Everything runs in one transaction:
     * if the escrow move is refused, the dispute stays open.
     */
    public function resolve(User $admin, Dispute $dispute, string $outcome, string $resolution): Dispute
    {
        if (! in_array($outcome, [Dispute::OUTCOME_BUYER, Dispute::OUTCOME_VENDOR], true)) {
            throw new EscrowException('Outcome must be buyer or vendor.', 422);
        }

        return DB::transaction(function () use ($admin, $dispute, $outcome, $resolution) {
            $fresh = Dispute::whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status === Dispute::RESOLVED) {
                throw new EscrowException('This dispute is already resolved.');
            }

            $order = Order::findOrFail($fresh->order_id);
            $outcome === Dispute::OUTCOME_BUYER
                ? $this->lifecycle->refund($order)
                : $this->lifecycle->releaseToVendor($order);

            $fresh->forceFill([
                'status' => Dispute::RESOLVED,
                'outcome' => $outcome,
                'resolution' => $resolution,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ])->save();

            return $fresh->refresh();
        });
    }
}
