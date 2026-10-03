<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\DeveloperPlatform\Jobs\DeliverWebhook;
use App\Modules\DeveloperPlatform\Models\WebhookDelivery;
use App\Modules\DeveloperPlatform\Models\WebhookEndpoint;
use App\Modules\DeveloperPlatform\Support\WebhookUrlGuard;
use App\Modules\Escrow\Models\Dispute;
use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Models\Order;
use App\Modules\Wallet\Actions\Ledger;
use App\Modules\Wallet\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Regression tests for the independent security review (docs/SECURITY_REVIEW.md). */
class SecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'buyer'): User
    {
        return User::factory()->create(['role' => $role, 'handle' => 'u' . uniqid(), 'shop_name' => $role === 'vendor' ? 'Shop' : null]);
    }

    private function as(User $u): void
    {
        Passport::actingAs($u, $u->allowedScopes());
    }

    private function order(User $buyer, User $vendor): Order
    {
        $p = $vendor->products()->create(['title' => 'Item', 'price_cents' => 1000, 'stock' => 5, 'status' => 'active']);
        $this->as($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertCreated();
        $id = $this->postJson('/api/v1/orders', ['shipping_address' => '12 Example Street, Amsterdam', 'payment_method' => 'monero'])
            ->assertCreated()->json('data.0.id');

        return Order::findOrFail($id);
    }

    // ---- SSRF -----------------------------------------------------------------------------

    public static function badWebhookUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/hook'],
            'metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'private' => ['https://10.0.0.5/h'],
            'localhost' => ['https://localhost/h'],
            'ipv6 loopback' => ['https://[::1]/h'],
            'ipv4-mapped ipv6' => ['https://[::ffff:127.0.0.1]/h'],
            'ipv6 ula' => ['https://[fd00::1]/h'],
            'decimal ip' => ['http://2130706433/h'],
            'hex ip' => ['http://0x7f000001/h'],
            'short ip' => ['http://127.1/h'],
            'internal tld' => ['https://db.internal/h'],
            'single label' => ['https://redis/h'],
            'credentials' => ['https://user:pw@example.com/h'],
            'odd port' => ['https://example.com:22/h'],
        ];
    }

    #[DataProvider('badWebhookUrls')]
    public function test_webhook_url_to_internal_targets_is_rejected(string $url): void
    {
        $this->as($this->user('vendor'));
        $this->postJson('/api/v1/developer/webhooks', ['url' => $url, 'events' => ['order.paid']])->assertUnprocessable();
    }

    public function test_webhook_hostname_resolving_to_private_address_is_rejected(): void
    {
        WebhookUrlGuard::$resolver = fn () => ['93.184.216.34', '10.1.2.3']; // one bad record is enough
        $this->as($this->user('vendor'));
        $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://evil.example.com/h', 'events' => ['order.paid']])->assertUnprocessable();
    }

    public function test_delivery_rechecks_dns_and_does_not_send_to_private_address(): void
    {
        Http::fake();
        $vendor = $this->user('vendor');
        $ep = WebhookEndpoint::create(['user_id' => $vendor->id, 'url' => 'https://rebind.example.com/h', 'secret' => 's', 'events' => ['order.paid']]);
        $d = WebhookDelivery::create(['webhook_endpoint_id' => $ep->id, 'event_id' => 'e1', 'event' => 'order.paid', 'payload' => ['x' => 1]]);
        WebhookUrlGuard::$resolver = fn () => ['169.254.169.254']; // DNS changed after the URL was saved

        (new DeliverWebhook($d->id))->handle();

        Http::assertNothingSent();
        $this->assertSame('failed', $d->fresh()->status);
    }

    // ---- IDOR -----------------------------------------------------------------------------

    public function test_other_users_cannot_touch_an_order_its_payment_or_its_conversation(): void
    {
        $buyer = $this->user();
        $vendor = $this->user('vendor');
        $order = $this->order($buyer, $vendor);
        $stranger = $this->user();
        $otherVendor = $this->user('vendor');

        foreach ([$stranger, $otherVendor] as $who) {
            $this->as($who);
            $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
            $this->getJson("/api/v1/orders/{$order->id}/payment")->assertForbidden();
            $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();
            $this->postJson("/api/v1/orders/{$order->id}/confirm")->assertForbidden();
            $this->postJson("/api/v1/orders/{$order->id}/ship", ['tracking_number' => 'X'])->assertForbidden();
        }
        $this->as($stranger);
        $this->postJson('/api/v1/conversations', ['order_id' => $order->id, 'body' => 'hi'])->assertForbidden();
        $this->postJson("/api/v1/orders/{$order->id}/disputes", ['reason' => 'not my order at all'])->assertStatus(403);
    }

    public function test_vendor_cannot_read_the_buyers_deposit_address(): void
    {
        $buyer = $this->user();
        $vendor = $this->user('vendor');
        $order = $this->order($buyer, $vendor);

        $this->as($vendor);
        $this->getJson("/api/v1/orders/{$order->id}/payment")->assertForbidden();
    }

    public function test_webhooks_and_api_keys_are_owner_only(): void
    {
        $owner = $this->user('vendor');
        $other = $this->user('vendor');
        $ep = WebhookEndpoint::create(['user_id' => $owner->id, 'url' => 'https://example.com/h', 'secret' => 's', 'events' => ['order.paid']]);
        $this->as($other);
        $this->getJson("/api/v1/developer/webhooks/{$ep->id}")->assertForbidden();
        $this->putJson("/api/v1/developer/webhooks/{$ep->id}", ['url' => 'https://example.com/x'])->assertForbidden();
        $this->deleteJson("/api/v1/developer/webhooks/{$ep->id}")->assertForbidden();
        $this->getJson("/api/v1/developer/webhooks/{$ep->id}/deliveries")->assertForbidden();

        $this->as($owner);
        $keyId = $this->postJson('/api/v1/developer/keys', ['name' => 'k', 'scopes' => ['catalog:read']])->json('data.id');
        $this->as($other);
        $this->deleteJson("/api/v1/developer/keys/{$keyId}")->assertForbidden();
    }

    public function test_buyer_token_with_every_scope_cannot_reach_vendor_or_admin_endpoints(): void
    {
        $buyer = $this->user();
        Passport::actingAs($buyer, ['profile', 'catalog:read', 'catalog:write', 'orders:read', 'orders:write', 'vendor:manage', 'admin']);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/wallet')->assertForbidden();
        $this->postJson('/api/v1/products', ['title' => 'x', 'price_cents' => 100])->assertForbidden();
        $this->postJson('/api/v1/developer/keys', ['name' => 'k', 'scopes' => ['catalog:read']])->assertForbidden();
    }

    // ---- Privilege escalation / mass assignment -------------------------------------------

    public function test_register_and_profile_update_cannot_escalate(): void
    {
        $this->artisan('passport:client', ['--personal' => true, '--name' => 'test', '--provider' => 'users']);
        $this->postJson('/api/v1/auth/register', [
            'name' => 'M', 'email' => 'mallory@example.com', 'password' => 'Str0ngPassw0rd', 'role' => 'admin',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'M', 'email' => 'mallory@example.com', 'password' => 'Str0ngPassw0rd',
            'is_verified_vendor' => true, 'email_verified_at' => now()->toDateString(),
        ])->assertCreated();
        $u = User::where('email', 'mallory@example.com')->first();
        $this->assertSame('buyer', $u->role);
        $this->assertFalse((bool) $u->is_verified_vendor);
        $this->assertNull($u->email_verified_at);

        $this->as($u);
        $this->putJson('/api/v1/auth/me', ['role' => 'admin', 'is_verified_vendor' => true, 'email_verified_at' => now()->toDateString(), 'name' => 'Ok'])->assertOk();
        $u->refresh();
        $this->assertSame('buyer', $u->role);
        $this->assertFalse((bool) $u->is_verified_vendor);
        $this->assertNull($u->email_verified_at);
        $this->assertSame('Ok', $u->name);
    }

    public function test_oauth_password_grant_is_disabled(): void
    {
        $res = $this->postJson('/oauth/token', [
            'grant_type' => 'password', 'client_id' => 'x', 'client_secret' => 'y', 'username' => 'a@b.c', 'password' => 'z', 'scope' => 'admin',
        ]);
        $this->assertContains($res->getStatusCode(), [400, 401]);
        $this->assertArrayNotHasKey('access_token', $res->json() ?? []);
    }

    // ---- Moderation, checkout --------------------------------------------------------------

    public function test_vendor_cannot_reactivate_a_product_a_moderator_archived(): void
    {
        $vendor = $this->user('vendor');
        $vendor->forceFill(['email_verified_at' => now()])->save();
        $p = $vendor->products()->create(['title' => 'Item', 'price_cents' => 1000, 'stock' => 5, 'status' => 'active']);
        $p->forceFill(['status' => 'archived', 'moderated_at' => now(), 'moderation_reason' => 'bad'])->save();

        $this->as($vendor);
        $this->putJson("/api/v1/products/{$p->id}", ['status' => 'active'])->assertForbidden();
        $this->assertSame('archived', $p->fresh()->status);
    }

    public function test_checkout_with_a_deleted_product_is_a_clean_422(): void
    {
        $buyer = $this->user();
        $vendor = $this->user('vendor');
        $p = $vendor->products()->create(['title' => 'Item', 'price_cents' => 1000, 'stock' => 5, 'status' => 'active']);
        $this->as($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertCreated();
        $p->delete();
        $this->getJson('/api/v1/cart');
        $this->postJson('/api/v1/orders', ['shipping_address' => '12 Example Street, Amsterdam', 'payment_method' => 'monero'])->assertUnprocessable();
    }

    // ---- Money integrity -------------------------------------------------------------------

    public function test_payout_cannot_overdraw_or_be_negative_and_reject_refunds_once(): void
    {
        $vendor = $this->user('vendor');
        $vendor->forceFill(['email_verified_at' => now()])->save();
        app(Ledger::class)->post($vendor->id, 'EUR', WalletEntry::SALE_CREDIT, 5000, 'seed:1');
        $addr = 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq';
        $this->as($vendor);

        $body = ['currency' => 'EUR', 'method' => 'bitcoin', 'destination_address' => $addr];
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 5001])->assertStatus(409);
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => -5])->assertUnprocessable();
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 9223372036854775807])->assertUnprocessable();
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 1.5])->assertUnprocessable();
        $id = $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 5000])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/payouts', $body + ['amount_cents' => 1])->assertStatus(409);

        $this->as($this->user('admin'));
        $this->postJson("/api/v1/admin/payouts/{$id}/reject", ['note' => 'no'])->assertOk();
        $this->postJson("/api/v1/admin/payouts/{$id}/reject", ['note' => 'no'])->assertStatus(409);
        $this->assertSame(5000, app(Ledger::class)->balance($vendor->id, 'EUR'));
        $this->assertSame(5000, app(Ledger::class)->computedBalance($vendor->id, 'EUR'));
    }

    public function test_dispute_resolves_exactly_once_and_vendor_is_credited_once(): void
    {
        $buyer = $this->user();
        $vendor = $this->user('vendor');
        $order = $this->order($buyer, $vendor);
        app(OrderLifecycle::class)->markPaid($order);
        app(OrderLifecycle::class)->markPaid($order); // replay is a no-op
        $this->as($buyer);
        $id = $this->postJson("/api/v1/orders/{$order->id}/disputes", ['reason' => 'item never arrived at all'])->assertCreated()->json('data.id');

        $this->as($this->user('admin'));
        $this->postJson("/api/v1/admin/disputes/{$id}/resolve", ['outcome' => 'vendor', 'resolution' => 'fine call'])->assertOk();
        $this->postJson("/api/v1/admin/disputes/{$id}/resolve", ['outcome' => 'buyer', 'resolution' => 'flip it'])->assertStatus(409);

        $this->assertSame('released', $order->fresh()->escrow_status);
        $this->assertSame(1, WalletEntry::where('user_id', $vendor->id)->where('type', WalletEntry::SALE_CREDIT)->count());
        $this->assertSame(Dispute::RESOLVED, Dispute::find($id)->status);
    }

    // ---- Auth hardening --------------------------------------------------------------------

    public function test_changing_password_revokes_other_tokens(): void
    {
        $this->artisan('passport:client', ['--personal' => true, '--name' => 'test', '--provider' => 'users']);
        $u = User::factory()->create(['password' => 'Str0ngPassw0rd1']);
        $old = $u->createToken('old', ['profile']);
        $new = $u->createToken('new', ['profile']);

        $this->withToken($new->accessToken)->postJson('/api/v1/auth/password', [
            'current_password' => 'Str0ngPassw0rd1', 'password' => 'An0therStr0ngOne', 'password_confirmation' => 'An0therStr0ngOne',
        ])->assertOk();

        $this->assertTrue((bool) $old->token->fresh()->revoked);
        $this->assertFalse((bool) $new->token->fresh()->revoked);
    }

    public function test_locale_switch_never_redirects_off_site(): void
    {
        $this->withHeader('Referer', 'https://evil.example/phish')->get('/locale/nl')->assertRedirect(route('home'));
        $this->withHeader('Referer', url('/faq'))->get('/locale/nl')->assertRedirect(url('/faq'));
    }

    public function test_cors_does_not_reflect_arbitrary_origins(): void
    {
        $res = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/v1/products');
        $this->assertNull($res->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_web_group_has_csrf_protection_and_session_cookie_hardening(): void
    {
        $web = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'];
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, $web);
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }
}
