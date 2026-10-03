<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Exceptions\WalletException;
use App\Modules\Wallet\Models\WalletAccount;
use App\Modules\Wallet\Models\WalletEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of wallet_entries / wallet_accounts.
 *
 * Every post runs in a transaction, locks the account row, refuses to take the balance below
 * zero and is idempotent per `idempotency_key` (a replay returns the original entry unchanged).
 */
class Ledger
{
    /**
     * Append one entry and move the cached balance.
     *
     * @return array{0: WalletEntry, 1: bool} the entry and whether it was newly created
     */
    public function post(
        int $userId,
        string $currency,
        string $type,
        int $amountCents,
        string $idempotencyKey,
        ?int $orderId = null,
        ?int $payoutRequestId = null,
        ?string $description = null,
    ): array {
        if ($amountCents === 0) {
            throw new WalletException('A ledger entry cannot be zero.', 422);
        }

        return DB::transaction(function () use ($userId, $currency, $type, $amountCents, $idempotencyKey, $orderId, $payoutRequestId, $description) {
            $account = $this->lockAccount($userId, $currency);

            if ($existing = WalletEntry::where('idempotency_key', $idempotencyKey)->first()) {
                return [$existing, false];
            }

            $new = $account->balance_cents + $amountCents;
            if ($new < 0) {
                throw new WalletException('Insufficient available balance.');
            }

            $entry = new WalletEntry();
            $entry->forceFill([
                'user_id' => $userId,
                'currency' => $currency,
                'type' => $type,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $new,
                'order_id' => $orderId,
                'payout_request_id' => $payoutRequestId,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
            ])->save();

            $account->forceFill(['balance_cents' => $new])->save();

            return [$entry, true];
        });
    }

    /** Get (creating if needed) and row-lock the account. Call inside a transaction. */
    public function lockAccount(int $userId, string $currency): WalletAccount
    {
        $key = ['user_id' => $userId, 'currency' => $currency];
        if (! WalletAccount::where($key)->exists()) {
            $account = new WalletAccount();
            $account->forceFill($key + ['balance_cents' => 0]);
            try {
                $account->save();
            } catch (UniqueConstraintViolationException) {
                // Lost a creation race: the winner's row is the one to lock.
            }
        }

        return WalletAccount::where($key)->lockForUpdate()->firstOrFail();
    }

    /** Balance recomputed from the ledger (used to audit the cached figure). */
    public function computedBalance(int $userId, string $currency): int
    {
        return (int) WalletEntry::where(['user_id' => $userId, 'currency' => $currency])->sum('amount_cents');
    }

    public function balance(int $userId, string $currency): int
    {
        return (int) (WalletAccount::where(['user_id' => $userId, 'currency' => $currency])->value('balance_cents') ?? 0);
    }
}
