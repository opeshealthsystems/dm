<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Modules\Identity\Actions\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Web side of account safety: pages, the two-step browser login, banners and the views scan. */
class SecurityPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'CorrectHorse123';

    private function user(string $role = 'buyer', array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'password' => self::PASSWORD], $attrs));
    }

    private function withTwoFactor(User $user): string
    {
        $secret = app(Totp::class)->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        return $secret;
    }

    public function test_forgot_and_reset_pages_render_for_guests(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('forgotPassword', false);
        $this->get('/reset-password/abc123?email=a%40b.test')->assertOk()->assertSee('resetPassword', false)->assertSee('abc123', false);
        $this->get('/login')->assertSee(route('password.request'), false);
    }

    public function test_pages_are_closed_to_logged_in_users_or_guests_as_appropriate(): void
    {
        $this->get('/account/security')->assertRedirect('/login');
        $this->actingAs($this->user())->get('/forgot-password')->assertRedirect();
    }

    public function test_security_page_renders_for_every_role_with_the_right_menu(): void
    {
        foreach (['buyer', 'vendor', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get('/account/security')
                ->assertOk()->assertSee('securityPage', false)->assertSee(__('security.page.title'));
        }
        $this->actingAs($this->user('vendor'))->get('/account/security')->assertSee(route('seller.wallet'), false);
        $this->actingAs($this->user('admin'))->get('/account/security')->assertSee(route('admin.users'), false);
    }

    public function test_every_area_links_to_the_security_page(): void
    {
        $this->actingAs($this->user('buyer'))->get(route('buyer.profile'))->assertSee(route('account.security'), false);
        $this->actingAs($this->user('vendor'))->get(route('seller.profile'))->assertSee(route('account.security'), false);
        $this->actingAs($this->user('admin'))->get(route('admin.overview'))->assertSee(route('account.security'), false);
        $this->actingAs($this->user('buyer'))->get('/')->assertSee(route('account.security'), false);
    }

    public function test_unverified_users_see_the_verification_banner_with_resend(): void
    {
        $this->actingAs($this->user('buyer', ['email_verified_at' => null]))->get(route('buyer.orders'))
            ->assertSee(__('security.verify.banner'))->assertSee('auth/email/resend', false);
        $this->actingAs($this->user('buyer'))->get(route('buyer.orders'))->assertDontSee(__('security.verify.banner'));
    }

    public function test_admins_without_two_factor_are_nudged_but_others_are_not(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('admin.overview'))->assertSee(__('security.twofa.admin_banner'));

        $this->withTwoFactor($admin);
        $this->actingAs($admin->fresh())->get(route('admin.overview'))->assertDontSee(__('security.twofa.admin_banner'));

        $this->actingAs($this->user('vendor'))->get(route('seller.orders'))->assertDontSee(__('security.twofa.admin_banner'));
    }

    // ---- browser login with 2FA -------------------------------------------

    public function test_login_without_two_factor_works_as_before(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_two_factor_needs_the_second_step(): void
    {
        $user = $this->user();
        $secret = $this->withTwoFactor($user);
        $totp = app(Totp::class);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->get('/two-factor-challenge')->assertOk()->assertSee(__('security.challenge.title'));
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $totp->code($secret, $totp->timeStep())])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_login_with_a_recovery_code(): void
    {
        $user = $this->user();
        $this->withTwoFactor($user);
        $user->recoveryCodes()->create(['code_hash' => hash_hmac('sha256', 'abcde-fghij', config('app.key'))]);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/two-factor-challenge', ['recovery_code' => 'abcde-fghij'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->post('/two-factor-challenge', ['recovery_code' => 'abcde-fghij'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_page_needs_a_pending_login_and_it_expires(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect('/login');

        $user = $this->user();
        $this->withTwoFactor($user);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->travel(6)->minutes();
        $this->get('/two-factor-challenge')->assertRedirect('/login');
    }

    public function test_web_login_locks_the_account_after_five_failures(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.9.0.$i"])
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.99'])
            ->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => __('security.lockout.message', ['minutes' => 15])]);
        $this->assertGuest();
    }

    public function test_registering_in_the_browser_leaves_the_email_unverified(): void
    {
        $this->post('/register', ['name' => 'Jane', 'email' => 'jane@example.com', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertFalse(User::where('email', 'jane@example.com')->firstOrFail()->hasVerifiedEmail());
    }

    // ---- views scan --------------------------------------------------------

    /** @return list<string> */
    private function viewFiles(): array
    {
        return array_merge(
            [
                resource_path('views/auth/forgot-password.blade.php'),
                resource_path('views/auth/reset-password.blade.php'),
                resource_path('views/auth/two-factor-challenge.blade.php'),
                resource_path('views/account/security.blade.php'),
                resource_path('views/partials/account-banners.blade.php'),
            ],
            glob(resource_path('views/emails/*.blade.php')),
        );
    }

    public function test_security_views_use_logical_classes_and_tokens_only(): void
    {
        foreach ($this->viewFiles() as $file) {
            $src = file_get_contents($file);
            $name = basename($file);
            $this->assertDoesNotMatchRegularExpression('/\b(ml|mr|pl|pr)-\d|\btext-(left|right)\b|\b(left|right)-\d|\bfloat-(left|right)\b/', $src, "$name uses physical direction classes");
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b(?!\w)/', $src, "$name contains a hex colour");
            $this->assertStringNotContainsString('x-html', $src, "$name binds HTML");
            $this->assertStringNotContainsString('{!!', $src, "$name prints unescaped output");
            $this->assertDoesNotMatchRegularExpression('#https?://[a-z0-9.-]+\.[a-z]{2,}#i', $src, "$name references an external host");
        }
    }

    public function test_every_security_key_used_in_views_and_code_exists_in_english(): void
    {
        $en = require base_path('lang/en/security.php');
        $sources = $this->viewFiles();
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/Identity')));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $sources[] = $f->getPathname();
            }
        }
        $sources[] = app_path('Modules/Catalog/Http/Controllers/ProductController.php');
        $sources[] = resource_path('views/layouts/dashboard.blade.php');

        $missing = [];
        $found = 0;
        foreach ($sources as $file) {
            preg_match_all("/(?:__\(|\bt\()\s*['\"]security\.([A-Za-z0-9_.]+)['\"]/", file_get_contents($file), $m);
            foreach (array_unique($m[1]) as $key) {
                $found++;
                if (! is_string(data_get($en, $key))) {
                    $missing[] = basename($file) . ": security.$key";
                }
            }
        }
        $this->assertGreaterThan(40, $found, 'The scanner found suspiciously few keys.');
        $this->assertSame([], $missing, "Missing translation keys:\n" . implode("\n", $missing));
    }

    public function test_no_unused_strings_in_security_file(): void
    {
        $en = \Illuminate\Support\Arr::dot(require base_path('lang/en/security.php'));
        $all = '';
        foreach ($this->viewFiles() as $file) {
            $all .= file_get_contents($file);
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules')));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $all .= file_get_contents($f->getPathname());
            }
        }
        foreach (['dashboard', 'store', 'login'] as $layout) {
            foreach (glob(resource_path("views/{$layout}*.blade.php")) as $f) {
                $all .= file_get_contents($f);
            }
        }
        $all .= file_get_contents(resource_path('views/layouts/dashboard.blade.php')) . file_get_contents(resource_path('views/layouts/store.blade.php'))
            . file_get_contents(resource_path('views/auth/login.blade.php')) . file_get_contents(resource_path('views/buyer/profile.blade.php'))
            . file_get_contents(resource_path('views/seller/profile.blade.php'));

        $unused = [];
        foreach (array_keys($en) as $key) {
            if (! str_contains($all, "security.$key'")) {
                $unused[] = "security.$key";
            }
        }
        $this->assertSame([], $unused, "Unused keys:\n" . implode("\n", $unused));
    }

    public function test_templates_contain_no_hard_coded_visible_text(): void
    {
        foreach ($this->viewFiles() as $file) {
            $src = file_get_contents($file);
            $src = preg_replace(['/<!DOCTYPE[^>]*>/i', '/^@section\(.title.*$/m', '/<script.*?<\/script>/s', '/<style.*?<\/style>/s', '/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/@php.*?@endphp/s', '/<\/?[a-zA-Z][^\s>]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/s'], ' ', $src);
            $src = preg_replace('/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', ' ', $src);
            $text = trim(preg_replace('/\s+/u', ' ', $src));
            $this->assertSame('', $text, basename($file) . " contains hard-coded text: $text");
        }
    }

    public function test_security_pages_render_right_to_left_in_arabic(): void
    {
        $this->actingAs($this->user());
        $this->get('/locale/ar');
        $this->get('/account/security')->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('security.page.title', [], 'ar'));
    }
}
