<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    /** The app env is switched to 'local' in these tests, so CSRF is enforced: send a valid token. */
    private function demoPost(string $role)
    {
        return $this->withSession(['_token' => 'tok'])->post('/demo-login/' . $role, ['_token' => 'tok']);
    }

    private function seedDemoUsers(): void
    {
        foreach (['admin' => ['admin@dm.test', 'admin'], 'seller' => ['demo-vendor@example.test', 'vendor'], 'buyer' => ['demo-buyer@example.test', 'buyer']] as [$email, $role]) {
            $u = User::factory()->create(['email' => $email]);
            $u->forceFill(['role' => $role])->save();
        }
    }

    public function test_demo_login_works_only_in_local_with_the_switch_on(): void
    {
        $this->seedDemoUsers();
        config(['demo.enabled' => true]);
        $this->app['env'] = 'local';

        $this->get('/login')->assertOk()->assertSee(route('demo-login', 'admin'), false);
        $this->demoPost('seller')->assertRedirect(route('dashboard'));
        $this->assertSame('vendor', auth()->user()->role);
    }

    public function test_each_role_lands_in_its_own_area(): void
    {
        $this->seedDemoUsers();
        config(['demo.enabled' => true]);
        $this->app['env'] = 'local';

        $this->demoPost('admin');
        $this->get('/dashboard')->assertRedirect(route('admin.overview'));
    }

    public function test_it_is_off_when_the_switch_is_off(): void
    {
        $this->seedDemoUsers();
        $this->app['env'] = 'local';
        config(['demo.enabled' => false]);

        $this->get('/login')->assertOk()->assertDontSee('/demo-login/', false);
        $this->demoPost('admin')->assertNotFound();
        $this->assertGuest();
    }

    public function test_it_can_never_run_outside_local_even_if_switched_on(): void
    {
        $this->seedDemoUsers();
        config(['demo.enabled' => true]);

        foreach (['production', 'staging', 'testing'] as $env) {
            $this->app['env'] = $env;
            $this->get('/login')->assertOk()->assertDontSee('/demo-login/', false);
            $this->demoPost('admin')->assertNotFound();
            $this->assertGuest();
        }
    }

    public function test_unknown_roles_and_missing_users_are_404_and_suspended_users_are_refused(): void
    {
        config(['demo.enabled' => true]);
        $this->app['env'] = 'local';

        // 'root' is not an allowed role, so the route does not match: refused either way (404 or 405).
        $this->assertContains($this->demoPost('root')->getStatusCode(), [404, 405]);
        $this->demoPost('admin')->assertNotFound();   // user not seeded

        $this->seedDemoUsers();
        User::where('email', 'admin@dm.test')->update(['suspended_at' => now()]);
        $this->demoPost('admin')->assertNotFound();
        $this->assertGuest();
    }
}
