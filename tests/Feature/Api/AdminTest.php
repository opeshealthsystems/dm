<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Admin\Actions\UserModeration;
use App\Modules\Admin\Exceptions\AdminException;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--name' => 'test', '--provider' => 'users']);
    }

    private function user(string $role = 'buyer', array $extra = []): User
    {
        $u = User::factory()->create($extra);
        $u->forceFill(['role' => $role, 'handle' => $role . $u->id])->save();

        return $u;
    }

    private function admin(): User
    {
        $a = $this->user('admin');
        Passport::actingAs($a, ['admin']);

        return $a;
    }

    private function product(User $vendor, array $attrs = []): Product
    {
        $p = new Product(array_merge(['title' => 'Thing', 'price_cents' => 1000, 'currency' => 'EUR', 'stock' => 5, 'status' => 'active'], $attrs));
        $p->vendor_id = $vendor->id;
        $p->save();

        return $p;
    }

    private function order(User $buyer, User $vendor, string $status, int $cents, string $cur = 'EUR'): Order
    {
        $o = new Order;
        $o->forceFill([
            'number' => 'DM' . random_int(100000, 999999) . $status[0],
            'buyer_id' => $buyer->id, 'vendor_id' => $vendor->id, 'currency' => $cur,
            'subtotal_cents' => $cents, 'status' => $status, 'shipping_address' => 'secret street 1',
        ])->save();

        return $o;
    }

    // ---- access control -------------------------------------------------

    public function test_non_admin_is_denied_everywhere(): void
    {
        $buyer = $this->user('buyer');
        $target = $this->user('vendor');
        $cat = Category::create(['name' => 'X', 'slug' => 'x']);
        $product = $this->product($target);
        $order = $this->order($buyer, $target, 'paid', 100);

        $calls = [
            ['get', '/api/v1/admin/stats'], ['get', '/api/v1/admin/audit-logs'], ['get', '/api/v1/admin/users'],
            ['get', "/api/v1/admin/users/{$target->id}"], ['post', "/api/v1/admin/users/{$target->id}/suspend"],
            ['post', "/api/v1/admin/users/{$target->id}/unsuspend"], ['post', "/api/v1/admin/users/{$target->id}/verify"],
            ['post', "/api/v1/admin/users/{$target->id}/unverify"], ['put', "/api/v1/admin/users/{$target->id}/role", ['role' => 'buyer']],
            ['get', '/api/v1/admin/products'], ['post', "/api/v1/admin/products/{$product->id}/archive", ['reason' => 'because']],
            ['post', "/api/v1/admin/products/{$product->id}/restore"], ['get', '/api/v1/admin/categories'],
            ['post', '/api/v1/admin/categories', ['name' => 'N']], ['put', "/api/v1/admin/categories/{$cat->id}", ['name' => 'N']],
            ['delete', "/api/v1/admin/categories/{$cat->id}"], ['get', '/api/v1/admin/orders'], ['get', "/api/v1/admin/orders/{$order->id}"],
            ['get', '/api/v1/admin/settings'], ['put', '/api/v1/admin/settings', ['settings' => ['site_name' => 'x']]],
        ];

        // 1) Unauthenticated -> 401.
        foreach ($calls as $c) {
            $this->json($c[0], $c[1], $c[2] ?? [])->assertUnauthorized();
        }
        // 2) Buyer token without admin scope -> 403.
        Passport::actingAs($buyer, ['profile', 'orders:read']);
        foreach ($calls as $c) {
            $this->json($c[0], $c[1], $c[2] ?? [])->assertForbidden();
        }
        // 3) Even with the admin scope, a non-admin role is denied by policy/gate (defence in depth).
        Passport::actingAs($buyer, ['admin']);
        foreach ($calls as $c) {
            $this->json($c[0], $c[1], $c[2] ?? [])->assertForbidden();
        }

        $this->assertSame(0, AuditLog::count());
    }

    // ---- admin:create ---------------------------------------------------

    public function test_admin_create_command_creates_admin_with_strong_password(): void
    {
        $this->artisan('admin:create', ['email' => 'boss@example.com'])
            ->expectsQuestion('Password (min 12 chars, mixed case, numbers, symbols)', 'Str0ng!Passw0rd#')
            ->expectsQuestion('Confirm password', 'Str0ng!Passw0rd#')
            ->assertSuccessful();

        $u = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertSame('admin', $u->role);
        $this->assertNotNull($u->email_verified_at);
        $this->assertTrue(password_verify('Str0ng!Passw0rd#', $u->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.created', 'target_id' => (string) $u->id]);
    }

    public function test_admin_create_rejects_weak_password_and_duplicates(): void
    {
        $this->artisan('admin:create', ['email' => 'weak@example.com'])
            ->expectsQuestion('Password (min 12 chars, mixed case, numbers, symbols)', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);

        $existing = $this->user('buyer');
        $this->artisan('admin:create', ['email' => $existing->email])->assertFailed();
        $this->assertSame('buyer', $existing->fresh()->role);
    }

    public function test_admin_create_rejects_mismatched_confirmation(): void
    {
        $this->artisan('admin:create', ['email' => 'mm@example.com'])
            ->expectsQuestion('Password (min 12 chars, mixed case, numbers, symbols)', 'Str0ng!Passw0rd#')
            ->expectsQuestion('Confirm password', 'different')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'mm@example.com']);
    }

    // ---- users ----------------------------------------------------------

    public function test_list_search_and_show_users(): void
    {
        $this->admin();
        $v = $this->user('vendor', ['email' => 'findme@example.com', 'name' => 'Zed Findable']);
        $this->user('buyer');

        $this->getJson('/api/v1/admin/users?q=findme')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $v->id)->assertJsonMissingPath('data.0.password');
        $this->getJson('/api/v1/admin/users?role=vendor')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/users/{$v->id}")->assertOk()->assertJsonPath('data.email', 'findme@example.com');
    }

    public function test_suspend_revokes_tokens_and_blocks_api_and_audits(): void
    {
        $buyer = $this->user('buyer', ['email' => 'victim@example.com', 'password' => 'CorrectHorse123']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'CorrectHorse123'])->json('access_token');
        $this->assertNotEmpty($token);
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $token"])->assertOk();

        $this->app['auth']->forgetGuards();
        $admin = $this->admin();

        $this->postJson("/api/v1/admin/users/{$buyer->id}/suspend", ['reason' => 'fraud'])
            ->assertOk()->assertJsonPath('data.suspended_at', fn ($v) => $v !== null);

        // Reset the in-process guard so the real bearer token is evaluated again.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $token"])->assertUnauthorized();
        $this->assertSame(0, \DB::table('oauth_access_tokens')->where('user_id', $buyer->id)->where('revoked', false)->count());

        $log = AuditLog::where('action', 'user.suspended')->firstOrFail();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame('user', $log->target_type);
        $this->assertSame((string) $buyer->id, $log->target_id);
        $this->assertNull($log->before['suspended_at']);
        $this->assertNotNull($log->after['suspended_at']);
        $this->assertSame('fraud', $log->after['reason']);
        $this->assertNotNull($log->ip);

        // Login is refused too.
        $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'CorrectHorse123'])->assertForbidden();
    }

    public function test_middleware_rejects_suspended_user_with_unrevoked_token(): void
    {
        $buyer = $this->user('buyer', ['email' => 'late@example.com', 'password' => 'CorrectHorse123']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'late@example.com', 'password' => 'CorrectHorse123'])->json('access_token');

        // Suspended directly in the DB (token not revoked): the middleware must still stop it.
        $buyer->forceFill(['suspended_at' => now()])->save();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $token"])
            ->assertForbidden()->assertJsonPath('message', 'This account is suspended.');
    }

    public function test_unsuspend(): void
    {
        $this->admin();
        $u = $this->user('buyer', ['suspended_at' => now()]);
        $this->postJson("/api/v1/admin/users/{$u->id}/unsuspend")->assertOk()->assertJsonPath('data.suspended_at', null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.unsuspended']);
        $this->postJson("/api/v1/admin/users/{$u->id}/unsuspend")->assertStatus(409);
    }

    public function test_admin_cannot_suspend_self(): void
    {
        $admin = $this->admin();
        $this->postJson("/api/v1/admin/users/{$admin->id}/suspend")->assertForbidden();
        $this->assertNull($admin->fresh()->suspended_at);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->admin();
        $this->putJson("/api/v1/admin/users/{$admin->id}/role", ['role' => 'buyer'])->assertForbidden();
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_last_admin_protection(): void
    {
        $actor = $this->user('buyer'); // not an admin: simulates acting on the sole remaining admin
        $only = $this->user('admin');
        $svc = app(UserModeration::class);

        foreach ([fn () => $svc->suspend($actor, $only), fn () => $svc->changeRole($actor, $only, 'buyer')] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected last-admin protection.');
            } catch (AdminException $e) {
                $this->assertSame(422, $e->status);
                $this->assertStringContainsString('last active admin', $e->getMessage());
            }
        }
        $this->assertSame('admin', $only->fresh()->role);
        $this->assertNull($only->fresh()->suspended_at);

        // With a second active admin it is allowed.
        $second = $this->user('admin');
        $svc->suspend($second, $only);
        $this->assertNotNull($only->fresh()->suspended_at);
        // ...and the remaining one is now protected again.
        $this->expectException(AdminException::class);
        $svc->suspend($actor, $second);
    }

    public function test_suspended_admins_do_not_count_toward_last_admin(): void
    {
        $a1 = $this->admin();
        $this->user('admin', ['suspended_at' => now()]);
        $other = $this->user('admin');
        // a1 may demote `other` since a1 itself stays an active admin.
        $this->putJson("/api/v1/admin/users/{$other->id}/role", ['role' => 'vendor'])->assertOk();
        $this->assertSame('vendor', $other->fresh()->role);
        $this->assertSame('admin', $a1->fresh()->role);
    }

    public function test_verify_and_unverify_vendor(): void
    {
        $this->admin();
        $v = $this->user('vendor');
        $b = $this->user('buyer');

        $this->postJson("/api/v1/admin/users/{$v->id}/verify")->assertOk()->assertJsonPath('data.is_verified_vendor', true);
        $this->postJson("/api/v1/admin/users/{$v->id}/unverify")->assertOk()->assertJsonPath('data.is_verified_vendor', false);
        $this->postJson("/api/v1/admin/users/{$b->id}/verify")->assertUnprocessable();
        $this->assertSame(2, AuditLog::whereIn('action', ['user.vendor_verified', 'user.vendor_unverified'])->count());
    }

    public function test_change_role_only_between_buyer_and_vendor(): void
    {
        $this->admin();
        $u = $this->user('buyer');

        $this->putJson("/api/v1/admin/users/{$u->id}/role", ['role' => 'admin'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertSame('buyer', $u->fresh()->role);

        $this->putJson("/api/v1/admin/users/{$u->id}/role", ['role' => 'vendor'])->assertOk()->assertJsonPath('data.role', 'vendor');
        $log = AuditLog::where('action', 'user.role_changed')->firstOrFail();
        $this->assertSame('buyer', $log->before['role']);
        $this->assertSame('vendor', $log->after['role']);
    }

    // ---- catalog --------------------------------------------------------

    public function test_lists_all_products_including_drafts_and_force_archive_restore(): void
    {
        $this->admin();
        $v = $this->user('vendor');
        $draft = $this->product($v, ['status' => 'draft', 'title' => 'Draft one']);
        $active = $this->product($v);

        $this->getJson('/api/v1/admin/products')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/products?status=draft')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/admin/products/{$active->id}/archive")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/admin/products/{$active->id}/archive", ['reason' => 'Prohibited item'])
            ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.moderation_reason', 'Prohibited item');
        $this->postJson("/api/v1/admin/products/{$active->id}/archive", ['reason' => 'again'])->assertStatus(409);
        // Listing after moderation must still serialise (moderated_at is a cast datetime).
        $this->getJson('/api/v1/admin/products?status=archived')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.moderated_at', fn ($v) => is_string($v) && $v !== '');

        $this->postJson("/api/v1/admin/products/{$active->id}/restore")->assertOk()->assertJsonPath('data.status', 'draft');
        $this->postJson("/api/v1/admin/products/{$draft->id}/restore")->assertStatus(409);

        $this->assertSame(['product.archived', 'product.restored'], AuditLog::where('target_type', 'product')->orderBy('id')->pluck('action')->all());
    }

    public function test_category_crud_tree_and_slug_uniqueness(): void
    {
        $this->admin();

        $root = $this->postJson('/api/v1/admin/categories', ['name' => 'Gadgets Store'])->assertCreated()
            ->assertJsonPath('data.slug', 'gadgets-store')->json('data.id');
        $this->postJson('/api/v1/admin/categories', ['name' => 'Other', 'slug' => 'gadgets-store'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
        $child = $this->postJson('/api/v1/admin/categories', ['name' => 'Phones', 'parent_id' => $root])->assertCreated()->json('data.id');

        $tree = $this->getJson('/api/v1/admin/categories')->assertOk();
        $tree->assertJsonCount(1, 'data')->assertJsonPath('data.0.children.0.id', $child);

        // Updating a category may keep its own slug, but not take another's.
        $this->putJson("/api/v1/admin/categories/$child", ['name' => 'Smartphones', 'slug' => 'phones'])->assertOk();
        $this->putJson("/api/v1/admin/categories/$child", ['slug' => 'gadgets-store'])->assertUnprocessable();
        // No cycles.
        $this->putJson("/api/v1/admin/categories/$root", ['parent_id' => $child])->assertUnprocessable();
        $this->putJson("/api/v1/admin/categories/$root", ['parent_id' => $root])->assertUnprocessable();

        // Cannot delete with children or products.
        $this->deleteJson("/api/v1/admin/categories/$root")->assertStatus(409);
        $this->product($this->user('vendor'), ['category_id' => $child]);
        $this->deleteJson("/api/v1/admin/categories/$child")->assertStatus(409);

        $empty = $this->postJson('/api/v1/admin/categories', ['name' => 'Empty'])->json('data.id');
        $this->deleteJson("/api/v1/admin/categories/$empty")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $empty]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted']);
    }

    // ---- orders ---------------------------------------------------------

    public function test_orders_overview_filters_totals_and_hides_address(): void
    {
        $this->admin();
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $this->order($b, $v, 'paid', 1000);
        $this->order($b, $v, 'completed', 2500);
        $this->order($b, $v, 'paid', 700, 'USD');

        $res = $this->getJson('/api/v1/admin/orders')->assertOk()->assertJsonCount(3, 'data');
        $totals = collect($res->json('totals'))->keyBy('currency');
        $this->assertSame(3500, $totals['EUR']['subtotal_cents']);
        $this->assertSame(2, $totals['EUR']['orders']);
        $this->assertSame(700, $totals['USD']['subtotal_cents']);

        $this->getJson('/api/v1/admin/orders?status=paid&currency=EUR')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('totals.0.subtotal_cents', 1000);

        $id = $res->json('data.0.id');
        $show = $this->getJson("/api/v1/admin/orders/$id")->assertOk()->assertJsonStructure(['data' => ['number', 'items', 'escrow_status']]);
        $this->assertStringNotContainsString('secret street', $show->getContent());

        // Read-only: no mutating routes exist.
        $this->postJson("/api/v1/admin/orders/$id")->assertStatus(405);
        $this->deleteJson("/api/v1/admin/orders/$id")->assertStatus(405);
    }

    // ---- settings -------------------------------------------------------

    public function test_settings_defaults_update_whitelist_and_cache(): void
    {
        $this->admin();

        $this->getJson('/api/v1/admin/settings')->assertOk()
            ->assertJsonPath('data.default_currency', 'EUR')->assertJsonPath('data.maintenance_mode', false)
            ->assertJsonPath('data.fee_rate_vendor_bps', 0);

        $this->putJson('/api/v1/admin/settings', ['settings' => [
            'site_name' => 'My Market', 'support_email' => 'help@example.com', 'default_currency' => 'USD',
            'fee_rate_buyer_bps' => 150, 'maintenance_mode' => true,
        ]])->assertOk()->assertJsonPath('data.site_name', 'My Market')
            ->assertJsonPath('data.fee_rate_buyer_bps', 150)->assertJsonPath('data.maintenance_mode', true);

        // Typed on read-back and served from cache.
        $this->getJson('/api/v1/admin/settings')->assertJsonPath('data.fee_rate_buyer_bps', 150)->assertJsonPath('data.maintenance_mode', true);
        $this->assertTrue(\Cache::has('platform.settings'));

        $log = AuditLog::where('action', 'settings.updated')->firstOrFail();
        $this->assertSame(config('app.name'), $log->before['site_name']); // prior value kept
        $this->assertSame('My Market', $log->after['site_name']);
        $this->assertTrue($log->after['maintenance_mode']);
    }

    public function test_settings_whitelist_and_validation(): void
    {
        $this->admin();

        $this->putJson('/api/v1/admin/settings', ['settings' => ['app_key' => 'x']])
            ->assertUnprocessable()->assertJsonValidationErrors('settings.app_key');
        $this->putJson('/api/v1/admin/settings', ['settings' => ['site_name' => 'ok', 'db_password' => 'x']])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings', ['settings' => ['default_currency' => 'euro']])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings', ['settings' => ['support_email' => 'nope']])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings', ['settings' => ['fee_rate_vendor_bps' => 10001]])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings', ['settings' => ['maintenance_mode' => 'maybe']])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings', ['settings' => []])->assertUnprocessable();

        // Nothing was persisted or audited, including the valid key sent with an invalid one.
        $this->assertDatabaseCount('settings', 0);
        $this->assertSame(0, AuditLog::count());
    }

    // ---- stats ----------------------------------------------------------

    public function test_stats(): void
    {
        $this->admin();
        $b = $this->user('buyer');
        $v = $this->user('vendor');
        $old = $this->user('buyer');
        \DB::table('users')->where('id', $old->id)->update(['created_at' => now()->subDays(30)]);

        $this->order($b, $v, 'paid', 1000);
        $this->order($b, $v, 'shipped', 500);
        $this->order($b, $v, 'completed', 250, 'USD');
        $this->order($b, $v, 'cancelled', 9999);
        $this->order($b, $v, 'pending_payment', 8888);

        $d = $this->getJson('/api/v1/admin/stats')->assertOk()->json('data');
        $this->assertSame(1, $d['users_by_role']['admin']);
        $this->assertSame(2, $d['users_by_role']['buyer']);
        $this->assertSame(1, $d['users_by_role']['vendor']);
        $this->assertSame(1, $d['orders_by_status']['paid']);
        $this->assertSame(1, $d['orders_by_status']['cancelled']);
        $this->assertSame(['EUR' => 1500, 'USD' => 250], $d['gmv_by_currency']);
        $this->assertSame(3, $d['new_users_7d']);
    }

    // ---- audit log API --------------------------------------------------

    public function test_audit_log_endpoint_filters(): void
    {
        $admin = $this->admin();
        $u = $this->user('buyer');
        $v = $this->user('vendor');

        $this->postJson("/api/v1/admin/users/{$u->id}/suspend");
        $this->postJson("/api/v1/admin/users/{$v->id}/verify");
        $this->postJson('/api/v1/admin/categories', ['name' => 'Zed']);

        $this->getJson('/api/v1/admin/audit-logs')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.action', 'category.created')->assertJsonPath('data.0.actor.email', $admin->email);
        $this->getJson('/api/v1/admin/audit-logs?action=user.*')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/audit-logs?action=user.suspended')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/audit-logs?target_type=user&target_id={$v->id}")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/audit-logs?actor_id={$admin->id}")->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/admin/audit-logs?actor_id=999999')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/audit-logs?from=2999-01-01')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/audit-logs?from=2000-01-01&to=2999-01-01')->assertJsonCount(3, 'data');
    }

    // ---- seeders --------------------------------------------------------

    public function test_database_seeder_creates_only_categories_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $n = Category::count();
        $this->assertGreaterThan(5, $n);
        $this->assertDatabaseHas('categories', ['slug' => 'phones']);
        $this->assertSame(0, User::count());
        $this->assertSame(0, Product::count());

        $this->seed(DatabaseSeeder::class);
        $this->assertSame($n, Category::count());
    }

    public function test_demo_seeder_is_separate_and_creates_no_admin(): void
    {
        $this->seed(DemoSeeder::class);
        $this->assertGreaterThan(0, Product::count());
        $this->assertSame(0, User::where('role', 'admin')->count());
    }
}
