<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--name' => 'test', '--provider' => 'users']);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'CorrectHorse123',
        ], $override);
    }

    public function test_buyer_can_register_and_gets_buyer_scopes(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.role', 'buyer')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonMissingPath('user.password');

        $scopes = $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'CorrectHorse123'])
            ->assertOk()->json('scopes');

        $this->assertContains('orders:write', $scopes);
        $this->assertNotContains('catalog:write', $scopes);
        $this->assertNotContains('admin', $scopes);
    }

    public function test_vendor_registration_requires_shop_name_and_gets_catalog_write(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['role' => 'vendor']))
            ->assertUnprocessable()->assertJsonValidationErrors('shop_name');

        $res = $this->postJson('/api/v1/auth/register', $this->payload(['role' => 'vendor', 'shop_name' => 'Jane Goods']))
            ->assertCreated();

        $this->assertContains('catalog:write', $res->json('scopes'));
    }

    public function test_cannot_register_as_admin_or_set_role_by_mass_assignment(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['role' => 'admin']))
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'short']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_wrong_password_fails_and_suspended_user_is_blocked(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'CorrectHorse123']);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'nope'])
            ->assertUnprocessable();

        User::where('email', 'a@example.com')->update(['suspended_at' => now()]);
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'CorrectHorse123'])
            ->assertForbidden();
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $token = $this->postJson('/api/v1/auth/register', $this->payload())->json('access_token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.email', 'jane@example.com');
    }
}
