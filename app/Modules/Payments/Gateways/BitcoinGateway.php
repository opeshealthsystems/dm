<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\DTO\PaymentInstruction;
use App\Modules\Payments\DTO\PaymentObservation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\BitcoinHelper;
use App\Modules\Payments\Support\PaymentLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bitcoin via xpub/zpub address derivation (no private keys) and BlockCypher lookups.
 * Behaviour ported from legacy PaymentService (sealed): sats = floor(fiat / rate * 1e8),
 * detected = final_balance (incl. unconfirmed), confirmed = balance, rate cache + 3 APIs + fallbacks.
 */
class BitcoinGateway implements PaymentGateway
{
    public const SATS_PER_BTC = 100000000;

    public function method(): string
    {
        return 'bitcoin';
    }

    /**
     * Must be called inside the caller's DB transaction: the row lock on existing bitcoin
     * payments keeps the next derivation index unique under concurrent checkouts.
     */
    public function createPayment(Order $order): PaymentInstruction
    {
        $existing = Payment::where('order_id', $order->id)->where('method', $this->method())->first();
        if ($existing) {
            return $this->instructionFor($existing);
        }

        $xpub = config('payments.bitcoin.xpub');
        if (! $xpub) {
            PaymentLogger::error('Missing XPUB for address generation.');
            throw new RuntimeException('Bitcoin payments are not configured.');
        }

        // Legacy: MAX(derivation_index) ... FOR UPDATE, next = max + 1 (first = 0).
        $max = Payment::where('method', $this->method())->lockForUpdate()->max('derivation_index');
        $index = $max === null ? 0 : ((int) $max) + 1;

        try {
            $address = BitcoinHelper::deriveAddress($xpub, $index, 0);
        } catch (\Throwable $e) {
            PaymentLogger::error('Local derivation failed', ['index' => $index, 'error' => $e->getMessage()]);
            throw new RuntimeException('Could not derive Bitcoin address.', 0, $e);
        }
        PaymentLogger::info('Derived Bitcoin address', ['index' => $index]);

        $rate = $this->exchangeRate(strtoupper($order->currency));
        $sats = self::fiatToSatoshi($order->subtotal_cents, $rate);

        return new PaymentInstruction(
            method: $this->method(),
            address: $address,
            expectedAtomic: $sats,
            derivationIndex: $index,
            fingerprint: PaymentLogger::mask($address),
            expiresAt: now()->addSeconds((int) config('payments.expiry_seconds')),
            meta: ['rate' => $rate, 'currency' => strtoupper($order->currency)],
        );
    }

    /** Legacy: floor(($price / $rate) * 100000000), float maths kept on purpose. */
    public static function fiatToSatoshi(int $cents, float $rate): int
    {
        return (int) floor((($cents / 100) / $rate) * self::SATS_PER_BTC);
    }

    public function observe(string $address, int $expectedAtomic): PaymentObservation
    {
        $base = rtrim(config('payments.bitcoin.blockcypher_base'), '/');
        $query = array_filter(['token' => config('payments.bitcoin.blockcypher_token')]);

        $res = Http::timeout(10)->acceptJson()->get("$base/addrs/$address/balance", $query);
        if (! $res->successful() || ! is_array($res->json())) {
            throw new RuntimeException('Chain lookup failed (HTTP ' . $res->status() . ').');
        }
        $data = $res->json();
        $detected = max(0, (int) ($data['final_balance'] ?? 0));   // incl. unconfirmed
        $confirmed = max(0, (int) ($data['balance'] ?? 0));        // >=1 confirmation

        $confirmations = 0;
        $txids = [];
        if ($detected > 0) {
            $confirmations = $confirmed > 0 ? 1 : 0;
            $tx = Http::timeout(10)->acceptJson()->get("$base/addrs/$address", $query + ['limit' => 5]);
            if ($tx->successful()) {
                $min = null;
                foreach ((array) $tx->json('txrefs') as $ref) {
                    if (($ref['tx_input_n'] ?? -1) >= 0) {
                        continue; // spends, not receipts
                    }
                    $c = (int) ($ref['confirmations'] ?? 0);
                    $min = $min === null ? $c : min($min, $c);
                    $txids[] = $ref['tx_hash'] ?? null;
                }
                foreach ((array) $tx->json('unconfirmed_txrefs') as $ref) {
                    if (($ref['tx_input_n'] ?? -1) < 0) {
                        $min = 0;
                        $txids[] = $ref['tx_hash'] ?? null;
                    }
                }
                if ($min !== null) {
                    $confirmations = $min;
                }
            }
        }

        return new PaymentObservation($detected, $confirmed, $confirmations, array_values(array_filter($txids)));
    }

    /**
     * Legacy rate logic: cache <6h returned as-is; else try the 3 APIs in random order
     * (accept 1000 < rate < 1,000,000); else stale cache up to 7 days; else hardcoded fallback.
     */
    public function exchangeRate(string $currency = 'USD'): float
    {
        $currency = strtoupper($currency);
        $key = 'payments.btc_rate.' . $currency;
        $cfg = config('payments.bitcoin');
        $now = time();

        $cached = Cache::get($key);
        $cachedRate = null;
        if (is_array($cached) && isset($cached['rate'], $cached['updated'])) {
            $age = $now - (int) $cached['updated'];
            if ($age < $cfg['rate_usable_seconds']) {
                return (float) $cached['rate'];
            }
            if ($age < $cfg['rate_stale_seconds']) {
                $cachedRate = (float) $cached['rate'];
            }
        }

        $lower = strtolower($currency);
        $apis = [
            ['https://api.coindesk.com/v1/bpi/currentprice.json', fn ($j) => $currency === 'USD' ? ($j['bpi']['USD']['rate_float'] ?? null) : null],
            ['https://blockchain.info/ticker', fn ($j) => $j[$currency]['last'] ?? null],
            ["https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=$lower", fn ($j) => $j['bitcoin'][$lower] ?? null],
        ];
        shuffle($apis);

        foreach ($apis as [$url, $parse]) {
            try {
                $res = Http::timeout(3)->acceptJson()->get($url);
                if ($res->status() === 200) {
                    $rate = $parse($res->json() ?? []);
                    if ($rate && $rate > 1000 && $rate < 1000000) {
                        Cache::put($key, ['rate' => (float) $rate, 'updated' => $now], $cfg['rate_stale_seconds']);

                        return (float) $rate;
                    }
                }
            } catch (\Throwable $e) {
                PaymentLogger::warning('Rate API failed', ['error' => $e->getMessage()]);
            }
        }

        if ($cachedRate) {
            PaymentLogger::warning('Using stale cached BTC rate', ['rate' => $cachedRate]);

            return $cachedRate;
        }

        PaymentLogger::error('All BTC rate APIs failed; using emergency fallback rate');

        return (float) $cfg['fallback_rate'];
    }

    private function instructionFor(Payment $p): PaymentInstruction
    {
        return new PaymentInstruction(
            $p->method, $p->address, $p->expected_atomic, $p->derivation_index,
            PaymentLogger::mask($p->address), $p->expires_at, $p->meta ?? [],
        );
    }
}
