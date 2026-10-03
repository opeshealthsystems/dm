<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPagesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** @return list<string> */
    private function urls(): array
    {
        return [
            route('seller.overview'), route('seller.orders'), route('seller.orders.show', 12),
            route('seller.products'), route('seller.products.create'), route('seller.products.edit', 7),
            route('seller.wallet'), route('seller.reviews'), route('seller.messages'),
            route('seller.developers'), route('seller.profile'),
        ];
    }

    public function test_every_seller_page_renders_for_vendors_and_admins(): void
    {
        foreach (['vendor', 'admin'] as $role) {
            $u = $this->user($role);
            foreach ($this->urls() as $url) {
                $this->actingAs($u)->get($url)->assertOk()->assertSee('x-data="seller', false);
            }
        }
    }

    public function test_seller_pages_carry_their_translations_to_the_browser(): void
    {
        $this->actingAs($this->user('vendor'))->get(route('seller.wallet'))
            ->assertOk()->assertSee('window.i18n', false)->assertSee(__('seller.wallet.payout_title'));
    }

    public function test_guests_go_to_login_and_buyers_to_their_dashboard(): void
    {
        foreach ($this->urls() as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $buyer = $this->user('buyer');
        foreach ($this->urls() as $url) {
            $this->actingAs($buyer)->get($url)->assertRedirect(route('dashboard'));
        }
    }

    public function test_detail_routes_reject_non_numeric_ids(): void
    {
        $this->actingAs($this->user('vendor'));
        $this->get('/seller/orders/abc')->assertNotFound();
        $this->get('/seller/products/abc/edit')->assertNotFound();
    }

    public function test_seller_pages_render_right_to_left_in_arabic(): void
    {
        $this->actingAs($this->user('vendor'));
        $this->get('/locale/ar');
        $this->get(route('seller.orders'))->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_seller_views_use_logical_classes_only_and_no_raw_colours_or_html_binding(): void
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

    public function test_every_seller_translation_key_used_in_views_exists_in_english(): void
    {
        $lang = require base_path('lang/en/seller.php');
        $common = require base_path('lang/en/common.php');
        $missing = [];
        $found = 0;

        foreach ($this->viewFiles() as $file) {
            $src = file_get_contents($file);
            preg_match_all("/(?:__\(|\bt\()\s*['\"]((?:seller|common)\.[A-Za-z0-9_.]+)['\"]/", $src, $m);
            foreach (array_unique($m[1]) as $key) {
                if (str_ends_with($key, '.') || str_ends_with($key, '_')) {
                    continue; // dynamic key, checked in test_dynamic_translation_keys_exist
                }
                $found++;
                [$group, $rest] = explode('.', $key, 2);
                $source = $group === 'seller' ? $lang : $common;
                if (! is_string(data_get($source, $rest))) {
                    $missing[] = basename($file) . ': ' . $key;
                }
            }
        }

        $this->assertGreaterThan(100, $found, 'The scanner found suspiciously few keys.');
        $this->assertSame([], $missing, "Missing translation keys:\n" . implode("\n", $missing));
    }

    public function test_dynamic_translation_keys_exist(): void
    {
        $lang = require base_path('lang/en/seller.php');
        $keys = [
            'method' => ['bitcoin', 'monero'],
            'products' => ['status_draft', 'status_active', 'status_archived'],
            'wallet' => ['status_pending', 'status_approved', 'status_paid', 'status_rejected',
                'type_sale_credit', 'type_platform_fee', 'type_payout_debit', 'type_payout_reversal'],
            'order' => ['escrow_pending', 'escrow_held', 'escrow_released', 'escrow_refunded'],
            'developers' => ['key_active', 'key_revoked', 'key_expired'],
        ];
        foreach ($keys as $group => $names) {
            foreach ($names as $n) {
                $this->assertIsString($lang[$group][$n] ?? null, "seller.$group.$n");
            }
        }
        foreach (['pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'disputed'] as $s) {
            $this->assertNotSame('common.status.' . $s, __('common.status.' . $s));
        }
    }

    /** @return list<string> */
    private function viewFiles(): array
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views/seller')));
        $files = [];
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $files[] = $f->getPathname();
            }
        }

        return $files;
    }
}
