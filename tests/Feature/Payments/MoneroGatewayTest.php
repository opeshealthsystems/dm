<?php

namespace Tests\Feature\Payments;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Gateways\Monero\MoneroException;
use App\Modules\Payments\Gateways\Monero\MoneroHealth;
use App\Modules\Payments\Gateways\Monero\MoneroRate;
use App\Modules\Payments\Gateways\Monero\MoneroRpc;
use App\Modules\Payments\Gateways\MoneroGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Characterization tests pinning the legacy Monero behaviour. No database, no network. */
class MoneroGatewayTest extends TestCase
{
    private const ADDR = "8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA";

    private array $cfg;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->cfg = array_merge(config('monero'), [
            'rpc_url' => 'http://wallet.test/json_rpc', 'retry_attempts' => 2, 'retry_delay_ms' => 0,
            'required_confirmations' => 10, 'cache_integrity_key' => 'test-key',
        ]);
    }

    private function gateway(): MoneroGateway
    {
        return new MoneroGateway($this->cfg);
    }

    private function order(int $id = 7, int $cents = 15000, string $cur = 'USD'): Order
    {
        $o = new Order;
        $o->forceFill(['id' => $id, 'subtotal_cents' => $cents, 'currency' => $cur]);

        return $o;
    }

    private function rpcFake(array $transfers, int $major = 0, int $minor = 5): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'wallet.test/*' => function ($request) use ($transfers, $major, $minor) {
                $m = $request['method'];
                $result = match ($m) {
                    'create_address' => ['address' => self::ADDR, 'address_index' => 5],
                    'get_address_index' => ['index' => ['major' => $major, 'minor' => $minor]],
                    'get_transfers' => $transfers,
                    'get_version' => ['version' => 65563],
                };

                return Http::response(['jsonrpc' => '2.0', 'id' => '0', 'result' => $result]);
            },
            'api.coingecko.com/*' => Http::response(['monero' => ['usd' => 150]]),
        ]);
    }

    public function test_piconero_math_is_legacy_round_8_decimals(): void
    {
        // $150.00 at 150 USD/XMR = 1 XMR
        $this->assertSame(1_000_000_000_000, MoneroGateway::toPiconero(15000, 150.0));
        // 100/157.33 = 0.63560...; legacy round(.., 8) = 0.6356 -> check against explicit rounding
        $xmr = round(100.0 / 157.33, 8);
        $this->assertSame((int) round($xmr * 1e8) * 10000, MoneroGateway::toPiconero(10000, 157.33));
        $this->assertSame(0, MoneroGateway::toPiconero(1, 1_000_000_000.0) % 10000);
    }

    public function test_create_payment_makes_subaddress_per_order_with_fingerprint(): void
    {
        $this->rpcFake([]);
        $i = $this->gateway()->createPayment($this->order());

        $this->assertSame('monero', $i->method);
        $this->assertSame(self::ADDR, $i->address);
        $this->assertSame(1_000_000_000_000, $i->expectedAtomic);
        $this->assertSame(5, $i->derivationIndex);
        $this->assertSame(substr(self::ADDR, 0, 6).'...'.substr(self::ADDR, -6), $i->fingerprint);
        $this->assertNotNull($i->expiresAt);
        Http::assertSent(function ($r) {
            $b = json_decode($r->body(), true) ?: [];

            return ($b["method"] ?? null) === "create_address" && $b["params"]["account_index"] === 0 && $b["params"]["label"] === "Order #7";
        });
    }

    public function test_create_payment_is_idempotent_per_order(): void
    {
        $this->rpcFake([]);
        $g = $this->gateway();
        $a = $g->createPayment($this->order());
        $b = $g->createPayment($this->order());

        $this->assertSame($a->address, $b->address);
        $this->assertSame($a->expectedAtomic, $b->expectedAtomic);
        Http::assertSentCount(1 + 1); // one create_address + one rate fetch
    }

    public function test_rate_falls_back_when_provider_down_and_caches_when_up(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.coingecko.com/*' => Http::response('', 500)]);
        $this->assertSame(150.0, (new MoneroRate($this->cfg))->rate('USD'));

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.coingecko.com/*' => Http::response(['monero' => ['usd' => 200]])]);
        $r = new MoneroRate($this->cfg);
        $this->assertSame(200.0, $r->rate('USD'));
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.coingecko.com/*' => Http::response('', 500)]);
        $this->assertSame(200.0, $r->rate('USD')); // served from signed cache

        $this->expectException(MoneroException::class);
        $r->rate('EUR'); // no fallback for non-USD
    }

    public function test_tampered_rate_cache_is_ignored(): void
    {
        Cache::put('monero:rate:usd', ['rate' => 1, 'updated' => time(), 'signature' => 'forged'], 600);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.coingecko.com/*' => Http::response(['monero' => ['usd' => 160]])]);
        $this->assertSame(160.0, (new MoneroRate($this->cfg))->rate('USD'));
    }

    public function test_observe_confirmation_threshold(): void
    {
        $this->rpcFake(['in' => [['txid' => 'a1', 'amount' => 1_000_000_000_000, 'confirmations' => 9]]]);
        $o = $this->gateway()->observe(self::ADDR, 1_000_000_000_000);
        $this->assertSame(1_000_000_000_000, $o->detectedAtomic);
        $this->assertSame(0, $o->confirmedAtomic);
        $this->assertSame(9, $o->confirmations);

        $this->rpcFake(['in' => [['txid' => 'a1', 'amount' => 1_000_000_000_000, 'confirmations' => 10]]]);
        $o = $this->gateway()->observe(self::ADDR, 1_000_000_000_000);
        $this->assertSame(1_000_000_000_000, $o->confirmedAtomic);
        $this->assertSame(['a1'], $o->txids);
    }

    public function test_observe_underpayment_reports_less_than_expected(): void
    {
        $this->rpcFake(['in' => [['txid' => 'b', 'amount' => 400_000_000_000, 'confirmations' => 20]]]);
        $o = $this->gateway()->observe(self::ADDR, 1_000_000_000_000);
        $this->assertSame(400_000_000_000, $o->detectedAtomic);
        $this->assertTrue($o->confirmedAtomic < 1_000_000_000_000);
    }

    public function test_observe_twice_and_duplicate_txs_do_not_double_count(): void
    {
        $t = ['txid' => 'c', 'amount' => 500_000_000_000, 'confirmations' => 12];
        $this->rpcFake(['in' => [$t, $t], 'pool' => [['txid' => 'd', 'amount' => 1, 'confirmations' => 0]]]);
        $g = $this->gateway();
        $first = $g->observe(self::ADDR, 1);
        $second = $g->observe(self::ADDR, 1);

        $this->assertEquals($first, $second);
        $this->assertSame(500_000_000_001, $first->detectedAtomic);
        $this->assertSame(500_000_000_000, $first->confirmedAtomic);
        $this->assertSame(0, $first->confirmations); // oldest/least-confirmed relevant tx
    }

    public function test_observe_rejects_address_from_another_account(): void
    {
        $this->rpcFake([], major: 3);
        $this->expectException(MoneroException::class);
        $this->gateway()->observe(self::ADDR, 1);
    }

    public function test_observe_ignores_double_spend_and_foreign_address_transfers(): void
    {
        $this->rpcFake(['in' => [
            ['txid' => 'e', 'amount' => 9, 'confirmations' => 99, 'double_spend_seen' => true],
            ['txid' => 'f', 'amount' => 9, 'confirmations' => 99, 'address' => '8other'],
        ]]);
        $o = $this->gateway()->observe(self::ADDR, 1);
        $this->assertSame(0, $o->detectedAtomic);
    }

    public function test_rpc_retries_then_fails_with_exception(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['wallet.test/*' => Http::response('boom', 500)]);
        try {
            (new MoneroRpc($this->cfg))->call('get_version');
            $this->fail('expected exception');
        } catch (MoneroException $e) {
            Http::assertSentCount(2); // retry_attempts
        }
    }

    public function test_health_reports_rpc_state(): void
    {
        $this->rpcFake([]);
        $h = new MoneroHealth(new MoneroRpc($this->cfg), new MoneroRate($this->cfg), $this->cfg);
        $this->assertSame('healthy', $h->check()['status']);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['wallet.test/*' => Http::response('', 503)]);
        $this->assertSame('unhealthy', $h->check()['status']);
    }
}
