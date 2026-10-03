<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Exceptions\WalletException;
use App\Modules\Wallet\Models\PayoutRequest;
use App\Modules\Wallet\Models\WalletEntry;
use Illuminate\Support\Facades\DB;

/**
 * Payout requests. Mirrors legacy MoneroPayoutController / MoneroPayoutRequest:
 * the balance is deducted at request time (prevents double spend), an admin processes it
 * offline. No chain sending happens here; `markPaid` just records the admin's txid.
 * Rejecting returns the money through a reversal entry.
 */
class PayoutService
{
    public function __construct(private readonly Ledger $ledger)
    {
    }

    public function request(User $vendor, string $currency, int $amountCents, string $method, string $address): PayoutRequest
    {
        if ($amountCents <= 0) {
            throw new WalletException('Invalid amount.', 422);
        }

        return DB::transaction(function () use ($vendor, $currency, $amountCents, $method, $address) {
            $account = $this->ledger->lockAccount($vendor->id, $currency);
            if ($amountCents > $account->balance_cents) {
                throw new WalletException('Insufficient available balance.');
            }

            $payout = new PayoutRequest();
            $payout->forceFill([
                'user_id' => $vendor->id, 'currency' => $currency, 'amount_cents' => $amountCents,
                'method' => $method, 'destination_address' => $address, 'status' => PayoutRequest::PENDING,
            ])->save();

            $this->ledger->post($vendor->id, $currency, WalletEntry::PAYOUT_DEBIT, -$amountCents,
                "payout:{$payout->id}", payoutRequestId: $payout->id, description: "Payout request #{$payout->id}");

            return $payout->refresh();
        });
    }

    public function approve(PayoutRequest $payout, User $admin, ?string $note = null): PayoutRequest
    {
        return $this->transition($payout, [PayoutRequest::PENDING], function (PayoutRequest $p) use ($admin, $note) {
            $p->forceFill(['status' => PayoutRequest::APPROVED, 'processed_by' => $admin->id,
                'admin_note' => $note, 'approved_at' => now()])->save();
        }, 'Only a pending payout can be approved.');
    }

    public function markPaid(PayoutRequest $payout, User $admin, string $txid): PayoutRequest
    {
        return $this->transition($payout, [PayoutRequest::APPROVED], function (PayoutRequest $p) use ($admin, $txid) {
            $p->forceFill(['status' => PayoutRequest::PAID, 'processed_by' => $admin->id,
                'txid' => $txid, 'paid_at' => now()])->save();
        }, 'Only an approved payout can be marked as paid.');
    }

    public function reject(PayoutRequest $payout, User $admin, string $note): PayoutRequest
    {
        return $this->transition($payout, [PayoutRequest::PENDING, PayoutRequest::APPROVED], function (PayoutRequest $p) use ($admin, $note) {
            $p->forceFill(['status' => PayoutRequest::REJECTED, 'processed_by' => $admin->id,
                'admin_note' => $note, 'rejected_at' => now()])->save();
            $this->ledger->post($p->user_id, $p->currency, WalletEntry::PAYOUT_REVERSAL, (int) $p->amount_cents,
                "payout-reversal:{$p->id}", payoutRequestId: $p->id, description: "Payout #{$p->id} rejected");
        }, 'Only a pending or approved payout can be rejected.');
    }

    /** Lock the payout row, check the current status, apply, all in one transaction. */
    private function transition(PayoutRequest $payout, array $from, callable $apply, string $error): PayoutRequest
    {
        return DB::transaction(function () use ($payout, $from, $apply, $error) {
            $fresh = PayoutRequest::whereKey($payout->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($fresh->status, $from, true)) {
                throw new WalletException($error);
            }
            $apply($fresh);

            return $fresh->refresh();
        });
    }
}
