<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/** Endpoints the seller screens rely on: own product list/show, categories, profile update. */
class SellerSupportApiTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(string $handle = 'v1'): User
    {
        return User::factory()->create(['role' => 'vendor', 'handle' => $handle, 'shop_name' => 'Shop']);
    }

    public function test_vendor_lists_own_products_in_every_status_only(): void
    {
        $v = $this->vendor();
        $other = $this->vendor('v2');
        $v->products()->create(['title' => 'Draft Mug', 'price_cents' => 100, 'status' => 'draft']);
        $v->products()->create(['title' => 'Live Mug', 'price_cents' => 100, 'status' => 'active']);
        $other->products()->create(['title' => 'Not mine', 'price_cents' => 100, 'status' => 'active']);

        Passport::actingAs($v, ['catalog:write']);

        $this->getJson('/api/v1/vendor/products')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/vendor/products?status=draft')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Draft Mug');
        $this->getJson('/api/v1/vendor/products?q=live')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_vendor_products_require_catalog_write_scope_and_auth(): void
    {
        $this->getJson('/api/v1/vendor/products')->assertUnauthorized();
        Passport::actingAs($this->vendor(), ['orders:read']);
        $this->getJson('/api/v1/vendor/products')->assertForbidden();
    }

    public function test_vendor_can_show_own_draft_but_not_someone_elses(): void
    {
        $v = $this->vendor();
        $mine = $v->products()->create(['title' => 'Draft', 'price_cents' => 100, 'status' => 'draft']);
        $theirs = $this->vendor('v2')->products()->create(['title' => 'Theirs', 'price_cents' => 100, 'status' => 'draft']);

        Passport::actingAs($v, ['catalog:write']);
        $this->getJson('/api/v1/vendor/products/' . $mine->id)->assertOk()->assertJsonPath('data.title', 'Draft');
        $this->getJson('/api/v1/vendor/products/' . $theirs->id)->assertForbidden();
    }

    public function test_categories_are_public(): void
    {
        Category::create(['name' => 'Home', 'slug' => 'home']);
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.slug', 'home');
    }

    public function test_vendor_updates_shop_profile_and_it_is_returned(): void
    {
        $v = $this->vendor();
        Passport::actingAs($v, ['profile']);

        $this->putJson('/api/v1/auth/me', ['name' => 'New Name', 'shop_name' => 'New Shop', 'shop_description' => 'About us'])
            ->assertOk()->assertJsonPath('data.shop_name', 'New Shop')->assertJsonPath('data.shop_description', 'About us');

        $this->assertDatabaseHas('users', ['id' => $v->id, 'name' => 'New Name', 'shop_name' => 'New Shop']);
        $this->putJson('/api/v1/auth/me', ['shop_name' => str_repeat('x', 300)])->assertUnprocessable()->assertJsonValidationErrors('shop_name');
    }

    public function test_profile_update_cannot_change_role_or_email(): void
    {
        $v = $this->vendor();
        Passport::actingAs($v, ['profile']);

        $this->putJson('/api/v1/auth/me', ['role' => 'admin', 'email' => 'x@example.com', 'name' => 'Ok'])->assertOk();

        $v->refresh();
        $this->assertSame('vendor', $v->role);
        $this->assertNotSame('x@example.com', $v->email);
    }
}
