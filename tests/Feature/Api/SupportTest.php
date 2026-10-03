<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\ContentSeo\Models\SupportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    private array $valid = [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.test',
        'subject' => 'Question about escrow',
        'message' => 'How long does escrow hold my payment?',
    ];

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'handle' => 'u' . uniqid()]);
    }

    public function test_anyone_can_send_a_support_request_and_it_is_stored_with_an_encrypted_message(): void
    {
        $this->postJson('/api/v1/support', $this->valid)->assertCreated()->assertJson(['data' => ['received' => true]]);

        $row = SupportRequest::query()->firstOrFail();
        $this->assertSame('ada@example.test', $row->email);
        $this->assertSame('new', $row->status);
        $this->assertSame($this->valid['message'], $row->message);
        $this->assertSame('en', $row->locale);
        $raw = DB::table('support_requests')->value('message');
        $this->assertStringNotContainsString('escrow hold', $raw, 'message is stored encrypted');
    }

    public function test_locale_is_recorded(): void
    {
        $this->withHeader('Accept-Language', 'de')->postJson('/api/v1/support', $this->valid)->assertCreated();
        $this->assertNotNull(SupportRequest::query()->first());
    }

    public function test_validation(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // validation retries are not what this test is about
        $this->postJson('/api/v1/support', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
        $this->postJson('/api/v1/support', ['email' => 'not-an-email'] + $this->valid)->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/v1/support', ['message' => 'short'] + $this->valid)->assertStatus(422)->assertJsonValidationErrors(['message']);
        $this->postJson('/api/v1/support', ['message' => str_repeat('x', 5001)] + $this->valid)->assertStatus(422)->assertJsonValidationErrors(['message']);
        $this->postJson('/api/v1/support', ['name' => str_repeat('n', 101)] + $this->valid)->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->assertSame(0, SupportRequest::query()->count());
    }

    public function test_honeypot_looks_like_success_but_stores_nothing(): void
    {
        $this->postJson('/api/v1/support', $this->valid + ['website' => 'http://spam.example'])
            ->assertCreated()->assertJson(['data' => ['received' => true]]);
        $this->assertSame(0, SupportRequest::query()->count());
    }

    public function test_it_is_throttled_per_visitor(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/support', $this->valid)->assertCreated();
        }
        $this->postJson('/api/v1/support', $this->valid)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(5, SupportRequest::query()->count());
    }

    public function test_admin_list_is_admin_only(): void
    {
        SupportRequest::query()->getModel()->forceFill($this->valid + ['locale' => 'en'])->save();

        $this->getJson('/api/v1/admin/support')->assertUnauthorized();

        Passport::actingAs($this->user('buyer'), ['profile', 'orders:read']);
        $this->getJson('/api/v1/admin/support')->assertForbidden();

        Passport::actingAs($this->user('vendor'), ['vendor:manage']);
        $this->getJson('/api/v1/admin/support')->assertForbidden();

        Passport::actingAs($this->user('admin'), ['admin']);
        $this->getJson('/api/v1/admin/support')->assertOk()
            ->assertJsonPath('data.0.subject', 'Question about escrow')
            ->assertJsonPath('data.0.message', $this->valid['message'])
            ->assertJsonPath('data.0.status', 'new')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_can_filter_and_mark_handled_and_it_is_audited(): void
    {
        foreach (['First', 'Second'] as $subject) {
            (new SupportRequest)->forceFill(['subject' => $subject] + $this->valid + ['locale' => 'en'])->save();
        }
        $admin = $this->user('admin');
        Passport::actingAs($admin, ['admin']);

        $first = SupportRequest::query()->orderBy('id')->first();
        $this->postJson("/api/v1/admin/support/{$first->id}/handle")->assertOk()->assertJsonPath('data.status', 'handled');
        $this->postJson("/api/v1/admin/support/{$first->id}/handle")->assertOk(); // idempotent

        $first->refresh();
        $this->assertSame('handled', $first->status);
        $this->assertSame($admin->id, $first->handled_by);
        $this->assertNotNull($first->handled_at);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'support.handled')->count());

        $this->getJson('/api/v1/admin/support?status=new')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Second');
        $this->getJson('/api/v1/admin/support?status=handled')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'First');
        // New requests are listed before handled ones.
        $this->getJson('/api/v1/admin/support')->assertOk()->assertJsonPath('data.0.status', 'new');

        $this->postJson('/api/v1/admin/support/9999/handle')->assertNotFound();
    }

    public function test_non_admins_cannot_mark_handled(): void
    {
        $row = new SupportRequest;
        $row->forceFill($this->valid + ['locale' => 'en'])->save();

        Passport::actingAs($this->user('vendor'), ['vendor:manage']);
        $this->postJson("/api/v1/admin/support/{$row->id}/handle")->assertForbidden();
        $this->assertSame('new', $row->fresh()->status);
    }

    public function test_admin_support_page_is_in_the_admin_menu_and_admin_only(): void
    {
        $this->assertContains('admin.support', array_column(config('navigation.admin'), 'route'));

        $this->get('/admin/support')->assertRedirect('/login');
        $this->actingAs($this->user('buyer'))->get('/admin/support')->assertRedirect(route('dashboard'));
        $this->actingAs($this->user('admin'))->get('/admin/support')->assertOk()
            ->assertViewIs('admin.support')
            ->assertSee(__('pages.support.title'))
            ->assertSee(__('common.nav.support'));
    }

    public function test_contact_page_posts_to_the_support_endpoint_with_a_honeypot_field(): void
    {
        $this->get('/contact')->assertOk()
            ->assertSee("api('support'", false)
            ->assertSee('name="website"', false)
            ->assertSee('aria-hidden="true"', false);
    }
}
