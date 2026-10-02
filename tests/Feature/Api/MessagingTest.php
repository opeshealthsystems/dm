<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Models\Notification;
use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'handle' => 'u' . uniqid()]);
    }

    private function actAs(User $u): void
    {
        Passport::actingAs($u, $u->allowedScopes());
    }

    private function order(User $buyer, User $vendor): Order
    {
        $p = $vendor->products()->create(['title' => 'Item', 'price_cents' => 1000, 'stock' => 10, 'status' => 'active']);
        $this->actAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertCreated();
        $id = $this->postJson('/api/v1/orders', ['shipping_address' => '1 Street, Amsterdam', 'payment_method' => 'monero'])
            ->assertCreated()->json('data.0.id');

        return Order::findOrFail($id);
    }

    public function test_general_conversation_between_buyer_and_vendor(): void
    {
        [$buyer, $vendor] = [$this->user('buyer'), $this->user('vendor')];
        $this->actAs($buyer);
        $id = $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id, 'body' => 'Hello?'])
            ->assertCreated()->json('data.id');

        $this->actAs($vendor);
        $this->getJson('/api/v1/conversations/unread-count')->assertJsonPath('unread_count', 1);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->getJson("/api/v1/conversations/{$id}")->assertOk()->assertJsonPath('data.0.body', 'Hello?');
        $this->getJson('/api/v1/conversations/unread-count')->assertJsonPath('unread_count', 0);
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'Hi!'])->assertCreated();

        // Read receipt visible to the buyer: vendor has read up to the first message.
        $this->actAs($buyer);
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 1);
        $c = $this->postJson("/api/v1/conversations/{$id}/read")->assertOk()->json('data.participants');
        $this->assertSame(2, collect($c)->firstWhere('user_id', $vendor->id)['last_read_message_id'] > 0 ? 2 : 0);

        // Same pair reuses the conversation.
        $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id, 'body' => 'again'])->assertCreated();
        $this->assertSame(1, Conversation::count());
    }

    public function test_strangers_cannot_read_write_or_mark_read(): void
    {
        [$buyer, $vendor, $stranger] = [$this->user('buyer'), $this->user('vendor'), $this->user('buyer')];
        $this->actAs($buyer);
        $id = $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id, 'body' => 'secret'])->json('data.id');

        $this->actAs($stranger);
        $this->getJson("/api/v1/conversations/{$id}")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/conversations/{$id}/read")->assertForbidden();
        $this->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');
        $this->assertSame(1, Message::count());
    }

    public function test_cannot_start_order_conversation_for_someone_elses_order(): void
    {
        [$buyer, $vendor, $stranger] = [$this->user('buyer'), $this->user('vendor'), $this->user('buyer')];
        $order = $this->order($buyer, $vendor);

        $this->actAs($stranger);
        $this->postJson('/api/v1/conversations', ['order_id' => $order->id, 'body' => 'hi'])->assertForbidden();

        $this->actAs($vendor);
        $this->postJson('/api/v1/conversations', ['order_id' => $order->id, 'body' => 'hi buyer'])->assertCreated();
    }

    public function test_unauthenticated_and_missing_scope_denied(): void
    {
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
        $u = $this->user('buyer');
        Passport::actingAs($u, ['profile']);
        $this->getJson('/api/v1/notifications')->assertForbidden();
    }

    public function test_order_events_create_notifications_and_system_messages(): void
    {
        [$buyer, $vendor] = [$this->user('buyer'), $this->user('vendor')];
        $order = $this->order($buyer, $vendor);
        $this->assertSame(1, Notification::where('user_id', $buyer->id)->where('type', 'order.placed')->count());
        $this->assertSame(1, Notification::where('user_id', $vendor->id)->where('type', 'order.placed')->count());

        $lifecycle = app(OrderLifecycle::class);
        $lifecycle->markPaid($order);
        $lifecycle->markPaid($order->fresh()); // idempotent: no duplicate event
        $lifecycle->ship($order->fresh(), 'TRACK1');
        $lifecycle->confirmReceipt($order->fresh());

        foreach (['order.placed', 'order.paid', 'order.shipped', 'order.completed'] as $type) {
            foreach ([$buyer, $vendor] as $u) {
                $this->assertSame(1, Notification::where('user_id', $u->id)->where('type', $type)->count(), "$type for user {$u->id}");
            }
        }
        $conversation = Conversation::where('order_id', $order->id)->firstOrFail();
        $this->assertCount(2, $conversation->participants);
        $system = $conversation->messages()->where('type', 'system')->orderBy('id')->get();
        $this->assertCount(4, $system);
        $this->assertNull($system[0]->sender_id);

        $this->actAs($buyer);
        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_cancel_event_notifies_both(): void
    {
        [$buyer, $vendor] = [$this->user('buyer'), $this->user('vendor')];
        $order = $this->order($buyer, $vendor);
        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();

        foreach ([$buyer, $vendor] as $u) {
            $this->assertSame(1, Notification::where('user_id', $u->id)->where('type', 'order.cancelled')->count());
        }
        $this->assertTrue(Conversation::where('order_id', $order->id)->firstOrFail()->messages()
            ->get()->contains(fn ($m) => str_contains($m->body, 'cancelled')));
    }

    public function test_notifications_list_mark_read_and_mark_all_read(): void
    {
        [$buyer, $vendor] = [$this->user('buyer'), $this->user('vendor')];
        $order = $this->order($buyer, $vendor);
        app(OrderLifecycle::class)->markPaid($order);

        $this->actAs($buyer);
        $list = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 2);
        $first = $list->json('data.0.id');

        $this->postJson("/api/v1/notifications/{$first}/read")->assertOk();
        $this->assertNotNull(Notification::find($first)->read_at);
        $this->getJson('/api/v1/notifications?unread=1')->assertJsonCount(1, 'data');

        // Another user's notification cannot be touched.
        $theirs = Notification::where('user_id', $vendor->id)->first();
        $this->postJson("/api/v1/notifications/{$theirs->id}/read")->assertForbidden();

        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('marked', 1);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_blocking_prevents_messages_both_ways_but_not_system_messages(): void
    {
        [$buyer, $vendor] = [$this->user('buyer'), $this->user('vendor')];
        $this->actAs($buyer);
        $id = $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id, 'body' => 'hi'])->json('data.id');

        $this->actAs($vendor);
        $this->postJson("/api/v1/users/{$vendor->id}/block")->assertUnprocessable(); // not self
        $this->postJson("/api/v1/users/{$buyer->id}/block")->assertCreated();
        $this->getJson('/api/v1/blocks')->assertJsonPath('data.0', $buyer->id);
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'x'])->assertForbidden();

        $this->actAs($buyer);
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'x'])->assertForbidden();
        $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id, 'body' => 'x'])->assertForbidden();

        // Order system messages still flow.
        $order = $this->order($buyer, $vendor);
        $this->assertTrue(Conversation::where('order_id', $order->id)->firstOrFail()->messages()->where('type', 'system')->exists());

        $this->actAs($vendor);
        $this->deleteJson("/api/v1/users/{$buyer->id}/block")->assertOk();
        $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => 'back'])->assertCreated();
    }

    public function test_message_body_is_encrypted_at_rest(): void
    {
        [$buyer, $vendor] = [$this->user('buyer'), $this->user('vendor')];
        $this->actAs($buyer);
        $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id, 'body' => 'plain-text-marker-123'])->assertCreated();

        $raw = DB::table('messages')->value('body');
        $this->assertStringNotContainsString('plain-text-marker-123', $raw);
        $this->assertSame('plain-text-marker-123', Message::first()->body);
    }

    public function test_message_sending_is_rate_limited(): void
    {
        Cache::flush();
        [$buyer, $vendor] = [$this->user('buyer'), $this->user('vendor')];
        $this->actAs($buyer);
        $id = $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id, 'body' => 'm0'])->json('data.id');

        $codes = [];
        for ($i = 1; $i <= 25; $i++) {
            $codes[] = $this->postJson("/api/v1/conversations/{$id}/messages", ['body' => "m$i"])->status();
        }
        $this->assertContains(429, $codes);
        $this->assertSame(201, $codes[0]);
    }

    public function test_validation(): void
    {
        $vendor = $this->user('vendor');
        $this->actAs($this->user('buyer'));
        $this->postJson('/api/v1/conversations', ['body' => 'x'])->assertUnprocessable();
        $this->postJson('/api/v1/conversations', ['recipient_id' => $vendor->id])->assertUnprocessable();
    }
}
