<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_baseline_security_headers(): void
    {
        $r = $this->get('/login');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('Referrer-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('http://', $r->headers->get('Content-Security-Policy'));
    }

    public function test_api_responses_have_headers_but_no_html_csp(): void
    {
        $r = $this->getJson('/api/v1/products');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNull($r->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_only_on_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }
}
