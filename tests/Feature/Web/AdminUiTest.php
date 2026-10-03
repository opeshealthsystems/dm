<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_page_renders_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (config('navigation.admin') as $item) {
            $this->actingAs($admin)->get(route($item['route']))
                ->assertOk()
                ->assertSee('x-data', false);   // a real page, not the placeholder
        }
    }

    public function test_translation_keys_used_by_admin_views_exist(): void
    {
        $missing = [];
        foreach (File::allFiles(resource_path('views/admin')) as $file) {
            $src = File::get($file->getPathname());
            preg_match_all("/__\\(['\"](admin\\.[a-z_.]+)['\"]/", $src, $a);
            preg_match_all("/\\bt\\(['\"](admin\\.[a-z_.]+)['\"]/", $src, $b);
            foreach (array_unique(array_merge($a[1], $b[1])) as $key) {
                if (str_ends_with($key, '.')) {
                    continue; // dynamic key built at runtime, e.g. 'admin.users.roles.' + role
                }
                if (trans($key) === $key) {
                    $missing[] = $file->getFilename() . ': ' . $key;
                }
            }
        }
        $this->assertSame([], $missing, 'Missing admin translation keys');
    }

    public function test_admin_views_never_inject_api_data_as_html(): void
    {
        foreach (File::allFiles(resource_path('views/admin')) as $file) {
            $this->assertStringNotContainsString('x-html', File::get($file->getPathname()), $file->getFilename());
        }
    }

    /**
     * A signed-in browser gets a first-party cookie token that carries EVERY scope, so scope
     * checks alone prove nothing there: each endpoint must also check the user's real role.
     */
    public function test_full_scope_session_token_of_a_buyer_cannot_reach_admin_or_seller_apis(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        Passport::actingAs($buyer, ['*']);

        foreach (['admin/stats', 'admin/users', 'admin/products', 'admin/orders', 'admin/settings', 'admin/audit-logs',
                     'admin/disputes', 'admin/payouts', 'wallet', 'payouts', 'fees', 'developer/keys'] as $path) {
            $this->getJson("/api/v1/$path")->assertForbidden();
        }
        $this->postJson('/api/v1/products', ['title' => 'x', 'price_cents' => 100])->assertForbidden();
    }

    public function test_full_scope_session_token_of_a_vendor_cannot_reach_admin_api(): void
    {
        Passport::actingAs(User::factory()->create(['role' => 'vendor']), ['*']);

        foreach (['admin/stats', 'admin/users', 'admin/settings', 'admin/disputes', 'admin/payouts'] as $path) {
            $this->getJson("/api/v1/$path")->assertForbidden();
        }
    }
}
