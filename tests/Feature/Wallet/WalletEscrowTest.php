<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Modules\Escrow\Models\Dispute;
use App\Modules\Escrow\Models\VendorFee;
use App\Modules\Escrow\Models\VendorFeeTier;
use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderRefunded;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use App\Modules\Wallet\Actions\CreditVendorForOrder;
use App\Modules\Wallet\Actions\Ledger;
use App\Modules\Wallet\Models\PayoutRequest;
use App\Modules\Wallet\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;

class WalletEscrowTest extends TestCase
{
    use RefreshDatabase;

    private const BTC = 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq';
    private const XMR = '44AFFq5kSiGBoZ4NMDwYtN18obc8AemS33DBLWs3H7otXft3XjrpDtQGv7SqSsaBYBb98uNbr2VBBEt7f2wfn3RVGQBEP3A';

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'handle' => 'u' . uniqid(), 'shop_name' => 'S']);
    }

    private function actAs(User $u): void
    {
        Passport::actingAs($u, $u->allowedScopes());
    }

    private function order(User $buyer, User $vendor, int $cents = 10000, string $status = 'shipped'): Order
    {
        $o = new Order();
        $o->forceFill([
            'number' => 'DM' . uniqid(), 'buyer_id' => $buyer->id, 'vendor_id' => $vendor->id, 'currency' => 'EUR',
            'subtotal_cents' => $cents, 'payment_method' => 'monero', 'status' => $status,
            'escrow_status' => 'held', 'shipment_status' => $status === 'shipped' ? 'shipped' : 'pending',
            'shipping_address' => 'Somewhere 1',
        ])->save();

        return $o->refresh();
    }

    private function sold(int $cents = 10000): array
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v, $cents);
        app(OrderLifecycle::class)->confirmReceipt($o);

        return [$b, $v, $o];
    }

    // ---- ledger ----------------------------------------------------------

    public function test_order_completion_credits_vendor_minus_default_five_percent(): void
    {
        [, $v, $o] = $this->sold(10000);

        $ledger = app(Ledger::class);
        $this->assertSame(9500, $ledger->balance($v->id, 'EUR'));
        $this->assertSame(9500, $ledger->computedBalance($v->id, 'EUR'));
        $this->assertSame([10000, -500], WalletEntry::where('order_id', $o->id)->orderBy('id')->pluck('amount_cents')->all());
    }

    public function test_credit_is_idempotent_per_order_even_when_event_replays(): void
    {
        [, $v, $o] = $this->sold(10000);

        event(new OrderCompleted($o->refresh()));
        event(new OrderCompleted($o));
        $this->assertFalse(app(CreditVendorForOrder::class)->handle($o));

        $this->assertSame(9500, app(Ledger::class)->balance($v->id, 'EUR'));
        $this->assertSame(2, WalletEntry::where('user_id', $v->id)->count());
    }

    public function test_commission_rounds_half_up_in_integer_cents(): void
    {
        [, $v] = $this->sold(10); // 5% of 10 = 0.5 -> 1
        $this->assertSame(9, app(Ledger::class)->balance($v->id, 'EUR'));
    }

    public function test_fee_tiers_apply_by_lifetime_sales(): void
    {
        VendorFeeTier::create(['name' => 'Pro', 'min_sales_cents' => 10000, 'commission_bps' => 200]);
        $b = $this->user('buyer');
        $v = $this->user('vendor');

        app(OrderLifecycle::class)->confirmReceipt($this->order($b, $v, 10000)); // lifetime 0 -> 5%
        app(OrderLifecycle::class)->confirmReceipt($this->order($b, $v, 10000)); // lifetime 10000 -> 2%

        $this->assertSame(9500 + 9800, app(Ledger::class)->balance($v->id, 'EUR'));
    }

    public function test_ledger_entries_are_append_only(): void
    {
        [, $v] = $this->sold();
        $entry = WalletEntry::where('user_id', $v->id)->first();

        $this->expectException(\LogicException::class);
        $entry->forceFill(['amount_cents' => 1])->save();
    }

    public function test_ledger_entries_cannot_be_deleted(): void
    {
        [, $v] = $this->sold();
        $this->expectException(\LogicException::class);
        WalletEntry::where('user_id', $v->id)->first()->delete();
    }

    public function test_ledger_refuses_to_go_negative_and_replays_do_not_double_post(): void
    {
        $v = $this->user('vendor');
        $ledger = app(Ledger::class);

        $ledger->post($v->id, 'EUR', WalletEntry::SALE_CREDIT, 1000, 'k1');
        [, $created] = $ledger->post($v->id, 'EUR', WalletEntry::SALE_CREDIT, 1000, 'k1');
        $this->assertFalse($created);
        $this->assertSame(1000, $ledger->balance($v->id, 'EUR'));

        $this->expectException(\App\Modules\Wallet\Exceptions\WalletException::class);
        $ledger->post($v->id, 'EUR', WalletEntry::PAYOUT_DEBIT, -1001, 'k2');
    }

    public function test_wallet_endpoints_show_balance_stats_and_ledger(): void
    {
        [, $v] = $this->sold(10000);
        $this->actAs($v);

        $this->getJson('/api/v1/wallet')->assertOk()
            ->assertJsonPath('data.EUR.available_cents', 9500)
            ->assertJsonPath('data.EUR.total_sales_cents', 10000)
            ->assertJsonPath('data.EUR.commission_cents', 500)
            ->assertJsonPath('data.EUR.commission_bps', 500);
        $this->getJson('/api/v1/wallet/entries')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/wallet/entries?type=platform_fee')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_buyer_cannot_use_wallet_endpoints(): void
    {
        $this->actAs($this->user('buyer'));
        $this->getJson('/api/v1/wallet')->assertForbidden();
        $this->postJson('/api/v1/payouts', [])->assertForbidden();
    }

    // ---- payouts ---------------------------------------------------------

    public function test_payout_request_deducts_balance_and_stores_encrypted_address(): void
    {
        [, $v] = $this->sold(10000);
        $this->actAs($v);

        $res = $this->postJson('/api/v1/payouts', ['amount_cents' => 4000, 'currency' => 'EUR', 'method' => 'monero', 'destination_address' => self::XMR])
            ->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertSame(5500, app(Ledger::class)->balance($v->id, 'EUR'));
        $this->assertNotSame(self::XMR, \DB::table('payout_requests')->value('destination_address'));
        $this->getJson('/api/v1/payouts/' . $res->json('data.id'))->assertOk();
    }

    public function test_payout_cannot_exceed_available_balance_and_cannot_be_requested_twice_over(): void
    {
        [, $v] = $this->sold(10000); // 9500 available
        $this->actAs($v);
        $body = ['currency' => 'EUR', 'method' => 'bitcoin', 'destination_address' => self::BTC];

        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 9501])->assertStatus(409);
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 9000])->assertCreated();
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 9000])->assertStatus(409); // double spend
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 500])->assertCreated();

        $this->assertSame(0, app(Ledger::class)->balance($v->id, 'EUR'));
        $this->assertSame(0, app(Ledger::class)->computedBalance($v->id, 'EUR'));
        $this->assertSame(2, PayoutRequest::count());
    }

    public function test_payout_validates_amount_and_address(): void
    {
        [, $v] = $this->sold();
        $this->actAs($v);
        $ok = ['amount_cents' => 100, 'currency' => 'EUR', 'method' => 'bitcoin', 'destination_address' => self::BTC];

        $this->postJson('/api/v1/payouts', array_merge($ok, ['amount_cents' => 0]))->assertUnprocessable();
        $this->postJson('/api/v1/payouts', array_merge($ok, ['amount_cents' => -5]))->assertUnprocessable();
        $this->postJson('/api/v1/payouts', array_merge($ok, ['amount_cents' => 1.5]))->assertUnprocessable();
        $this->postJson('/api/v1/payouts', array_merge($ok, ['destination_address' => 'not-an-address']))->assertUnprocessable()->assertJsonValidationErrors('destination_address');
        $this->postJson('/api/v1/payouts', array_merge($ok, ['destination_address' => self::XMR]))->assertUnprocessable(); // XMR addr on bitcoin
        $this->postJson('/api/v1/payouts', array_merge($ok, ['method' => 'monero']))->assertUnprocessable(); // BTC addr on monero
        $this->postJson('/api/v1/payouts', array_merge($ok, ['method' => 'paypal']))->assertUnprocessable();
    }

    public function test_admin_approve_pay_flow_and_state_guards(): void
    {
        [, $v] = $this->sold(10000);
        $admin = $this->user('admin');
        $this->actAs($v);
        $id = $this->postJson('/api/v1/payouts', ['amount_cents' => 3000, 'currency' => 'EUR', 'method' => 'monero', 'destination_address' => self::XMR])->json('data.id');

        $this->actAs($admin);
        $this->postJson("/api/v1/admin/payouts/$id/mark-paid", ['txid' => 'abc'])->assertStatus(409); // not approved yet
        $this->postJson("/api/v1/admin/payouts/$id/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/v1/admin/payouts/$id/approve")->assertStatus(409);
        $this->postJson("/api/v1/admin/payouts/$id/mark-paid", [])->assertUnprocessable();
        $this->postJson("/api/v1/admin/payouts/$id/mark-paid", ['txid' => 'abc'])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/v1/admin/payouts/$id/reject", ['note' => 'late'])->assertStatus(409); // cannot undo a paid payout
        $this->assertSame(6500, app(Ledger::class)->balance($v->id, 'EUR'));
        $this->getJson('/api/v1/admin/payouts?status=paid')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_rejected_payout_returns_funds_exactly_once(): void
    {
        [, $v] = $this->sold(10000);
        $admin = $this->user('admin');
        $this->actAs($v);
        $id = $this->postJson('/api/v1/payouts', ['amount_cents' => 3000, 'currency' => 'EUR', 'method' => 'monero', 'destination_address' => self::XMR])->json('data.id');

        $this->actAs($admin);
        $this->postJson("/api/v1/admin/payouts/$id/reject", [])->assertUnprocessable(); // note required
        $this->postJson("/api/v1/admin/payouts/$id/reject", ['note' => 'bad address'])->assertOk();
        $this->postJson("/api/v1/admin/payouts/$id/reject", ['note' => 'again'])->assertStatus(409);

        $this->assertSame(9500, app(Ledger::class)->balance($v->id, 'EUR'));
        $this->assertSame(9500, app(Ledger::class)->computedBalance($v->id, 'EUR'));
    }

    public function test_strangers_and_non_admins_denied_on_payouts(): void
    {
        [, $v] = $this->sold(10000);
        $this->actAs($v);
        $id = $this->postJson('/api/v1/payouts', ['amount_cents' => 100, 'currency' => 'EUR', 'method' => 'monero', 'destination_address' => self::XMR])->json('data.id');

        $other = $this->user('vendor');
        $this->actAs($other);
        $this->getJson("/api/v1/payouts/$id")->assertForbidden();
        $this->getJson('/api/v1/payouts')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/admin/payouts/$id/approve")->assertForbidden(); // token lacks admin scope

        $this->actAs($v);
        $this->postJson("/api/v1/admin/payouts/$id/approve")->assertForbidden();
        $this->getJson('/api/v1/admin/payouts')->assertForbidden();
    }

    // ---- disputes --------------------------------------------------------

    public function test_buyer_opens_dispute_order_is_frozen_and_cannot_be_confirmed_or_shipped(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v);
        $this->actAs($b);

        $this->postJson("/api/v1/orders/{$o->id}/disputes", ['reason' => 'Item never arrived at all'])
            ->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.against_user', $v->id);

        $this->assertSame('disputed', $o->refresh()->status);
        $this->postJson("/api/v1/orders/{$o->id}/confirm")->assertStatus(409);
        $this->assertSame(0, WalletEntry::count());
        $this->postJson("/api/v1/orders/{$o->id}/disputes", ['reason' => 'Second try again please'])->assertStatus(409);
    }

    public function test_vendor_can_open_dispute_and_reason_is_encrypted_at_rest(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v, 5000, 'paid');
        $this->actAs($v);

        $this->postJson("/api/v1/orders/{$o->id}/disputes", ['reason' => 'Buyer is unresponsive on address'])->assertCreated();
        $this->assertStringNotContainsString('unresponsive', \DB::table('disputes')->value('reason'));
        $this->assertSame('paid', Dispute::first()->order_status_before);
    }

    public function test_dispute_needs_held_funds_and_participation(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $stranger = $this->user('buyer');
        $held = $this->order($b, $v);
        $pending = $this->order($b, $v);
        $pending->forceFill(['escrow_status' => 'pending', 'status' => 'pending_payment'])->save();
        [, , $done] = $this->sold();

        $this->actAs($stranger);
        $this->postJson("/api/v1/orders/{$held->id}/disputes", ['reason' => 'I am not a party here'])->assertForbidden();

        $this->actAs($b);
        $this->postJson("/api/v1/orders/{$pending->id}/disputes", ['reason' => 'Not paid so not in escrow'])->assertStatus(409);
        $this->postJson("/api/v1/orders/{$done->id}/disputes", ['reason' => 'Already completed order here'])->assertForbidden(); // other buyer's order
        $this->postJson("/api/v1/orders/{$held->id}/disputes", ['reason' => 'short'])->assertUnprocessable();
    }

    public function test_messages_and_evidence_only_for_parties_and_admin(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v);
        $this->actAs($b);
        $id = $this->postJson("/api/v1/orders/{$o->id}/disputes", ['reason' => 'Parcel was empty on opening'])->json('data.id');

        $this->postJson("/api/v1/disputes/$id/messages", ['body' => 'photo at example.test/1', 'kind' => 'evidence'])->assertCreated();
        $this->actAs($v);
        $this->postJson("/api/v1/disputes/$id/messages", ['body' => 'It was packed fine'])->assertCreated();
        $this->getJson("/api/v1/disputes/$id")->assertOk()->assertJsonCount(2, 'data.messages')->assertJsonPath('data.messages.0.kind', 'evidence');

        $stranger = $this->user('vendor');
        $this->actAs($stranger);
        $this->getJson("/api/v1/disputes/$id")->assertForbidden();
        $this->postJson("/api/v1/disputes/$id/messages", ['body' => 'hi'])->assertForbidden();
        $this->getJson('/api/v1/disputes')->assertOk()->assertJsonCount(0, 'data');

        $this->actAs($this->user('admin'));
        $this->getJson("/api/v1/disputes/$id")->assertOk();
    }

    public function test_resolve_for_buyer_refunds_exactly_once_and_vendor_gets_nothing(): void
    {
        Event::spy();
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v);
        $this->actAs($b);
        $id = $this->postJson("/api/v1/orders/{$o->id}/disputes", ['reason' => 'Never received the item'])->json('data.id');

        $this->actAs($this->user('admin'));
        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'buyer'])->assertUnprocessable(); // note required
        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'buyer', 'resolution' => 'Tracking shows lost'])
            ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.outcome', 'buyer');

        $o->refresh();
        $this->assertSame('refunded', $o->escrow_status);
        $this->assertSame('cancelled', $o->status);
        $this->assertSame(0, WalletEntry::count());
        Event::assertDispatchedTimes(OrderRefunded::class, 1);

        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'vendor', 'resolution' => 'changed my mind'])->assertStatus(409);
        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'buyer', 'resolution' => 'again please'])->assertStatus(409);
        $this->assertSame('refunded', $o->refresh()->escrow_status);
        Event::assertDispatchedTimes(OrderRefunded::class, 1);
    }

    public function test_resolve_for_vendor_releases_escrow_once_and_credits_wallet(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v, 20000);
        $this->actAs($v);
        $id = $this->postJson("/api/v1/orders/{$o->id}/disputes", ['reason' => 'Buyer claims fake non delivery'])->json('data.id');

        $this->actAs($this->user('admin'));
        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'vendor', 'resolution' => 'Delivery proof ok'])->assertOk();

        $o->refresh();
        $this->assertSame('released', $o->escrow_status);
        $this->assertSame('completed', $o->status);
        $this->assertSame('delivered', $o->shipment_status);
        $this->assertSame(19000, app(Ledger::class)->balance($v->id, 'EUR'));

        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'vendor', 'resolution' => 'second time'])->assertStatus(409);
        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'buyer', 'resolution' => 'flip it please'])->assertStatus(409);
        $this->assertSame(19000, app(Ledger::class)->balance($v->id, 'EUR'));
        $this->assertSame(2, WalletEntry::count());

        $this->actAs($b);
        $this->postJson("/api/v1/orders/{$o->id}/confirm")->assertStatus(409); // cannot confirm again
    }

    public function test_non_admin_cannot_resolve(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v);
        $this->actAs($b);
        $id = $this->postJson("/api/v1/orders/{$o->id}/disputes", ['reason' => 'Something went wrong here'])->json('data.id');

        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'buyer', 'resolution' => 'self serve'])->assertForbidden();
        $this->actAs($v);
        $this->postJson("/api/v1/admin/disputes/$id/resolve", ['outcome' => 'vendor', 'resolution' => 'self serve'])->assertForbidden();
        $this->assertSame('held', $o->refresh()->escrow_status);
    }

    // ---- lifecycle -------------------------------------------------------

    public function test_release_to_vendor_and_refund_are_mutually_exclusive_and_single_shot(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $life = app(OrderLifecycle::class);

        $a = $this->order($b, $v);
        $life->releaseToVendor($a);
        $this->expectExceptionObject(new OrderException('Only an order whose funds are held can be refunded.'));
        $life->refund($a);
    }

    public function test_release_after_refund_is_rejected_and_pending_orders_cannot_be_released(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $life = app(OrderLifecycle::class);

        $o = $this->order($b, $v);
        $life->refund($o);
        try {
            $life->releaseToVendor($o);
            $this->fail('released a refunded order');
        } catch (OrderException) {
        }

        $p = $this->order($b, $v);
        $p->forceFill(['escrow_status' => 'pending', 'status' => 'pending_payment'])->save();
        $this->expectException(OrderException::class);
        $life->releaseToVendor($p);
    }

    public function test_release_to_vendor_fires_order_completed_once(): void
    {
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $o = $this->order($b, $v);
        $life = app(OrderLifecycle::class);

        Event::fake([OrderCompleted::class]);
        $life->releaseToVendor($o);
        try {
            $life->releaseToVendor($o);
        } catch (OrderException) {
        }
        Event::assertDispatchedTimes(OrderCompleted::class, 1);
    }

    // ---- fees ------------------------------------------------------------

    public function test_vendor_bond_lifecycle_and_admin_mark_paid(): void
    {
        $v = $this->user('vendor');
        $this->actAs($v);

        $id = $this->postJson('/api/v1/fees', [])->assertCreated()->assertJsonPath('data.amount_cents', 50000)
            ->assertJsonPath('data.status', 'unpaid')->json('data.id');
        $this->postJson('/api/v1/fees', [])->assertStatus(409); // already an open bond
        $this->getJson('/api/v1/fees')->assertOk()->assertJsonPath('bond_paid', false);

        $this->postJson("/api/v1/admin/fees/$id/mark-paid")->assertForbidden();
        $this->actAs($this->user('admin'));
        $this->postJson("/api/v1/admin/fees/$id/mark-paid")->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/v1/admin/fees/$id/mark-paid")->assertOk();

        $this->actAs($v);
        $this->getJson('/api/v1/fees')->assertJsonPath('bond_paid', true);
        $this->postJson('/api/v1/fees', [])->assertStatus(409); // already active
        $this->assertSame(1, VendorFee::count());
        $this->getJson('/api/v1/wallet')->assertOk();
    }

    public function test_admin_manages_fee_tiers_and_vendors_can_read_schedule(): void
    {
        $this->actAs($this->user('vendor'));
        $this->postJson('/api/v1/admin/fee-tiers', ['name' => 'x', 'min_sales_cents' => 0, 'commission_bps' => 1])->assertForbidden();

        $this->actAs($this->user('admin'));
        $tier = $this->postJson('/api/v1/admin/fee-tiers', ['name' => 'Gold', 'min_sales_cents' => 100000, 'commission_bps' => 300])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/fee-tiers', ['name' => 'Dup', 'min_sales_cents' => 100000, 'commission_bps' => 300])->assertUnprocessable();
        $this->postJson('/api/v1/admin/fee-tiers', ['name' => 'Bad', 'min_sales_cents' => 5, 'commission_bps' => 99999])->assertUnprocessable();

        $this->actAs($this->user('vendor'));
        $this->getJson('/api/v1/fee-schedule')->assertOk()->assertJsonPath('default_commission_bps', 500)->assertJsonCount(1, 'tiers');

        $this->actAs($this->user('admin'));
        $this->deleteJson("/api/v1/admin/fee-tiers/$tier")->assertNoContent();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
        $this->postJson('/api/v1/orders/1/disputes', [])->assertUnauthorized();
        $this->postJson('/api/v1/admin/disputes/1/resolve', [])->assertUnauthorized();
    }
}
