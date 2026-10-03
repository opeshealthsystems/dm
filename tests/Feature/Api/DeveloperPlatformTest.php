<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\DeveloperPlatform\Actions\WebhookSigner;
use App\Modules\DeveloperPlatform\Jobs\DeliverWebhook;
use App\Modules\DeveloperPlatform\Models\ApiKey;
use App\Modules\DeveloperPlatform\Models\WebhookDelivery;
use App\Modules\DeveloperPlatform\Models\WebhookEndpoint;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeveloperPlatformTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(): User
    {
        return User::factory()->create(['role' => 'vendor', 'handle' => 'v' . uniqid(), 'shop_name' => 'Shop']);
    }

    private function actAs(User $u): void
    {
        Passport::actingAs($u, $u->allowedScopes());
    }

    /** @return array{0: string, 1: array} plaintext key and key json */
    private function makeKey(User $u, array $scopes = ['catalog:read', 'orders:read']): array
    {
        $this->actAs($u);
        $res = $this->postJson('/api/v1/developer/keys', ['name' => 'k', 'scopes' => $scopes])->assertCreated();

        return [$res->json('key'), $res->json('data')];
    }

    private function orderFor(User $vendor): Order
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $p = $vendor->products()->create(['title' => 'Item', 'price_cents' => 1000, 'stock' => 5, 'status' => 'active']);
        $this->actAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertCreated();
        $id = $this->postJson('/api/v1/orders', ['shipping_address' => '12 Example Street, Amsterdam', 'payment_method' => 'monero'])
            ->assertCreated()->json('data.0.id');

        return Order::findOrFail($id);
    }

    public function test_key_is_shown_once_and_stored_hashed(): void
    {
        [$plain, $json] = $this->makeKey($this->vendor());

        $this->assertStringStartsWith('dm_live_', $plain);
        $this->assertArrayNotHasKey('key_hash', $json);
        $this->assertArrayNotHasKey('key', $json);
        $row = ApiKey::firstOrFail();
        $this->assertSame(hash('sha256', $plain), $row->key_hash);
        $this->assertNotSame($plain, $row->key_hash);
        $this->assertNull(ApiKey::where('key_hash', $plain)->first());
        $this->getJson('/api/v1/developer/keys')->assertOk()->assertJsonMissingPath('key')->assertJsonMissing(['key' => $plain]);
    }

    public function test_only_catalog_read_and_orders_read_scopes_allowed(): void
    {
        $this->actAs($this->vendor());
        $this->postJson('/api/v1/developer/keys', ['name' => 'k', 'scopes' => ['orders:write']])->assertUnprocessable();
        $this->postJson('/api/v1/developer/keys', ['name' => 'k', 'scopes' => ['admin']])->assertUnprocessable();
    }

    public function test_key_authenticates_and_enforces_scope(): void
    {
        $vendor = $this->vendor();
        $this->orderFor($vendor);
        [$catalogOnly] = $this->makeKey($vendor, ['catalog:read']);
        [$ordersOnly] = $this->makeKey($vendor, ['orders:read']);

        $this->app['auth']->forgetGuards();
        $this->withToken($catalogOnly)->getJson('/api/v1/developer/catalog/products')->assertOk();
        $this->withToken($catalogOnly)->getJson('/api/v1/developer/orders')->assertForbidden();
        $this->withToken($ordersOnly)->getJson('/api/v1/developer/catalog/products')->assertForbidden();
        $this->withToken($ordersOnly)->getJson('/api/v1/developer/orders?role=vendor')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken('dm_live_bogus')->getJson('/api/v1/developer/orders')->assertUnauthorized();
        $this->withToken('')->getJson('/api/v1/developer/orders')->assertUnauthorized();
    }

    public function test_key_cannot_reach_oauth_routes_or_write_routes(): void
    {
        [$plain] = $this->makeKey($this->vendor());
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->postJson('/api/v1/developer/keys', ['name' => 'x', 'scopes' => ['orders:read']])->assertUnauthorized();
        $this->withToken($plain)->postJson('/api/v1/products', [])->assertUnauthorized();
    }

    public function test_revoked_key_is_rejected_and_last_used_is_tracked(): void
    {
        $vendor = $this->vendor();
        [$plain, $json] = $this->makeKey($vendor);
        $this->app['auth']->forgetGuards();

        $this->assertNull(ApiKey::find($json['id'])->last_used_at);
        $this->withToken($plain)->getJson('/api/v1/developer/catalog/products')->assertOk();
        $this->assertNotNull(ApiKey::find($json['id'])->last_used_at);

        $this->actAs($vendor);
        $this->deleteJson("/api/v1/developer/keys/{$json['id']}")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson('/api/v1/developer/catalog/products')->assertUnauthorized();
    }

    public function test_usage_is_counted_per_key_per_day_and_isolated_per_user(): void
    {
        $a = $this->vendor();
        $b = $this->vendor();
        [$plainA] = $this->makeKey($a);
        [$plainB] = $this->makeKey($b);
        $this->app['auth']->forgetGuards();

        foreach (range(1, 3) as $_) {
            $this->withToken($plainA)->getJson('/api/v1/developer/catalog/products')->assertOk();
        }
        $this->withToken($plainB)->getJson('/api/v1/developer/catalog/products')->assertOk();
        $this->withToken('dm_live_nope')->getJson('/api/v1/developer/catalog/products')->assertUnauthorized(); // not counted

        $this->actAs($a);
        $this->getJson('/api/v1/developer/usage')->assertOk()
            ->assertJsonPath('total_requests', 3)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.requests', 3);
        $this->actAs($b);
        $this->getJson('/api/v1/developer/usage')->assertJsonPath('total_requests', 1);
    }

    public function test_other_user_cannot_revoke_or_see_keys_and_webhooks(): void
    {
        $owner = $this->vendor();
        $other = $this->vendor();
        [, $json] = $this->makeKey($owner);
        $this->actAs($owner);
        $hook = $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://example.test/h', 'events' => ['order.paid']])->assertCreated()->json('data.id');

        $this->actAs($other);
        $this->deleteJson("/api/v1/developer/keys/{$json['id']}")->assertForbidden();
        $this->assertNull(ApiKey::find($json['id'])->revoked_at);
        $this->getJson('/api/v1/developer/keys')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/developer/webhooks')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/developer/webhooks/$hook")->assertForbidden();
        $this->putJson("/api/v1/developer/webhooks/$hook", ['active' => false])->assertForbidden();
        $this->deleteJson("/api/v1/developer/webhooks/$hook")->assertForbidden();
        $this->getJson("/api/v1/developer/webhooks/$hook/deliveries")->assertForbidden();
    }

    public function test_buyers_cannot_use_developer_management(): void
    {
        $this->actAs(User::factory()->create(['role' => 'buyer']));
        $this->getJson('/api/v1/developer/keys')->assertForbidden();
    }

    public function test_api_key_cannot_read_another_users_order(): void
    {
        $v1 = $this->vendor();
        $v2 = $this->vendor();
        $order = $this->orderFor($v1);
        [$plain] = $this->makeKey($v2);
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson("/api/v1/developer/orders/{$order->id}")->assertForbidden();
    }

    public function test_webhook_secret_is_shown_once_and_encrypted_at_rest(): void
    {
        $this->actAs($this->vendor());
        $res = $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://example.test/h', 'events' => ['order.paid']])->assertCreated();
        $secret = $res->json('secret');
        $this->assertStringStartsWith('whsec_', $secret);
        $this->assertArrayNotHasKey('secret', $res->json('data'));
        $raw = \DB::table('webhook_endpoints')->value('secret');
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertSame($secret, WebhookEndpoint::first()->secret);
        $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://example.test/h', 'events' => ['bogus']])->assertUnprocessable();
    }

    public function test_signature_is_correct_and_payload_posted_on_order_event(): void
    {
        $vendor = $this->vendor();
        $order = $this->orderFor($vendor);
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->actAs($vendor);
        $secret = $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://example.test/h', 'events' => ['order.paid']])->json('secret');

        event(new OrderPaid($order));

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $r) use ($secret, $order) {
            [$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $r->header('X-DM-Signature')[0]));
            $this->assertSame(hash_hmac('sha256', $t . '.' . $r->body(), $secret), $v1);
            $this->assertSame($v1, WebhookSigner::sign($secret, (int) $t, $r->body()));
            $this->assertNotSame($v1, WebhookSigner::sign('wrong', (int) $t, $r->body()));
            $body = json_decode($r->body(), true);

            return $r->url() === 'https://example.test/h' && $body['type'] === 'order.paid' && $body['data']['id'] === $order->id
                && $r->header('X-DM-Event')[0] === 'order.paid';
        });
        $d = WebhookDelivery::firstOrFail();
        $this->assertSame('delivered', $d->status);
        $this->assertSame(200, $d->response_code);
        $this->actAs($vendor);
        $this->getJson('/api/v1/developer/webhooks/' . $d->webhook_endpoint_id . '/deliveries')->assertOk()->assertJsonPath('data.0.status', 'delivered');
    }

    public function test_events_only_go_to_the_orders_vendor_and_subscribed_events(): void
    {
        $v1 = $this->vendor();
        $v2 = $this->vendor();
        $order = $this->orderFor($v1);
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->actAs($v2);
        $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://example.test/other', 'events' => ['order.paid']])->assertCreated();
        $this->actAs($v1);
        $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://example.test/mine', 'events' => ['order.shipped']])->assertCreated();

        event(new OrderPaid($order));

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_failed_delivery_is_retried_with_exponential_backoff_then_succeeds(): void
    {
        $vendor = $this->vendor();
        $order = $this->orderFor($vendor);
        $this->actAs($vendor);
        $this->postJson('/api/v1/developer/webhooks', ['url' => 'https://example.test/h', 'events' => ['order.paid']])->assertCreated();

        Http::fake(['*' => Http::sequence()->push('boom', 500)->push('ok', 200)]);
        \Illuminate\Support\Facades\Queue::fake();
        event(new OrderPaid($order));
        \Illuminate\Support\Facades\Queue::assertPushed(DeliverWebhook::class, 1);
        $delivery = WebhookDelivery::firstOrFail();
        try {
            (new DeliverWebhook($delivery->id))->handle(); // first attempt throws so the queue retries it
            $this->fail('expected the job to throw');
        } catch (\RuntimeException) {
        }
        $delivery->refresh();
        $this->assertSame('retrying', $delivery->status);
        $this->assertSame(500, $delivery->response_code);
        $this->assertSame(1, $delivery->attempts);

        $job = new DeliverWebhook($delivery->id);
        $this->assertSame(6, $job->tries);
        $b = $job->backoff();
        $this->assertSame([10, 60, 300, 1800, 7200], $b);
        for ($i = 1; $i < count($b); $i++) {
            $this->assertGreaterThan($b[$i - 1], $b[$i]);
        }

        $job->handle();
        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(2, $delivery->attempts);

        // already-delivered deliveries are not re-sent
        $job->handle();
        $this->assertSame(2, $delivery->refresh()->attempts);
    }

    public function test_exhausted_retries_mark_delivery_failed(): void
    {
        $vendor = $this->vendor();
        $endpoint = WebhookEndpoint::create(['user_id' => $vendor->id, 'url' => 'https://example.test/h', 'secret' => 's', 'events' => ['order.paid']]);
        $d = WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event_id' => (string) \Str::uuid(), 'event' => 'order.paid', 'payload' => ['x' => 1]]);

        (new DeliverWebhook($d->id))->failed(new \RuntimeException('x'));

        $this->assertSame('failed', $d->refresh()->status);
    }

    public function test_network_error_is_recorded_and_rethrown_for_retry(): void
    {
        $vendor = $this->vendor();
        $endpoint = WebhookEndpoint::create(['user_id' => $vendor->id, 'url' => 'https://example.test/h', 'secret' => 's', 'events' => ['order.paid']]);
        $d = WebhookDelivery::create(['webhook_endpoint_id' => $endpoint->id, 'event_id' => (string) \Str::uuid(), 'event' => 'order.paid', 'payload' => ['x' => 1]]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->expectException(\Illuminate\Http\Client\ConnectionException::class);
        try {
            (new DeliverWebhook($d->id))->handle();
        } finally {
            $this->assertSame('retrying', $d->refresh()->status);
        }
    }
}
