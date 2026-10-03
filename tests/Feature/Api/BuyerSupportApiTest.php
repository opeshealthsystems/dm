<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/** Endpoints added for the buyer UI: categories, following, profile update, password change. */
class BuyerSupportApiTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(): User
    {
        return User::factory()->create(['role' => 'vendor', 'handle' => 'v' . uniqid(), 'shop_name' => 'Shop ' . uniqid()]);
    }

    private function buyer(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'buyer', 'handle' => 'b' . uniqid()], $attrs));
    }

    private function actAs(User $u): void
    {
        Passport::actingAs($u, $u->allowedScopes());
    }

    public function test_categories_are_public_and_filterable_in_products(): void
    {
        $cat = Category::create(['name' => 'Books', 'slug' => 'books']);
        Category::create(['name' => 'Art', 'slug' => 'art']);
        $v = $this->vendor();
        $v->products()->create(['title' => 'Novel', 'price_cents' => 500, 'stock' => 2, 'status' => 'active', 'category_id' => $cat->id]);
        $v->products()->create(['title' => 'Other', 'price_cents' => 500, 'stock' => 2, 'status' => 'active']);

        $this->getJson('/api/v1/categories')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Art')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'parent_id']]]);

        $this->getJson('/api/v1/products?category_id=' . $cat->id)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_following_lists_only_my_followed_vendors(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        [$a, $b, $c] = [$this->vendor(), $this->vendor(), $this->vendor()];

        $this->actAs($buyer);
        $this->postJson("/api/v1/vendors/{$a->id}/follow")->assertOk();
        $this->postJson("/api/v1/vendors/{$b->id}/follow")->assertOk();
        $this->actAs($other);
        $this->postJson("/api/v1/vendors/{$c->id}/follow")->assertOk();

        $this->actAs($buyer);
        $res = $this->getJson('/api/v1/following')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$b->id, $a->id], array_column($res->json('data'), 'id'));

        $this->deleteJson("/api/v1/vendors/{$b->id}/follow")->assertOk();
        $this->getJson('/api/v1/following')->assertJsonCount(1, 'data');
    }

    public function test_following_requires_login(): void
    {
        $this->getJson('/api/v1/following')->assertUnauthorized();
    }

    public function test_update_profile_changes_only_sent_fields(): void
    {
        $u = $this->buyer(['name' => 'Old Name']);
        $this->actAs($u);

        $this->putJson('/api/v1/auth/me', ['name' => 'New Name', 'handle' => 'fresh_handle'])
            ->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.handle', 'fresh_handle');

        $this->putJson('/api/v1/auth/me', ['name' => 'Third'])->assertOk()->assertJsonPath('data.handle', 'fresh_handle');
        $this->assertSame('Third', $u->fresh()->name);
    }

    public function test_update_profile_validates_and_protects_role_and_email(): void
    {
        $taken = $this->buyer(['handle' => 'taken']);
        $u = $this->buyer();
        $this->actAs($u);

        $this->putJson('/api/v1/auth/me', ['handle' => 'taken'])->assertStatus(422)->assertJsonValidationErrors('handle');
        $this->putJson('/api/v1/auth/me', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson('/api/v1/auth/me', ['handle' => 'bad handle!'])->assertStatus(422);
        $this->putJson('/api/v1/auth/me', ['handle' => $u->handle, 'name' => 'Same'])->assertOk();

        $this->putJson('/api/v1/auth/me', ['role' => 'admin', 'email' => 'x@example.com', 'is_verified_vendor' => true])->assertOk();
        $fresh = $u->fresh();
        $this->assertSame('buyer', $fresh->role);
        $this->assertNotSame('x@example.com', $fresh->email);
        $this->assertFalse((bool) $fresh->is_verified_vendor);
        $this->assertNotNull($taken->fresh());
    }

    public function test_buyer_cannot_set_shop_fields_but_vendor_can(): void
    {
        $buyer = $this->buyer();
        $this->actAs($buyer);
        $this->putJson('/api/v1/auth/me', ['shop_name' => 'Hax'])->assertOk();
        $this->assertNull($buyer->fresh()->shop_name);

        $vendor = $this->vendor();
        $this->actAs($vendor);
        $this->putJson('/api/v1/auth/me', ['shop_name' => 'Renamed'])->assertOk()->assertJsonPath('data.shop_name', 'Renamed');
    }

    public function test_update_profile_requires_login(): void
    {
        $this->putJson('/api/v1/auth/me', ['name' => 'x'])->assertUnauthorized();
    }

    public function test_change_password(): void
    {
        $u = $this->buyer(['password' => 'OldPassword123']);
        $this->actAs($u);

        $this->postJson('/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->postJson('/api/v1/auth/password', ['current_password' => 'OldPassword123', 'password' => 'NewPassword456', 'password_confirmation' => 'mismatch'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/password', ['current_password' => 'OldPassword123', 'password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/password', ['current_password' => 'OldPassword123', 'password' => 'OldPassword123', 'password_confirmation' => 'OldPassword123'])
            ->assertStatus(422);

        $this->postJson('/api/v1/auth/password', ['current_password' => 'OldPassword123', 'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456'])
            ->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword456', $u->fresh()->password));

    }

    public function test_change_password_requires_login(): void
    {
        $this->postJson('/api/v1/auth/password', [])->assertUnauthorized();
    }

    public function test_orders_include_vendor_name_and_conversations_include_counterpart_name(): void
    {
        $buyer = $this->buyer();
        $vendor = $this->vendor();
        $product = $vendor->products()->create(['title' => 'Item', 'price_cents' => 1000, 'stock' => 5, 'status' => Product::STATUS_ACTIVE]);

        $this->actAs($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $order = $this->postJson('/api/v1/orders', ['shipping_address' => 'Street 1, 1234 AB City', 'payment_method' => 'bitcoin'])
            ->assertCreated()->json('data.0');

        $this->getJson('/api/v1/orders')->assertJsonPath('data.0.vendor.shop_name', $vendor->shop_name);
        $this->getJson('/api/v1/orders/' . $order['id'])->assertJsonPath('data.vendor.id', $vendor->id);

        $this->postJson('/api/v1/conversations', ['order_id' => $order['id'], 'body' => 'Hello'])->assertCreated();
        $list = $this->getJson('/api/v1/conversations')->assertOk();
        $list->assertJsonPath('data.0.order_number', Order::find($order['id'])->number);
        $names = array_column($list->json('data.0.participants'), 'display_name');
        $this->assertContains($vendor->shop_name, $names);
    }
}
