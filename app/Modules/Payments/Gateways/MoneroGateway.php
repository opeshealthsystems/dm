<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\DTO\PaymentInstruction;
use App\Modules\Payments\DTO\PaymentObservation;
use App\Modules\Payments\Gateways\Monero\MoneroException;
use App\Modules\Payments\Gateways\Monero\MoneroRate;
use App\Modules\Payments\Gateways\Monero\MoneroRpc;
use Illuminate\Support\Facades\Cache;

/**
 * Monero via monero-wallet-rpc. One subaddress per order (create_address, as in legacy
 * MoneroService), amounts in integer piconero.
 *
 * NOTE: the legacy "view-only" PHP derivation (MoneroHelper::deriveSubaddress) and the
 * daemon mempool scanner used keccak stubs instead of ed25519, so they could not produce
 * or detect real payments; they are intentionally not ported. Wallet RPC (the legacy
 * fallback path in MoneroService) is the only address and detection source.
 */
class MoneroGateway implements PaymentGateway
{
    public const PICONERO_PER_XMR = 1_000_000_000_000;

    private array $config;

    private MoneroRpc $rpc;

    private MoneroRate $rate;

    public function __construct(?array $config = null, ?MoneroRpc $rpc = null, ?MoneroRate $rate = null)
    {
        $this->config = $config ?? config('monero');
        $this->rpc = $rpc ?? new MoneroRpc($this->config);
        $this->rate = $rate ?? new MoneroRate($this->config);
    }

    public function method(): string
    {
        return 'monero';
    }

    /** Legacy math: xmr = round(fiat / rate, 8); piconero = xmr * 1e12 (a multiple of 1e4). */
    public static function toPiconero(int $cents, float $rate): int
    {
        if ($rate <= 0) {
            throw new MoneroException('Invalid exchange rate');
        }
        $xmr = round(($cents / 100) / $rate, 8);

        return (int) round($xmr * 1e8) * 10_000;
    }

    /** Short fingerprint the buyer compares against what their wallet shows. */
    public static function fingerprint(string $address, int $chars = 6): string
    {
        return substr($address, 0, $chars).'...'.substr($address, -$chars);
    }

    public function createPayment(Order $order): PaymentInstruction
    {
        $cacheKey = 'monero:payment:'.$order->getKey();

        return Cache::lock($cacheKey.':lock', 30)->block(10, function () use ($order, $cacheKey) {
            $existing = Cache::get($cacheKey);   // idempotent per order
            if (is_array($existing)) {
                return $this->instruction($existing);
            }

            $cents = (int) $order->subtotal_cents;
            if ($cents <= 0) {
                throw new MoneroException("Invalid transaction amount for order #{$order->getKey()}");
            }
            $account = (int) $this->config['account_index'];

            $result = $this->rpc->call('create_address', ['account_index' => $account, 'label' => "Order #{$order->getKey()}"]);
            $address = $result['address'] ?? null;
            if (! is_string($address) || ! $this->looksLikeAddress($address)) {
                throw new MoneroException('Monero wallet RPC returned an invalid address');
            }
            $rate = $this->rate->rate((string) $order->currency);
            $data = [
                'address' => $address,
                'index' => (int) ($result['address_index'] ?? 0),
                'atomic' => self::toPiconero($cents, $rate),
                'rate' => $rate,
                'expires_at' => now()->addHours((int) $this->config['payment_expiration_hours'])->getTimestamp(),
                'account' => $account,
            ];
            Cache::forever($cacheKey, $data);

            return $this->instruction($data);
        });
    }

    public function observe(string $address, int $expectedAtomic): PaymentObservation
    {
        $account = (int) $this->config['account_index'];
        $required = (int) $this->config['required_confirmations'];

        // Match by subaddress: resolve the address to its (account, index) in our wallet.
        $idx = $this->rpc->call('get_address_index', ['address' => $address])['index'] ?? null;
        if (! is_array($idx) || (int) ($idx['major'] ?? -1) !== $account) {
            throw new MoneroException('Address does not belong to this wallet');
        }

        $result = $this->rpc->call('get_transfers', [
            'in' => true, 'pool' => true,
            'account_index' => $account, 'subaddr_indices' => [(int) $idx['minor']],
        ]);

        $detected = 0;
        $confirmed = 0;
        $minConf = null;
        $txids = [];
        $seen = [];
        foreach (array_merge($result['in'] ?? [], $result['pool'] ?? []) as $t) {
            $txid = (string) ($t['txid'] ?? '');
            if ($txid === '' || isset($seen[$txid]) || ! empty($t['double_spend_seen'])) {
                continue;   // observing the same tx twice never double counts
            }
            if (isset($t['address']) && $t['address'] !== $address) {
                continue;
            }
            $seen[$txid] = true;
            $amount = (int) ($t['amount'] ?? 0);
            $conf = (int) ($t['confirmations'] ?? 0);
            $detected += $amount;
            if ($conf >= $required) {
                $confirmed += $amount;
            }
            $minConf = $minConf === null ? $conf : min($minConf, $conf);
            $txids[] = $txid;
        }

        return new PaymentObservation($detected, $confirmed, $minConf ?? 0, $txids);
    }

    private function instruction(array $d): PaymentInstruction
    {
        return new PaymentInstruction(
            method: 'monero',
            address: $d['address'],
            expectedAtomic: $d['atomic'],
            derivationIndex: $d['index'],
            fingerprint: self::fingerprint($d['address'], (int) $this->config['fingerprint_chars']),
            expiresAt: (new \DateTimeImmutable)->setTimestamp($d['expires_at']),
            meta: ['exchange_rate' => $d['rate'], 'account_index' => $d['account'],
                'required_confirmations' => (int) $this->config['required_confirmations']],
        );
    }

    private function looksLikeAddress(string $a): bool
    {
        if (($this->config['network'] ?? 'mainnet') !== 'mainnet') {
            return strlen($a) >= 95;
        }

        return (bool) preg_match('/^[48][1-9A-HJ-NP-Za-km-z]{94}$/', $a);
    }
}
