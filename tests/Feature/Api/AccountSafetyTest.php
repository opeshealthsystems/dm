<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Modules\Identity\Actions\Totp;
use App\Modules\Identity\Mail\PasswordResetMail;
use App\Modules\Identity\Mail\VerifyEmailMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Passport;
use Tests\TestCase;

/** Password reset, e-mail verification, TOTP two-factor and account lockout. */
class AccountSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'CorrectHorse123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('passport:client', ['--personal' => true, '--name' => 'test', '--provider' => 'users']);
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['password' => self::PASSWORD, 'role' => 'buyer'], $attrs));
    }

    private function totp(): Totp
    {
        return app(Totp::class);
    }

    /** Enable 2FA for a user through the API and return [secret, recoveryCodes]. */
    private function enableTwoFactor(User $user): array
    {
        // Pin the clock to the 3rd second of a 30s step, so "+31s" below is always exactly one
        // step later (otherwise a run that starts at the end of a step skips two and flakes).
        $this->travelTo(now()->setTimestamp(intdiv(now()->getTimestamp(), 30) * 30 + 2));

        Passport::actingAs($user, ['profile']);
        $secret = $this->postJson('/api/v1/auth/2fa/setup')->assertOk()->json('data.secret');
        $codes = $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $this->totp()->code($secret, $this->totp()->timeStep())])
            ->assertOk()->json('data.recovery_codes');
        $this->app['auth']->forgetGuards();
        $this->travel(31)->seconds(); // move past the time-step used to confirm (replay guard)

        return [$secret, $codes];
    }

    private function login(string $email, string $password = self::PASSWORD, array $extra = [], string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password] + $extra);
    }

    // ---- TOTP primitives ---------------------------------------------------

    public function test_totp_matches_rfc_6238_test_vector(): void
    {
        $secret = $this->totp()->base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        // RFC 6238 appendix B: T=59s, SHA1 -> 94287082 (8 digits), i.e. 287082 with 6 digits.
        $this->assertSame('287082', $this->totp()->code($secret, 1));
        $this->assertSame('081804', $this->totp()->code($secret, 37037036)); // T=1111111109
    }

    // ---- password reset ----------------------------------------------------

    private function requestResetToken(User $user): string
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $m) use (&$url) {
            $url = $m->url;

            return true;
        });
        preg_match('#/reset-password/([^?]+)\?email=#', $url, $m);

        return $m[1];
    }

    public function test_forgot_password_never_reveals_whether_the_email_exists(): void
    {
        Mail::fake();
        $user = $this->user();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Mail::assertSent(PasswordResetMail::class, 1);
        Mail::assertSent(PasswordResetMail::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_reset_token_is_stored_hashed_and_reset_changes_the_password(): void
    {
        $user = $this->user();
        $token = $this->requestResetToken($user);

        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertStringStartsWith('$2y$', $stored);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'BrandNewPass123', 'password_confirmation' => 'BrandNewPass123',
        ])->assertOk();

        $this->login($user->email, self::PASSWORD)->assertUnprocessable();
        $this->login($user->email, 'BrandNewPass123', [], '10.0.0.2')->assertOk();
    }

    public function test_reset_token_is_single_use(): void
    {
        $user = $this->user();
        $token = $this->requestResetToken($user);
        $body = ['email' => $user->email, 'token' => $token, 'password' => 'BrandNewPass123', 'password_confirmation' => 'BrandNewPass123'];

        $this->postJson('/api/v1/auth/reset-password', $body)->assertOk();
        $this->postJson('/api/v1/auth/reset-password', $body)
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_token');
    }

    public function test_reset_token_expires_after_60_minutes(): void
    {
        $user = $this->user();
        $token = $this->requestResetToken($user);
        $body = ['email' => $user->email, 'token' => $token, 'password' => 'BrandNewPass123', 'password_confirmation' => 'BrandNewPass123'];

        $this->travel(61)->minutes();
        $this->postJson('/api/v1/auth/reset-password', $body)->assertUnprocessable()->assertJsonPath('code', 'invalid_token');
        $this->assertTrue(\Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_reset_token_still_works_just_inside_the_hour(): void
    {
        $user = $this->user();
        $token = $this->requestResetToken($user);

        $this->travel(59)->minutes();
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token, 'password' => 'BrandNewPass123', 'password_confirmation' => 'BrandNewPass123',
        ])->assertOk();
    }

    public function test_wrong_token_and_unknown_email_give_the_same_error(): void
    {
        $user = $this->user();
        $this->requestResetToken($user);
        $pw = ['password' => 'BrandNewPass123', 'password_confirmation' => 'BrandNewPass123'];

        $a = $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => 'wrong'] + $pw);
        $b = $this->postJson('/api/v1/auth/reset-password', ['email' => 'nobody@example.com', 'token' => 'wrong'] + $pw);

        $a->assertUnprocessable();
        $this->assertSame($a->json(), $b->json());
    }

    public function test_reset_rejects_weak_passwords(): void
    {
        $user = $this->user();
        $token = $this->requestResetToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token, 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        // The token survives a validation failure.
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token, 'password' => 'BrandNewPass123', 'password_confirmation' => 'BrandNewPass123',
        ])->assertOk();
    }

    public function test_successful_reset_revokes_all_tokens_and_sessions(): void
    {
        $user = $this->user();
        $api = $user->createToken('api', ['profile']);
        $other = $user->createToken('other', ['profile']);
        $this->assertSame(2, $user->tokens()->where('revoked', false)->count());
        $oldRemember = $user->remember_token;

        $token = $this->requestResetToken($user);
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => $token, 'password' => 'BrandNewPass123', 'password_confirmation' => 'BrandNewPass123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->where('revoked', false)->count());
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertNotNull($api->accessToken);
        $this->assertNotNull($other->accessToken);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Mail::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => "u$i@example.com"])->assertOk();
        }
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'u9@example.com'])->assertStatus(429);
    }

    public function test_reset_mail_is_translated(): void
    {
        Mail::fake();
        $user = $this->user();
        $this->withHeaders(['Accept-Language' => 'nl'])->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $m) {
            $this->assertSame('nl', $m->locale);
            $html = $m->render();

            $this->assertStringContainsString('Nieuw wachtwoord kiezen', $html);

            return true;
        });
    }

    // ---- e-mail verification -----------------------------------------------

    public function test_register_sends_a_verification_mail_and_user_is_unverified(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/register', ['name' => 'Jane', 'email' => 'jane@example.com', 'password' => self::PASSWORD])
            ->assertCreated()->assertJsonPath('user.email_verified', false);

        Mail::assertSent(VerifyEmailMail::class, fn ($m) => $m->hasTo('jane@example.com'));
    }

    public function test_signed_link_verifies_the_email(): void
    {
        Mail::fake();
        $user = $this->user(['email_verified_at' => null]);
        $user->sendEmailVerificationNotification();
        $url = null;
        Mail::assertSent(VerifyEmailMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        Passport::actingAs($user->fresh(), ['profile']);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.email_verified', true);
    }

    public function test_tampered_or_expired_verification_links_are_rejected(): void
    {
        Mail::fake();
        $user = $this->user(['email_verified_at' => null]);
        $user->sendEmailVerificationNotification();
        $url = null;
        Mail::assertSent(VerifyEmailMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });

        $this->get($url . 'x')->assertForbidden();
        $this->get(preg_replace('#/verify/\d+/#', '/verify/999/', $url))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        Carbon::setTestNow(now()->addMinutes(61));
        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_for_a_different_email_hash_does_not_verify(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $url = \URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.com')]);

        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('status_error');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_endpoint_is_rate_limited_and_skips_verified_users(): void
    {
        Mail::fake();
        $user = $this->user(['email_verified_at' => null]);
        Passport::actingAs($user, ['profile']);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/email/resend')->assertStatus(202);
        }
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(429);
        Mail::assertSent(VerifyEmailMail::class, 3);

        Mail::fake();
        $this->travel(11)->minutes();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->postJson('/api/v1/auth/email/resend')->assertOk();
        Mail::assertNothingSent();
    }

    public function test_unverified_users_can_browse_and_buy_but_not_request_payouts(): void
    {
        $buyer = $this->user(['email_verified_at' => null]);
        Passport::actingAs($buyer, ['orders:read', 'orders:write', 'catalog:read']);
        $this->getJson('/api/v1/cart')->assertOk();
        $this->getJson('/api/v1/products')->assertOk();

        $vendor = $this->user(['role' => 'vendor', 'email_verified_at' => null]);
        Passport::actingAs($vendor, ['vendor:manage']);
        $this->postJson('/api/v1/payouts', [
            'amount_cents' => 100, 'currency' => 'EUR', 'method' => 'monero', 'destination_address' => 'x',
        ])->assertForbidden()->assertJsonPath('code', 'email_unverified');
        $this->getJson('/api/v1/payouts')->assertOk(); // reading is still fine
    }

    public function test_verified_vendor_passes_the_payout_gate(): void
    {
        $vendor = $this->user(['role' => 'vendor']);
        Passport::actingAs($vendor, ['vendor:manage']);
        $res = $this->postJson('/api/v1/payouts', [
            'amount_cents' => 100, 'currency' => 'EUR', 'method' => 'monero', 'destination_address' => 'x',
        ]);
        $this->assertNotSame('email_unverified', $res->json('code'));
        $this->assertNotSame(403, $res->status());
    }

    public function test_unverified_vendor_can_save_drafts_but_not_publish_products(): void
    {
        $vendor = $this->user(['role' => 'vendor', 'email_verified_at' => null]);
        Passport::actingAs($vendor, ['catalog:write']);

        $this->postJson('/api/v1/products', ['title' => 'Mug', 'price_cents' => 1500])->assertCreated();
        $this->postJson('/api/v1/products', ['title' => 'Mug2', 'price_cents' => 1500, 'status' => 'active'])
            ->assertForbidden()->assertJsonPath('code', 'email_unverified');
        $this->assertDatabaseMissing('products', ['title' => 'Mug2']);

        $draft = $vendor->products()->where('title', 'Mug')->firstOrFail();
        $this->putJson("/api/v1/products/{$draft->id}", ['status' => 'active'])->assertForbidden();
        $this->assertSame('draft', $draft->fresh()->status);
        $this->putJson("/api/v1/products/{$draft->id}", ['title' => 'Mug renamed'])->assertOk();

        // Once verified, publishing works.
        $vendor->forceFill(['email_verified_at' => now()])->save();
        $this->putJson("/api/v1/products/{$draft->id}", ['status' => 'active'])->assertOk();
    }

    // ---- two-factor --------------------------------------------------------

    public function test_setup_returns_secret_and_uri_and_confirm_issues_ten_hashed_recovery_codes(): void
    {
        $user = $this->user();
        Passport::actingAs($user, ['profile']);

        $setup = $this->postJson('/api/v1/auth/2fa/setup')->assertOk();
        $secret = $setup->json('data.secret');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $uri = $setup->json('data.otpauth_uri');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString("secret=$secret", $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);

        // Stored encrypted, never in clear text.
        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, $raw);
        $this->assertSame($secret, $user->fresh()->two_factor_secret);

        $user->refresh(); // the acting user instance is reused across requests in tests
        $this->getJson('/api/v1/auth/2fa')->assertJsonPath('data.enabled', false)->assertJsonPath('data.pending_setup', true);
        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])->assertUnprocessable()->assertJsonPath('code', 'invalid_code');

        $res = $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $this->totp()->code($secret, $this->totp()->timeStep())])->assertOk();
        $codes = $res->json('data.recovery_codes');
        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertDatabaseMissing('two_factor_recovery_codes', ['code_hash' => $code]);
        }
        $this->assertSame(10, DB::table('two_factor_recovery_codes')->where('user_id', $user->id)->count());
        $user->refresh();
        $this->getJson('/api/v1/auth/2fa')->assertJsonPath('data.enabled', true)->assertJsonPath('data.recovery_codes_remaining', 10);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.two_factor_enabled', true);
        $this->postJson('/api/v1/auth/2fa/setup')->assertStatus(409);
    }

    public function test_login_requires_the_code_when_two_factor_is_enabled(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);

        $this->login($user->email)->assertUnprocessable()
            ->assertJsonPath('code', 'two_factor_required')->assertJsonPath('two_factor_required', true);
        $this->login($user->email, self::PASSWORD, ['code' => '000000'])->assertUnprocessable()->assertJsonPath('code', 'invalid_code');

        // Wrong password never reveals the 2FA state.
        $this->login($user->email, 'wrong-password')->assertUnprocessable()->assertJsonMissingPath('two_factor_required');

        $code = $this->totp()->code($secret, $this->totp()->timeStep());
        $this->login($user->email, self::PASSWORD, ['code' => $code])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);
        $code = $this->totp()->code($secret, $this->totp()->timeStep());

        $this->login($user->email, self::PASSWORD, ['code' => $code])->assertOk();
        $this->login($user->email, self::PASSWORD, ['code' => $code], '10.0.0.2')->assertUnprocessable()->assertJsonPath('code', 'invalid_code');
        // The code used to enable 2FA cannot be replayed either.
    }

    public function test_the_code_used_to_confirm_setup_cannot_be_reused_to_log_in(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);
        // enableTwoFactor() moved one step on, so the confirm code is the previous step: still in the window, but used.
        $code = $this->totp()->code($secret, $this->totp()->timeStep() - 1);

        $this->login($user->email, self::PASSWORD, ['code' => $code])->assertUnprocessable();
    }

    public function test_one_step_of_clock_drift_is_accepted_but_not_two(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);
        $this->travel(60)->seconds();
        $now = $this->totp()->timeStep();

        $this->login($user->email, self::PASSWORD, ['code' => $this->totp()->code($secret, $now - 2)])->assertUnprocessable();
        $this->login($user->email, self::PASSWORD, ['code' => $this->totp()->code($secret, $now + 2)], '10.0.0.2')->assertUnprocessable();
        $this->login($user->email, self::PASSWORD, ['code' => $this->totp()->code($secret, $now - 1)], '10.0.0.3')->assertOk();
    }

    public function test_one_step_ahead_is_accepted(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);
        $this->travel(60)->seconds();
        $now = $this->totp()->timeStep();

        $this->login($user->email, self::PASSWORD, ['code' => $this->totp()->code($secret, $now + 1)])->assertOk();
    }

    public function test_recovery_codes_work_exactly_once(): void
    {
        $user = $this->user();
        [, $codes] = $this->enableTwoFactor($user);

        $this->login($user->email, self::PASSWORD, ['recovery_code' => $codes[0]])->assertOk();
        $this->login($user->email, self::PASSWORD, ['recovery_code' => $codes[0]], '10.0.0.2')
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_code');
        $this->login($user->email, self::PASSWORD, ['recovery_code' => strtoupper($codes[1])], '10.0.0.3')->assertOk(); // case-insensitive

        Passport::actingAs($user->fresh(), ['profile']);
        $this->getJson('/api/v1/auth/2fa')->assertJsonPath('data.recovery_codes_remaining', 8);
    }

    public function test_two_factor_code_guessing_locks_the_account(): void
    {
        $user = $this->user();
        [$secret] = $this->enableTwoFactor($user);

        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, self::PASSWORD, ['code' => '000000'], "10.1.0.$i")->assertUnprocessable();
        }
        // Even the right code is refused now.
        $this->travel(120)->seconds();
        $good = $this->totp()->code($secret, $this->totp()->timeStep());
        $this->login($user->email, self::PASSWORD, ['code' => $good], '10.1.0.9')->assertStatus(429)->assertJsonPath('code', 'account_locked');
    }

    public function test_disable_needs_password_and_code(): void
    {
        $user = $this->user();
        [$secret, $codes] = $this->enableTwoFactor($user);
        Passport::actingAs($user->fresh(), ['profile']);
        $this->travel(60)->seconds();
        $good = $this->totp()->code($secret, $this->totp()->timeStep());

        $this->postJson('/api/v1/auth/2fa/disable', ['password' => 'wrong', 'code' => $good])->assertUnprocessable();
        $this->postJson('/api/v1/auth/2fa/disable', ['password' => self::PASSWORD, 'code' => '000000'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/2fa/disable', ['password' => self::PASSWORD])->assertUnprocessable();
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->postJson('/api/v1/auth/2fa/disable', ['password' => self::PASSWORD, 'code' => $good])->assertOk();
        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertSame(0, DB::table('two_factor_recovery_codes')->where('user_id', $user->id)->count());
        $this->assertNotEmpty($codes);
    }

    public function test_regenerating_recovery_codes_invalidates_the_old_ones(): void
    {
        $user = $this->user();
        [$secret, $old] = $this->enableTwoFactor($user);
        Passport::actingAs($user->fresh(), ['profile']);
        $this->travel(60)->seconds();
        $good = $this->totp()->code($secret, $this->totp()->timeStep());

        $new = $this->postJson('/api/v1/auth/2fa/recovery-codes', ['password' => self::PASSWORD, 'code' => $good])
            ->assertOk()->json('data.recovery_codes');
        $this->assertCount(10, $new);
        $this->assertEmpty(array_intersect($old, $new));

        $this->app['auth']->forgetGuards();
        $this->login($user->email, self::PASSWORD, ['recovery_code' => $old[0]])->assertUnprocessable();
        $this->login($user->email, self::PASSWORD, ['recovery_code' => $new[0]], '10.0.0.2')->assertOk();
    }

    public function test_two_factor_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/2fa')->assertUnauthorized();
        $this->postJson('/api/v1/auth/2fa/setup')->assertUnauthorized();
        $this->postJson('/api/v1/auth/email/resend')->assertUnauthorized();
    }

    // ---- account lockout ---------------------------------------------------

    public function test_five_failed_logins_lock_the_account_even_from_other_ips(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, 'wrong-password', [], "10.2.0.$i")->assertUnprocessable();
        }

        $res = $this->login($user->email, self::PASSWORD, [], '10.2.0.99');
        $res->assertStatus(429)->assertJsonPath('code', 'account_locked');
        $this->assertNotNull($res->headers->get('Retry-After'));
    }

    public function test_lockout_expires_after_15_minutes(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, 'wrong-password', [], "10.2.0.$i");
        }
        $this->login($user->email, self::PASSWORD, [], '10.2.0.50')->assertStatus(429);

        $this->travel(16)->minutes();
        $this->login($user->email, self::PASSWORD, [], '10.2.0.51')->assertOk();
    }

    public function test_unknown_emails_are_locked_the_same_way(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('ghost@example.com', 'wrong-password', [], "10.3.0.$i")->assertUnprocessable();
        }
        $this->login('ghost@example.com', 'wrong-password', [], '10.3.0.50')->assertStatus(429)->assertJsonPath('code', 'account_locked');
    }

    public function test_successful_login_resets_the_failure_counter(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 4; $i++) {
            $this->login($user->email, 'wrong-password', [], "10.4.0.$i");
        }
        $this->login($user->email, self::PASSWORD, [], '10.4.0.10')->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->login($user->email, 'wrong-password', [], "10.4.1.$i")->assertUnprocessable();
        }
        $this->login($user->email, self::PASSWORD, [], '10.4.1.10')->assertOk();
    }

    public function test_failed_login_keeps_the_original_error_shape(): void
    {
        $user = $this->user();
        $this->login($user->email, 'wrong-password')->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
