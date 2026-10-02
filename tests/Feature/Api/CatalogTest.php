<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function vendor(): User
    {
        return User::factory()->create(['role' => 'vendor', 'handle' => 'v' . uniqid(), 'shop_name' => 'Shop']);
    }

    public function test_public_can_list_only_active_products(): void
    {
        $v = $this->vendor();
        $v->products()->create(['title' => 'Active Mug', 'price_cents' => 1000, 'status' => 'active']);
        $v->products()->create(['title' => 'Draft Mug', 'price_cents' => 1000, 'status' => 'draft']);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Active Mug');
    }

    public function test_draft_product_is_not_publicly_visible(): void
    {
        $p = $this->vendor()->products()->create(['title' => 'Hidden', 'price_cents' => 500, 'status' => 'draft']);
        $this->getJson('/api/v1/products/' . $p->slug)->assertNotFound();
    }

    public function test_vendor_can_create_product_with_scope(): void
    {
        $v = $this->vendor();
        Passport::actingAs($v, ['catalog:write']);

        $this->postJson('/api/v1/products', ['title' => 'New Mug', 'price_cents' => 1500, 'status' => 'active'])
            ->assertCreated()
            ->assertJsonPath('data.vendor.id', $v->id);

        $this->assertDatabaseHas('products', ['title' => 'New Mug', 'vendor_id' => $v->id]);
    }

    public function test_token_without_catalog_write_scope_is_forbidden(): void
    {
        Passport::actingAs($this->vendor(), ['catalog:read']);
        $this->postJson('/api/v1/products', ['title' => 'X', 'price_cents' => 100])->assertForbidden();
    }

    public function test_buyer_cannot_create_products_even_with_scope(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 'buyer']), ['catalog:write']);
        $this->postJson('/api/v1/products', ['title' => 'X', 'price_cents' => 100])->assertForbidden();
    }

    public function test_vendor_cannot_modify_another_vendors_product(): void
    {
        $owner = $this->vendor();
        $p = $owner->products()->create(['title' => 'Owned', 'price_cents' => 900, 'status' => 'active']);

        Passport::actingAs($this->vendor(), ['catalog:write']);
        $this->putJson("/api/v1/products/{$p->id}", ['title' => 'Hijacked'])->assertForbidden();
        $this->deleteJson("/api/v1/products/{$p->id}")->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $p->id, 'title' => 'Owned']);
    }

    public function test_vendor_can_update_and_delete_own_product(): void
    {
        $v = $this->vendor();
        $p = $v->products()->create(['title' => 'Mine', 'price_cents' => 900, 'status' => 'active']);

        Passport::actingAs($v, ['catalog:write']);
        $this->putJson("/api/v1/products/{$p->id}", ['price_cents' => 1100])->assertOk()->assertJsonPath('data.price_cents', 1100);
        $this->deleteJson("/api/v1/products/{$p->id}")->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $p->id]);
    }

    public function test_validation_rejects_bad_price(): void
    {
        Passport::actingAs($this->vendor(), ['catalog:write']);
        $this->postJson('/api/v1/products', ['title' => 'X', 'price_cents' => -5])
            ->assertUnprocessable()->assertJsonValidationErrors('price_cents');
    }
}
