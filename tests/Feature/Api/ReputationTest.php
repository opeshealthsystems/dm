<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReputationTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(): User
    {
        return User::factory()->create(['role' => 'vendor', 'handle' => 'v' . uniqid(), 'shop_name' => 'Shop']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['role' => 'buyer']);
    }

    private function product(User $vendor): Product
    {
        return $vendor->products()->create(['title' => 'Item ' . uniqid(), 'price_cents' => 1000, 'stock' => 10, 'status' => 'active']);
    }

    private function order(User $buyer, Product $product, string $status = Order::STATUS_COMPLETED): Order
    {
        $order = new Order();
        $order->forceFill([
            'number' => 'T' . uniqid(),
            'buyer_id' => $buyer->id,
            'vendor_id' => $product->vendor_id,
            'currency' => 'EUR',
            'subtotal_cents' => 1000,
            'status' => $status,
            'shipping_address' => 'Somewhere 1',
        ])->save();
        $order->items()->create([
            'product_id' => $product->id, 'title' => $product->title,
            'unit_price_cents' => 1000, 'quantity' => 1, 'line_total_cents' => 1000,
        ]);

        return $order;
    }

    private function actAs(User $u): void
    {
        Passport::actingAs($u, $u->allowedScopes());
    }

    private function review(User $buyer, Product $p, Order $o, mixed $rating = 5, ?string $body = 'Great')
    {
        $this->actAs($buyer);

        return $this->postJson("/api/v1/products/{$p->id}/reviews", ['order_id' => $o->id, 'rating' => $rating, 'body' => $body]);
    }

    public function test_buyer_of_completed_order_can_review_and_aggregates_update(): void
    {
        $vendor = $this->vendor();
        $p = $this->product($vendor);
        $b1 = $this->buyer();
        $b2 = $this->buyer();

        $this->review($b1, $p, $this->order($b1, $p), 5)->assertCreated()->assertJsonPath('data.rating', 5);
        $this->review($b2, $p, $this->order($b2, $p), 2, null)->assertCreated();

        $this->getJson("/api/v1/products/{$p->slug}")
            ->assertJsonPath('data.rating_avg', 3.5)->assertJsonPath('data.rating_count', 2);
        $this->getJson("/api/v1/vendors/{$vendor->id}")
            ->assertJsonPath('data.rating_avg', 3.5)->assertJsonPath('data.rating_count', 2);
        $this->getJson("/api/v1/products/{$p->id}/reviews")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/vendors/{$vendor->id}/reviews")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_cannot_review_without_completed_order(): void
    {
        $p = $this->product($this->vendor());
        $buyer = $this->buyer();

        foreach ([Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAID, Order::STATUS_SHIPPED, Order::STATUS_CANCELLED] as $status) {
            $this->review($buyer, $p, $this->order($buyer, $p, $status))->assertStatus(409);
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_cannot_review_without_any_order_or_product_not_in_order(): void
    {
        $vendor = $this->vendor();
        $p = $this->product($vendor);
        $other = $this->product($vendor);
        $buyer = $this->buyer();

        $this->actAs($buyer);
        $this->postJson("/api/v1/products/{$p->id}/reviews", ['rating' => 5])->assertUnprocessable()->assertJsonValidationErrors('order_id');

        $this->review($buyer, $other, $this->order($buyer, $p))->assertUnprocessable();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_cannot_review_twice(): void
    {
        $p = $this->product($this->vendor());
        $buyer = $this->buyer();
        $o1 = $this->order($buyer, $p);
        $o2 = $this->order($buyer, $p);

        $this->review($buyer, $p, $o1)->assertCreated();
        $this->review($buyer, $p, $o1)->assertStatus(409);
        $this->review($buyer, $p, $o2)->assertStatus(409);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_cannot_review_someone_elses_order(): void
    {
        $p = $this->product($this->vendor());
        $owner = $this->buyer();
        $thief = $this->buyer();
        $order = $this->order($owner, $p);

        $this->review($thief, $p, $order)->assertForbidden();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_vendor_cannot_write_review_and_guest_is_rejected(): void
    {
        $vendor = $this->vendor();
        $p = $this->product($vendor);
        $order = $this->order($this->buyer(), $p);

        $this->actAs($vendor);
        $this->postJson("/api/v1/products/{$p->id}/reviews", ['order_id' => $order->id, 'rating' => 5])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/v1/products/{$p->id}/reviews", ['order_id' => $order->id, 'rating' => 5])->assertUnauthorized();
    }

    public function test_rating_must_be_integer_between_1_and_5(): void
    {
        $p = $this->product($this->vendor());
        $buyer = $this->buyer();
        $order = $this->order($buyer, $p);

        foreach ([0, 6, -1, 'abc', 3.5, null] as $bad) {
            $this->review($buyer, $p, $order, $bad)->assertUnprocessable()->assertJsonValidationErrors('rating');
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_vendor_can_reply_once_only_to_own_reviews(): void
    {
        $vendor = $this->vendor();
        $p = $this->product($vendor);
        $buyer = $this->buyer();
        $id = $this->review($buyer, $p, $this->order($buyer, $p))->json('data.id');

        $this->actAs($this->vendor()); // different vendor
        $this->postJson("/api/v1/reviews/{$id}/reply", ['reply' => 'hi'])->assertForbidden();

        $this->actAs($buyer); // buyer lacks vendor:manage scope
        $this->postJson("/api/v1/reviews/{$id}/reply", ['reply' => 'hi'])->assertForbidden();

        $this->actAs($vendor);
        $this->postJson("/api/v1/reviews/{$id}/reply", [])->assertUnprocessable();
        $this->postJson("/api/v1/reviews/{$id}/reply", ['reply' => 'Thanks!'])->assertOk()->assertJsonPath('data.vendor_reply', 'Thanks!');
        $this->postJson("/api/v1/reviews/{$id}/reply", ['reply' => 'Again'])->assertStatus(409);

        $this->getJson("/api/v1/products/{$p->id}/reviews")->assertJsonPath('data.0.vendor_reply', 'Thanks!');
    }

    public function test_helpful_vote_once_per_user_and_not_on_own_review(): void
    {
        $p = $this->product($this->vendor());
        $author = $this->buyer();
        $voter = $this->buyer();
        $id = $this->review($author, $p, $this->order($author, $p))->json('data.id');

        $this->actAs($author);
        $this->postJson("/api/v1/reviews/{$id}/helpful")->assertForbidden();

        $this->actAs($voter);
        $this->postJson("/api/v1/reviews/{$id}/helpful")->assertOk()->assertJsonPath('data.helpful_count', 1);
        $this->postJson("/api/v1/reviews/{$id}/helpful")->assertOk()->assertJsonPath('data.helpful_count', 1);
        $this->assertDatabaseCount('review_helpful_votes', 1);

        $this->deleteJson("/api/v1/reviews/{$id}/helpful")->assertOk()->assertJsonPath('data.helpful_count', 0);
        $this->deleteJson("/api/v1/reviews/{$id}/helpful")->assertOk()->assertJsonPath('data.helpful_count', 0);
    }

    public function test_follow_and_unfollow_vendor(): void
    {
        $vendor = $this->vendor();
        $buyer = $this->buyer();

        $this->actAs($buyer);
        $this->postJson("/api/v1/vendors/{$vendor->id}/follow")->assertOk()->assertJsonPath('following', true);
        $this->postJson("/api/v1/vendors/{$vendor->id}/follow")->assertOk();
        $this->assertDatabaseCount('vendor_follows', 1);
        $this->getJson("/api/v1/vendors/{$vendor->id}")->assertJsonPath('data.followers_count', 1);

        $this->deleteJson("/api/v1/vendors/{$vendor->id}/follow")->assertOk()->assertJsonPath('following', false);
        $this->assertDatabaseCount('vendor_follows', 0);

        $this->postJson("/api/v1/vendors/{$buyer->id}/follow")->assertNotFound(); // not a vendor
        $this->actAs($vendor);
        $this->postJson("/api/v1/vendors/{$vendor->id}/follow")->assertUnprocessable(); // self
    }

    public function test_product_list_aggregates_do_not_cause_n_plus_one(): void
    {
        $vendor = $this->vendor();
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/products')->assertOk();

            return count(DB::getQueryLog());
        };
        $this->product($vendor);
        $few = $count();
        for ($i = 0; $i < 5; $i++) {
            $p = $this->product($vendor);
            $b = $this->buyer();
            $this->review($b, $p, $this->order($b, $p), 4);
            $this->app['auth']->forgetGuards();
        }
        $this->assertSame($few, $count());
        $this->getJson('/api/v1/products')->assertJsonPath('data.0.rating_avg', 4)->assertJsonPath('data.0.rating_count', 1);
    }
}
