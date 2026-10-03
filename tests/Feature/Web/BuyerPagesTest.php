<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerPagesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'handle' => 'u' . uniqid(), 'shop_name' => $role === 'vendor' ? 'Shop' : null]);
    }

    public function test_buyer_pages_render_for_a_buyer(): void
    {
        $buyer = $this->user('buyer');
        foreach (['orders', 'cart', 'messages', 'following', 'profile'] as $page) {
            $this->actingAs($buyer)->get(route("buyer.$page"))
                ->assertOk()
                ->assertViewIs("buyer.$page"); // the real page, not the coming-soon stub
        }
        $this->actingAs($buyer)->get(route('buyer.orders.show', 5))->assertOk()->assertSee('buyerOrder', false);
    }

    public function test_buyer_pages_expose_buyer_translations_to_javascript(): void
    {
        $this->actingAs($this->user('buyer'))->get(route('buyer.cart'))
            ->assertSee('"buyer":', false)
            ->assertSee(__('buyer.cart.title'));
    }

    public function test_buyer_pages_redirect_guests_to_login(): void
    {
        foreach (['/account/orders', '/account/cart', '/account/messages', '/account/following', '/account/profile', '/account/orders/3'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_order_detail_requires_numeric_id(): void
    {
        $this->actingAs($this->user('buyer'))->get('/account/orders/abc')->assertNotFound();
    }

    public function test_storefront_pages_are_public(): void
    {
        $this->get('/')->assertOk()->assertSee(__('buyer.store.title'))->assertSee('storeHome', false);
        $vendor = $this->user('vendor');
        $product = $vendor->products()->create(['title' => 'Mug', 'price_cents' => 500, 'stock' => 1, 'status' => 'active']);
        $this->get('/p/' . $product->slug)->assertOk()->assertSee('productPage', false);
        $this->get('/p/some-slug')->assertNotFound();
        $this->get('/v/' . $vendor->id)->assertOk()->assertSee('vendorPage', false);
        $this->get('/v/9999')->assertNotFound();
        $this->get('/v/abc')->assertNotFound();
    }

    public function test_storefront_header_changes_with_login_state(): void
    {
        $this->get('/')->assertSee(route('login'), false);
        $this->actingAs($this->user('buyer'))->get('/')->assertSee(route('dashboard'), false);
    }

    public function test_product_page_receives_the_viewer_role(): void
    {
        $vendor = $this->user('vendor');
        $slug = $vendor->products()->create(['title' => 'Mug', 'price_cents' => 500, 'stock' => 1, 'status' => 'active'])->slug;
        $this->get("/p/$slug")->assertSee("productPage('$slug', null)", false);
        $this->actingAs($vendor)->get("/p/$slug")->assertSee("productPage('$slug', 'vendor')", false);
    }

    public function test_views_use_logical_properties_and_no_raw_html_or_hex_colours(): void
    {
        $files = array_merge(
            glob(resource_path('views/buyer/*.blade.php')),
            glob(resource_path('views/store/*.blade.php')),
            [resource_path('views/home.blade.php'), resource_path('views/layouts/store.blade.php'), resource_path('views/partials/pager.blade.php')],
        );
        foreach ($files as $file) {
            $src = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*(?:^|\s)-?(?:ml|mr|pl|pr|left|right|text-left|text-right|border-l|border-r|rounded-l|rounded-r)-[\w\[]/',
                $src,
                basename($file) . ' uses physical left/right classes'
            );
            $this->assertStringNotContainsString('x-html', $src, basename($file) . ' must not use x-html');
            $this->assertDoesNotMatchRegularExpression('/(?<![&\w])#[0-9a-fA-F]{6}\b/', $src, basename($file) . ' hard-codes a colour');
            $this->assertDoesNotMatchRegularExpression('/\son(?:click|change|submit|input)=/i', $src, basename($file) . ' has an inline handler');
        }
    }
}
