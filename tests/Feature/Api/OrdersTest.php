<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Models\CartItem;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = '12 Example Street, 1011 AB Amsterdam';

    private function vendor(): User
    {
        return User::factory()->create(['role' => 'vendor', 'handle' => 'v' . uniqid(), 'shop_name' => 'Shop']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['role' => 'buyer']);
    }

    private function product(User $vendor, int $price = 1000, int $stock = 10): Product
    {
        return $vendor->products()->create(['title' => 'Item ' . uniqid(), 'price_cents' => $price, 'stock' => $stock, 'status' => 'active']);
    }

    private function actAs(User $u): void
    {
        Passport::actingAs($u, $u->allowedScopes());
    }

    private function checkout(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/orders', ['shipping_address' => self::ADDRESS, 'payment_method' => 'monero']);
    }

    public function test_cart_add_update_remove_and_totals(): void
    {
        $buyer = $this->buyer();
        $p = $this->product($this->vendor(), 250, 5);
        $this->actAs($buyer);

        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2])->assertCreated()
            ->assertJsonPath('data.0.line_total_cents', 500)->assertJsonPath('totals.EUR', 500);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])
            ->assertJsonPath('data.0.quantity', 3);
        $this->patchJson("/api/v1/cart/items/{$p->id}", ['quantity' => 4])->assertOk()->assertJsonPath('data.0.quantity', 4);
        $this->deleteJson("/api/v1/cart/items/{$p->id}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cannot_add_more_than_stock_or_own_product_or_inactive(): void
    {
        $vendor = $this->vendor();
        $p = $this->product($vendor, 100, 2);
        $draft = $vendor->products()->create(['title' => 'Draft', 'price_cents' => 100, 'status' => 'draft']);

        $this->actAs($this->buyer());
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 3])->assertUnprocessable();
        $this->postJson('/api/v1/cart/items', ['product_id' => $draft->id, 'quantity' => 1])->assertNotFound();

        $vendor->forceFill(['role' => 'buyer'])->save(); // vendor with order scopes tries to buy own item
        $this->actAs($vendor);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertUnprocessable();
    }

    public function test_checkout_splits_per_vendor_snapshots_prices_and_decrements_stock(): void
    {
        $v1 = $this->vendor();
        $v2 = $this->vendor();
        $a = $this->product($v1, 1000, 10);
        $b = $this->product($v1, 500, 10);
        $c = $this->product($v2, 2000, 3);
        $buyer = $this->buyer();
        $this->actAs($buyer);

        foreach ([[$a, 2], [$b, 1], [$c, 1]] as [$prod, $qty]) {
            $this->postJson('/api/v1/cart/items', ['product_id' => $prod->id, 'quantity' => $qty])->assertCreated();
        }

        $res = $this->checkout()->assertCreated();
        $res->assertJsonCount(2, 'data');
        // the response itself must carry the initial state, not just the database
        $res->assertJsonPath('data.0.status', 'pending_payment')->assertJsonPath('data.0.escrow_status', 'pending');

        $orders = Order::with('items')->where('buyer_id', $buyer->id)->get()->keyBy('vendor_id');
        $this->assertSame(2500, $orders[$v1->id]->subtotal_cents);
        $this->assertSame(2000, $orders[$v2->id]->subtotal_cents);
        $this->assertSame('pending_payment', $orders[$v1->id]->status);
        $this->assertSame('pending', $orders[$v1->id]->escrow_status);
        $this->assertSame(8, $a->fresh()->stock);
        $this->assertSame(2, $c->fresh()->stock);
        $this->assertSame(0, CartItem::where('user_id', $buyer->id)->count());

        // price snapshot survives a later price change
        $a->update(['price_cents' => 99999]);
        $this->assertSame(1000, $orders[$v1->id]->items->firstWhere('product_id', $a->id)->unit_price_cents);

        // address is encrypted at rest
        $raw = \DB::table('orders')->where('id', $orders[$v1->id]->id)->value('shipping_address');
        $this->assertStringNotContainsString('Amsterdam', $raw);
    }

    public function test_checkout_rolls_back_entirely_when_one_item_is_out_of_stock(): void
    {
        $vendor = $this->vendor();
        $ok = $this->product($vendor, 100, 5);
        $short = $this->product($vendor, 100, 5);
        $buyer = $this->buyer();
        $this->actAs($buyer);

        $this->postJson('/api/v1/cart/items', ['product_id' => $ok->id, 'quantity' => 1]);
        $this->postJson('/api/v1/cart/items', ['product_id' => $short->id, 'quantity' => 2]);
        $short->update(['stock' => 1]); // someone else bought it meanwhile

        $this->checkout()->assertStatus(409);

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $ok->fresh()->stock);   // untouched: no partial decrement
        $this->assertSame(2, CartItem::where('user_id', $buyer->id)->count()); // cart kept
    }

    public function test_empty_cart_and_bad_payload_are_rejected(): void
    {
        $this->actAs($this->buyer());
        $this->checkout()->assertUnprocessable();
        $this->postJson('/api/v1/orders', ['shipping_address' => 'x', 'payment_method' => 'paypal'])
            ->assertUnprocessable()->assertJsonValidationErrors(['shipping_address', 'payment_method']);
    }

    public function test_full_lifecycle_pay_ship_confirm(): void
    {
        Event::fake([OrderPaid::class]);
        $vendor = $this->vendor();
        $buyer = $this->buyer();
        $p = $this->product($vendor);
        $this->actAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
        $orderId = $this->checkout()->json('data.0.id');
        $order = Order::findOrFail($orderId);

        // cannot ship or confirm before payment
        $this->actAs($vendor);
        $this->postJson("/api/v1/orders/{$orderId}/ship", ['tracking_number' => 'T1'])->assertStatus(409);
        $this->actAs($buyer);
        $this->postJson("/api/v1/orders/{$orderId}/confirm")->assertStatus(409);

        // payment (internal call, as the Payments module will do): idempotent
        $lifecycle = app(OrderLifecycle::class);
        $lifecycle->markPaid($order);
        $lifecycle->markPaid($order);
        $lifecycle->markPaid($order->fresh());
        Event::assertDispatchedTimes(OrderPaid::class, 1);
        $this->assertSame('held', $order->fresh()->escrow_status);

        // vendor ships
        $this->actAs($vendor);
        $this->postJson("/api/v1/orders/{$orderId}/ship", ['tracking_number' => 'TRACK123'])
            ->assertOk()->assertJsonPath('data.shipment_status', 'shipped');
        $this->postJson("/api/v1/orders/{$orderId}/ship", ['tracking_number' => 'again'])->assertStatus(409);

        // buyer confirms; escrow released
        $this->actAs($buyer);
        $this->postJson("/api/v1/orders/{$orderId}/confirm")->assertOk()
            ->assertJsonPath('data.escrow_status', 'released')->assertJsonPath('data.status', 'completed');
        $this->postJson("/api/v1/orders/{$orderId}/confirm")->assertStatus(409); // no double release
    }

    public function test_only_involved_parties_can_see_or_act_on_an_order(): void
    {
        $vendor = $this->vendor();
        $buyer = $this->buyer();
        $p = $this->product($vendor);
        $this->actAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
        $id = $this->checkout()->json('data.0.id');
        app(OrderLifecycle::class)->markPaid(Order::find($id));

        $stranger = $this->buyer();
        $this->actAs($stranger);
        $this->getJson("/api/v1/orders/{$id}")->assertForbidden();
        $this->postJson("/api/v1/orders/{$id}/confirm")->assertForbidden();
        $this->postJson("/api/v1/orders/{$id}/cancel")->assertForbidden();

        $otherVendor = $this->vendor();
        $this->actAs($otherVendor);
        $this->postJson("/api/v1/orders/{$id}/ship", ['tracking_number' => 'X'])->assertForbidden();

        // buyer cannot ship their own order (needs vendor:manage scope)
        $this->actAs($buyer);
        $this->postJson("/api/v1/orders/{$id}/ship", ['tracking_number' => 'X'])->assertForbidden();

        // address visible to buyer and the order's vendor
        $this->getJson("/api/v1/orders/{$id}")->assertOk()->assertJsonPath('data.shipping_address', self::ADDRESS);
        $this->actAs($vendor);
        $this->getJson("/api/v1/orders/{$id}")->assertOk()->assertJsonPath('data.shipping_address', self::ADDRESS);
    }

    public function test_cancel_returns_stock_only_before_payment(): void
    {
        $vendor = $this->vendor();
        $buyer = $this->buyer();
        $p = $this->product($vendor, 100, 5);
        $this->actAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2]);
        $id = $this->checkout()->json('data.0.id');
        $this->assertSame(3, $p->fresh()->stock);

        $this->postJson("/api/v1/orders/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(5, $p->fresh()->stock);
        $this->postJson("/api/v1/orders/{$id}/cancel")->assertStatus(409);

        // paid orders can't be cancelled by the buyer
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
        $id2 = $this->checkout()->json('data.0.id');
        app(OrderLifecycle::class)->markPaid(Order::find($id2));
        $this->postJson("/api/v1/orders/{$id2}/cancel")->assertStatus(409);
    }

    public function test_order_lists_are_scoped_to_buyer_or_vendor(): void
    {
        $vendor = $this->vendor();
        $buyer = $this->buyer();
        $p = $this->product($vendor);
        $this->actAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1]);
        $this->checkout();

        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->actAs($this->buyer());
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->actAs($vendor);
        $this->getJson('/api/v1/orders?role=vendor')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->postJson('/api/v1/orders', [])->assertUnauthorized();
    }
}
