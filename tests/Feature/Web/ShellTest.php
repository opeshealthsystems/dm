<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_every_menu_item_has_a_working_route_for_its_role(): void
    {
        $roleFor = ['buyer' => 'buyer', 'seller' => 'vendor', 'admin' => 'admin'];

        foreach (config('navigation') as $area => $items) {
            $user = $this->user($roleFor[$area]);
            foreach ($items as $item) {
                $this->actingAs($user)->get(route($item['route']))
                    ->assertOk("Menu item {$item['route']} is broken");
            }
        }
    }

    public function test_menu_labels_exist_in_every_language(): void
    {
        foreach (array_keys(config('locales.supported')) as $locale) {
            foreach (config('navigation') as $items) {
                foreach ($items as $item) {
                    $this->assertNotSame(
                        'common.' . $item['label'],
                        __('common.' . $item['label'], [], $locale),
                        "Missing {$item['label']} for $locale"
                    );
                }
            }
        }
    }

    public function test_areas_are_closed_to_other_roles_and_guests(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/seller/orders')->assertRedirect('/login');

        $this->actingAs($this->user('buyer'))->get('/admin/users')->assertRedirect(route('dashboard'));
        $this->actingAs($this->user('buyer'))->get('/seller/products')->assertRedirect(route('dashboard'));
        $this->actingAs($this->user('vendor'))->get('/admin/users')->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_redirects_by_role(): void
    {
        $this->actingAs($this->user('admin'))->get('/dashboard')->assertRedirect(route('admin.overview'));
        $this->actingAs($this->user('vendor'))->get('/dashboard')->assertRedirect(route('seller.overview'));
        $this->actingAs($this->user('buyer'))->get('/dashboard')->assertRedirect(route('buyer.orders'));
    }

    public function test_web_login_and_logout(): void
    {
        $u = User::factory()->create(['email' => 'w@example.com', 'password' => 'CorrectHorse123', 'role' => 'buyer']);

        $this->post('/login', ['email' => 'w@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'w@example.com', 'password' => 'CorrectHorse123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 's@example.com', 'password' => 'CorrectHorse123', 'suspended_at' => now()]);
        $this->post('/login', ['email' => 's@example.com', 'password' => 'CorrectHorse123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_language_switch_sets_locale_and_rtl_direction(): void
    {
        $this->get('/locale/ar')->assertRedirect();
        $this->get('/login')->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
        $this->get('/login?lang=en')->assertSee('dir="ltr"', false);
        $this->get('/locale/xx')->assertNotFound();
    }
}
