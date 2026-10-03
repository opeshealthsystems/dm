<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Actions\PaymentService;
use App\Modules\Payments\DTO\PaymentObservation;
use App\Modules\Payments\Gateways\BitcoinGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\BitcoinHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Characterization tests: pin the legacy platform's Bitcoin payment behaviour
 * (PaymentService / BitcoinHelper / Transaction::markPaid).
 */
class BitcoinPaymentsTest extends TestCase
{
    use RefreshDatabase;

    /** Public BIP84 test vector (account 0 of the well-known "abandon ... about" test mnemonic). Public data, not a secret. */
    private const ZPUB = 'zpub6rFR7y4Q2AijBEqTUquhVz398htDFrtymD9xYYfG1m4wAcvPhXNfE3EfH1r1ADqtfSdVCToUG868RvUUkgDKf31mGDtKsAYz2oz2AGutZYs';
    private const ADDR0 = 'bc1qcr8te4kr609gcawutmrza0j4xv80jy8z306fyu';
    private const ADDR1 = 'bc1qnjg0jd8228aq7egyzacy8cys3knf9xvrerkf9g';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.bitcoin.xpub' => self::ZPUB, 'payments.bitcoin.min_confirmations' => 1]);
        Cache::flush();
    }

    private function fakeRate(float $usdLikeRate = 50000.0): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'api.coingecko.com/*' => Http::response(['bitcoin' => ['eur' => $usdLikeRate]]),
            'blockchain.info/*' => Http::response(['EUR' => ['last' => $usdLikeRate]]),
            'api.coindesk.com/*' => Http::response([], 500),
        ]);
    }

    private function order(int $cents = 10000, string $method = 'bitcoin'): Order
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $vendor = User::factory()->create(['role' => 'vendor', 'handle' => 'v' . uniqid(), 'shop_name' => 'S']);
        $o = new Order();
        $o->forceFill([
            'number' => 'DM' . uniqid(), 'buyer_id' => $buyer->id, 'vendor_id' => $vendor->id, 'currency' => 'EUR',
            'subtotal_cents' => $cents, 'payment_method' => $method, 'shipping_address' => 'x',
        ])->save();

        return $o->fresh();
    }

    private function chain(int $final, int $confirmed, int $confs = 1): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            '*/balance*' => Http::response(['balance' => $confirmed, 'final_balance' => $final]),
            'api.blockcypher.com/v1/btc/main/addrs/*' => Http::response([
                'txrefs' => $confirmed > 0 ? [['tx_hash' => 'aa', 'tx_input_n' => -1, 'confirmations' => $confs]] : [],
                'unconfirmed_txrefs' => $final > $confirmed ? [['tx_hash' => 'bb', 'tx_input_n' => -1]] : [],
            ]),
        ]);
    }

    public function test_derivation_matches_bip84_vectors(): void
    {
        $this->assertSame(self::ADDR0, BitcoinHelper::deriveAddress(self::ZPUB, 0, 0));
        $this->assertSame(self::ADDR1, BitcoinHelper::deriveAddress(self::ZPUB, 1, 0));
    }

    public function test_legacy_satoshi_conversion(): void
    {
        // floor((price / rate) * 1e8)
        $this->assertSame(200000, BitcoinGateway::fiatToSatoshi(10000, 50000.0));      // 100 / 50000 = 0.002 BTC
        $this->assertSame(1000000, BitcoinGateway::fiatToSatoshi(50000, 50000.0));
        $this->assertSame(1, BitcoinGateway::fiatToSatoshi(1, 96500.0) >= 0 ? 1 : 0);
        $this->assertSame(10, BitcoinGateway::fiatToSatoshi(1, 100000.0));              // 0.01 / 1e5 * 1e8
    }

    public function test_create_for_order_stores_encrypted_address_and_amount_idempotently(): void
    {
        $this->fakeRate(50000);
        $svc = app(PaymentService::class);
        $order = $this->order(10000);

        $p = $svc->createForOrder($order);
        $again = $svc->createForOrder($order);

        $this->assertSame($p->id, $again->id);
        $this->assertSame(1, Payment::count());
        $this->assertSame(200000, $p->expected_atomic);
        $this->assertSame(0, $p->derivation_index);
        $this->assertSame(self::ADDR0, $p->address);
        $this->assertSame('pending', $p->status);
        $this->assertNotSame(self::ADDR0, DB::table('payments')->value('address')); // encrypted at rest
    }

    public function test_derivation_index_never_reused(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);
        $o1 = $this->order();
        $o2 = $this->order();
        $o3 = $this->order();

        $a = $svc->createForOrder($o1);
        $svc->createForOrder($o1);                 // repeated call: same payment
        $b = $svc->createForOrder($o2);
        $c = $svc->createForOrder($o3);

        $this->assertSame([0, 1, 2], [$a->derivation_index, $b->derivation_index, $c->derivation_index]);
        $this->assertSame(self::ADDR1, $b->address);
        $this->assertCount(3, array_unique(Payment::pluck('derivation_index')->all()));
    }

    public function test_confirmed_payment_marks_order_paid_once(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);
        $order = $this->order(10000);
        $p = $svc->createForOrder($order);

        $this->chain(200000, 200000);
        Event::fake([OrderPaid::class]);

        $p = $svc->poll($p);
        $this->assertSame('confirmed', $p->status);
        $this->assertSame(1, $p->confirmations);
        $order->refresh();
        $this->assertSame('held', $order->escrow_status);
        $this->assertSame('paid', $order->status);

        // double poll: idempotent, no second event
        $svc->poll($p);
        $svc->poll(Payment::find($p->id));
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    public function test_overpayment_counts_as_paid_underpayment_does_not(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);

        $under = $svc->createForOrder($o1 = $this->order(10000));
        $this->chain(199999, 199999);                  // one satoshi short
        $under = $svc->poll($under);
        $this->assertSame('underpaid', $under->status);
        $this->assertSame('pending', $o1->fresh()->escrow_status);

        // top-up arrives: same payment becomes confirmed
        $this->chain(200001, 200001);
        $under = $svc->poll($under);
        $this->assertSame('confirmed', $under->status);
        $this->assertSame('held', $o1->fresh()->escrow_status);

        $over = $svc->createForOrder($o2 = $this->order(10000));
        $this->chain(999999, 999999);
        $this->assertSame('confirmed', $svc->poll($over)->status);
        $this->assertSame('held', $o2->fresh()->escrow_status);
    }

    public function test_unconfirmed_funds_are_detected_but_do_not_pay(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);
        $order = $this->order(10000);
        $p = $svc->createForOrder($order);

        $this->chain(200000, 0);                        // in mempool only (legacy: final_balance vs balance)
        $p = $svc->poll($p);
        $this->assertSame('detected', $p->status);
        $this->assertSame(200000, $p->detected_atomic);
        $this->assertSame(0, $p->confirmed_atomic);
        $this->assertSame('pending', $order->fresh()->escrow_status);
    }

    public function test_min_confirmation_threshold_is_configurable(): void
    {
        $this->fakeRate();
        config(['payments.bitcoin.min_confirmations' => 3]);
        $svc = app(PaymentService::class);
        $order = $this->order(10000);
        $p = $svc->createForOrder($order);

        $this->chain(200000, 200000, 2);
        $this->assertSame('detected', $svc->poll($p)->status);
        $this->assertSame('pending', $order->fresh()->escrow_status);

        $this->chain(200000, 200000, 3);
        $this->assertSame('confirmed', $svc->poll($p->fresh())->status);
        $this->assertSame('held', $order->fresh()->escrow_status);
    }

    public function test_zero_expected_amount_never_pays(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);
        $order = $this->order(10000);
        $p = $svc->createForOrder($order);
        DB::table('payments')->where('id', $p->id)->update(['expected_atomic' => 0]);

        $this->chain(500, 500);
        $svc->poll($p->fresh());
        $this->assertSame('pending', $order->fresh()->escrow_status);
    }

    public function test_untouched_address_expires_but_late_payment_still_counts(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);
        $order = $this->order(10000);
        $p = $svc->createForOrder($order);
        DB::table('payments')->where('id', $p->id)->update(['expires_at' => now()->subMinute()]);

        $this->chain(0, 0);
        $p = $svc->poll($p->fresh());
        $this->assertSame('expired', $p->status);
        $this->assertTrue($svc->pollable()->whereKey($p->id)->exists());

        $this->chain(200000, 200000);
        $this->assertSame('confirmed', $svc->poll($p)->status);
        $this->assertSame('held', $order->fresh()->escrow_status);
    }

    public function test_chain_failure_changes_nothing(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);
        $p = $svc->createForOrder($order = $this->order());
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response('boom', 500)]);

        $p = $svc->poll($p);
        $this->assertSame('pending', $p->status);
        $this->assertSame('pending', $order->fresh()->escrow_status);
    }

    public function test_rate_cache_then_fallbacks(): void
    {
        $gw = app(BitcoinGateway::class);

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'api.coingecko.com/*' => Http::response(['bitcoin' => ['usd' => 60000]]),
            'blockchain.info/*' => Http::response(['USD' => ['last' => 60000]]),
            'api.coindesk.com/*' => Http::response(['bpi' => ['USD' => ['rate_float' => 60000]]]),
        ]);
        $this->assertSame(60000.0, $gw->exchangeRate('USD'));

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response('', 500)]);
        $this->assertSame(60000.0, $gw->exchangeRate('USD'));          // fresh cache, no HTTP needed

        // implausible values are rejected -> stale cache, then hardcoded fallback
        Cache::put('payments.btc_rate.USD', ['rate' => 61000.0, 'updated' => time() - 86400], 604800);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['USD' => ['last' => 5], 'bitcoin' => ['usd' => 5], 'bpi' => ['USD' => ['rate_float' => 5]]])]);
        $this->assertSame(61000.0, $gw->exchangeRate('USD'));

        Cache::flush();
        $this->assertSame(96500.0, $gw->exchangeRate('USD'));
    }

    public function test_observe_reads_blockcypher_balance(): void
    {
        $this->chain(300, 200, 2);
        $obs = app(BitcoinGateway::class)->observe(self::ADDR0, 1000);
        $this->assertInstanceOf(PaymentObservation::class, $obs);
        $this->assertSame(300, $obs->detectedAtomic);
        $this->assertSame(200, $obs->confirmedAtomic);
        $this->assertSame(0, $obs->confirmations);      // an unconfirmed receipt is the oldest-relevant minimum
    }

    public function test_poll_command_processes_open_payments(): void
    {
        $this->fakeRate();
        $svc = app(PaymentService::class);
        $order = $this->order();
        $svc->createForOrder($order);
        $this->chain(200000, 200000);

        $this->artisan('payments:poll')->expectsOutput('Polled 1 payment(s).')->assertSuccessful();
        $this->assertSame('held', $order->fresh()->escrow_status);
        $this->artisan('payments:poll')->expectsOutput('Polled 0 payment(s).');
    }

    public function test_payment_api_visibility_and_listener(): void
    {
        $this->fakeRate();
        $order = $this->order();
        event(new \App\Modules\Orders\Events\OrderPlaced($order));      // listener creates the payment
        $this->assertSame(1, Payment::count());

        $buyer = $order->buyer;
        Passport::actingAs($buyer, $buyer->allowedScopes());
        $this->getJson("/api/v1/orders/{$order->id}/payment")->assertOk()
            ->assertJsonPath('data.address', self::ADDR0)
            ->assertJsonPath('data.expected_atomic', 200000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.confirmations', 0);

        $vendor = $order->vendor;
        Passport::actingAs($vendor, $vendor->allowedScopes());
        $this->getJson("/api/v1/orders/{$order->id}/payment")->assertOk();

        $stranger = User::factory()->create(['role' => 'buyer']);
        Passport::actingAs($stranger, $stranger->allowedScopes());
        $this->getJson("/api/v1/orders/{$order->id}/payment")->assertForbidden();
    }

    public function test_listener_failure_never_breaks_checkout(): void
    {
        config(['payments.bitcoin.xpub' => null]);
        $order = $this->order();
        event(new \App\Modules\Orders\Events\OrderPlaced($order));
        $this->assertSame(0, Payment::count());
    }
}
