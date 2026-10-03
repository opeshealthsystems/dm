<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Public content pages, SEO tags, robots/sitemap and the error pages. */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['/about', '/how-it-works', '/faq', '/terms', '/privacy', '/sellers', '/contact'];

    private function locales(): array
    {
        return array_keys(config('locales.supported'));
    }

    private function vendor(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => 'vendor', 'handle' => 'v' . uniqid(), 'shop_name' => 'Fine Goods']);
    }

    private function product(User $vendor, array $attrs = []): Product
    {
        return $vendor->products()->create($attrs + [
            'title' => 'Blue Mug', 'description' => 'A sturdy blue mug.', 'price_cents' => 1250, 'currency' => 'EUR', 'stock' => 4, 'status' => 'active',
        ])->refresh();
    }

    /** @return list<array> decoded JSON-LD blocks of a response */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    public function test_every_page_renders_in_every_language_with_seo_tags(): void
    {
        foreach ($this->locales() as $locale) {
            foreach (array_merge(['/'], self::PAGES) as $path) {
                $res = $this->get($path . '?lang=' . $locale)->assertOk();
                $html = $res->getContent();

                $this->assertStringContainsString('<html lang="' . $locale . '"', $html, "$path $locale");
                $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]+"#', $html, "$path $locale canonical");
                $this->assertMatchesRegularExpression('#<meta name="description" content="[^"]{20,}"#', $html, "$path $locale description");
                $this->assertStringContainsString('property="og:title"', $html);
                $this->assertStringContainsString('name="twitter:card"', $html);
                $this->assertStringContainsString('hreflang="x-default"', $html);
                $this->assertStringContainsString('name="robots" content="index,follow"', $html);
                $this->assertStringNotContainsString('pages.', strip_tags(preg_replace('#<script.*?</script>#s', '', $html)), "$path $locale shows a raw key");
                foreach ($this->locales() as $alt) {
                    $this->assertStringContainsString('hreflang="' . $alt . '"', $html, "$path $locale lacks hreflang $alt");
                }
                foreach ($this->jsonLd($html) as $block) {
                    $this->assertSame('https://schema.org', $block['@context']);
                }
            }
        }
    }

    public function test_canonical_is_the_current_language_version_without_other_query_parameters(): void
    {
        $this->get('/faq?lang=de&utm_source=x')->assertSee('<link rel="canonical" href="http://localhost/faq?lang=de">', false);
        $this->get('/faq?lang=en')->assertSee('<link rel="canonical" href="http://localhost/faq">', false)
            ->assertSee('<link rel="alternate" hreflang="fr" href="http://localhost/faq?lang=fr">', false);
    }

    public function test_page_titles_differ_and_use_the_site_name(): void
    {
        $titles = [];
        foreach (self::PAGES as $path) {
            preg_match('#<title>(.*?)</title>#', $this->get($path)->getContent(), $m);
            $titles[] = html_entity_decode($m[1]);
            $this->assertStringEndsWith(config('app.name'), end($titles));
        }
        $this->assertCount(count(self::PAGES), array_unique($titles));
    }

    public function test_faq_has_details_accordion_and_valid_faqpage_json_ld(): void
    {
        $html = $this->get('/faq')->assertOk()->getContent();
        $this->assertGreaterThanOrEqual(10, substr_count($html, '<details>'));
        $this->assertLessThanOrEqual(12, substr_count($html, '<details>'));

        $types = array_column($this->jsonLd($html), '@type');
        $this->assertContains('FAQPage', $types);
        $this->assertContains('BreadcrumbList', $types);
        $faq = collect($this->jsonLd($html))->firstWhere('@type', 'FAQPage');
        $this->assertCount(substr_count($html, '<details>'), $faq['mainEntity']);
        // The commission placeholder is filled in, never shown raw.
        $this->assertStringNotContainsString(':percent', $html);
        $this->assertStringContainsString('5%', $html);
    }

    public function test_home_has_organization_json_ld_and_footer_links(): void
    {
        $res = $this->get('/')->assertOk();
        $types = array_column($this->jsonLd($res->getContent()), '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);

        foreach (['about', 'how', 'faq', 'terms', 'privacy', 'sellers', 'contact'] as $name) {
            $res->assertSee('href="' . route("pages.$name") . '"', false);
        }
    }

    public function test_product_page_is_server_rendered_with_product_json_ld(): void
    {
        $vendor = $this->vendor();
        $product = $this->product($vendor);
        $product->forceFill(['rating_avg' => 4.5, 'rating_count' => 2])->save();

        $res = $this->get('/p/' . $product->slug)->assertOk();
        $html = $res->getContent();

        $res->assertSee('<title>Blue Mug | ' . config('app.name') . '</title>', false)
            ->assertSee('A sturdy blue mug.')
            ->assertSee('Fine Goods')
            ->assertSee("productPage('" . $product->slug . "'", false)  // Alpine hydration still wired up
            ->assertSee('property="og:type" content="product"', false)
            ->assertSee('<link rel="canonical" href="' . route('store.product', $product->slug) . '">', false);

        $blocks = collect($this->jsonLd($html));
        $schema = $blocks->firstWhere('@type', 'Product');
        $this->assertSame('Blue Mug', $schema['name']);
        $this->assertSame('AggregateOffer', $schema['offers']['@type']);
        $this->assertSame('12.50', $schema['offers']['lowPrice']);
        $this->assertSame('EUR', $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['offers'][0]['availability']);
        $this->assertSame(2, $schema['aggregateRating']['reviewCount']);
        $this->assertEqualsWithDelta(4.5, $schema['aggregateRating']['ratingValue'], 0.001);
        $this->assertNotNull($blocks->firstWhere('@type', 'BreadcrumbList'));
    }

    public function test_product_json_ld_omits_rating_without_reviews_and_marks_out_of_stock(): void
    {
        $product = $this->product($this->vendor(), ['stock' => 0]);
        $schema = collect($this->jsonLd($this->get('/p/' . $product->slug)->getContent()))->firstWhere('@type', 'Product');

        $this->assertArrayNotHasKey('aggregateRating', $schema);
        $this->assertSame('https://schema.org/OutOfStock', $schema['offers']['offers'][0]['availability']);
    }

    public function test_product_text_is_escaped_in_html_and_json_ld(): void
    {
        $product = $this->product($this->vendor(), ['title' => 'Tea </script><b>x</b>', 'description' => 'Fine "tea" & more']);
        $html = $this->get('/p/' . $product->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('<b>x</b>', $html);
        // JSON-LD still parses even though the title contains a closing script tag.
        $this->assertSame('Tea </script><b>x</b>', collect($this->jsonLd($html))->firstWhere('@type', 'Product')['name']);
    }

    public function test_unknown_and_inactive_products_are_a_real_404_and_owners_can_still_preview(): void
    {
        $vendor = $this->vendor();
        $draft = $this->product($vendor, ['status' => 'draft']);

        $this->get('/p/does-not-exist')->assertNotFound();
        $this->get('/p/' . $draft->slug)->assertNotFound();

        $this->actingAs($vendor)->get('/p/' . $draft->slug)->assertOk()->assertSee('content="noindex,nofollow"', false);
    }

    public function test_vendor_page_is_server_rendered(): void
    {
        $vendor = $this->vendor(['shop_description' => 'Handmade ceramics.']);
        $res = $this->get('/v/' . $vendor->id)->assertOk();

        $res->assertSee('<title>Fine Goods | ' . config('app.name') . '</title>', false)
            ->assertSee('Handmade ceramics.')
            ->assertSee('vendorPage', false);
        $this->assertContains('Organization', array_column($this->jsonLd($res->getContent()), '@type'));

        $buyer = User::factory()->create(['role' => 'buyer', 'handle' => 'b' . uniqid()]);
        $this->get('/v/' . $buyer->id)->assertNotFound();
        $this->get('/v/999999')->assertNotFound();
    }

    public function test_auth_and_dashboard_pages_are_noindex_without_canonical_or_hreflang(): void
    {
        foreach (['/login', '/register'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('name="robots" content="noindex,nofollow"', false)
                ->assertDontSee('rel="canonical"', false)
                ->assertDontSee('hreflang=', false);
        }
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'handle' => 'b' . uniqid()]))
            ->get('/account/orders')->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_main_css_is_preloaded_and_scripts_are_deferred(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('#<link rel="preload" as="style" href="[^"]+\.css"#', $html);
        $this->assertMatchesRegularExpression('#<script type="module" src="[^"]+\.js"#', $html); // modules are deferred by default
        $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*(type="module"|type="application/ld\+json"))[^>]*\ssrc=#', $html);
    }

    public function test_htaccess_caches_build_assets_as_immutable(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('/build/', $htaccess);
        $this->assertStringContainsString('max-age=31536000, immutable', $htaccess);
    }

    public function test_robots_txt(): void
    {
        $res = $this->get('/robots.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=3600', $res->headers->get('Cache-Control'));
        $this->assertNull($res->headers->get('Set-Cookie'));

        $body = $res->getContent();
        foreach (['/account', '/seller', '/admin', '/api', '/oauth', '/docs'] as $path) {
            $this->assertStringContainsString("Disallow: $path\n", $body);
        }
        $this->assertStringContainsString("Allow: /\n", $body);
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $body);
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'a static robots.txt would shadow the route');
    }

    public function test_sitemap_lists_pages_categories_products_and_vendors_with_alternates(): void
    {
        $vendor = $this->vendor();
        $active = $this->product($vendor);
        $draft = $this->product($vendor, ['title' => 'Hidden', 'status' => 'draft']);
        $suspended = $this->vendor(['suspended_at' => now()]);
        $cat = Category::query()->create(['name' => 'Home', 'slug' => 'home']);

        $res = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=3600', $res->headers->get('Cache-Control'));
        $xml = $res->getContent();

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'sitemap is valid XML');

        foreach (['http://localhost', 'http://localhost/about', 'http://localhost/how-it-works', 'http://localhost/faq', 'http://localhost/terms',
            'http://localhost/privacy', 'http://localhost/sellers', 'http://localhost/contact',
            route('store.product', $active->slug), route('store.vendor', $vendor->id), 'http://localhost?category=' . $cat->id] as $loc) {
            $this->assertStringContainsString("<loc>$loc</loc>", $xml, "missing $loc");
        }
        $this->assertStringNotContainsString($draft->slug, $xml);
        $this->assertStringNotContainsString('/v/' . $suspended->id . '<', $xml);

        $this->assertStringContainsString('hreflang="ar" href="http://localhost/about?lang=ar"', $xml);
        $this->assertStringContainsString('hreflang="x-default" href="http://localhost/about"', $xml);
        $this->assertSame(12 + 1, substr_count(explode('</url>', explode('<loc>http://localhost/about</loc>', $xml)[1])[0], '<xhtml:link'));
    }

    public function test_sitemap_is_cached_for_an_hour(): void
    {
        $vendor = $this->vendor();
        $this->get('/sitemap.xml')->assertOk();
        $this->assertTrue(Cache::has('sitemap:http://localhost:index'));

        $late = $this->product($vendor, ['title' => 'Late addition']);
        $this->get('/sitemap.xml')->assertDontSee($late->slug);   // still the cached copy

        Cache::flush();
        $this->get('/sitemap.xml')->assertSee($late->slug);
    }

    public function test_sitemap_is_chunked_into_an_index_when_it_grows(): void
    {
        config(['seo.sitemap_chunk' => 3]);
        $vendor = $this->vendor();
        $products = collect(range(1, 5))->map(fn ($i) => $this->product($vendor, ['title' => "Item $i"]));

        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<sitemapindex', $index);
        $this->assertStringContainsString('http://localhost/sitemaps/pages-1.xml', $index);
        $this->assertStringContainsString('http://localhost/sitemaps/products-1.xml', $index);
        $this->assertStringContainsString('http://localhost/sitemaps/products-2.xml', $index);
        $this->assertStringNotContainsString('products-3.xml', $index);
        $this->assertStringContainsString('http://localhost/sitemaps/vendors-1.xml', $index);

        $part1 = $this->get('/sitemaps/products-1.xml')->assertOk()->getContent();
        $part2 = $this->get('/sitemaps/products-2.xml')->assertOk()->getContent();
        $this->assertSame(3, substr_count($part1, '<loc>'));
        $this->assertSame(2, substr_count($part2, '<loc>'));
        foreach ($products as $p) {
            $this->assertSame(1, substr_count($part1 . $part2, '<loc>' . route('store.product', $p->slug) . '</loc>'));
        }
        $this->get('/sitemaps/pages-1.xml')->assertOk()->assertSee('/about');
        $this->get('/sitemaps/bogus-1.xml')->assertNotFound();
        $this->get('/sitemaps/products-0.xml')->assertNotFound();
    }

    public function test_sitemap_never_exceeds_the_protocol_limit(): void
    {
        config(['seo.sitemap_chunk' => 999999]);
        $this->assertLessThanOrEqual(50000, (int) min(config('seo.sitemap_chunk'), config('seo.sitemap_max_urls')));
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_error_pages_are_designed_translated_and_noindex(): void
    {
        foreach ($this->locales() as $locale) {
            $res = $this->get('/definitely/not/a/page?lang=' . $locale)->assertNotFound();
            $res->assertSee(__('pages.errors.404.title', [], $locale))
                ->assertSee(__('pages.errors.404.message', [], $locale))
                ->assertSee('name="robots" content="noindex,nofollow"', false)
                ->assertSee('<html lang="' . $locale . '"', false);
            $this->assertNotSame('pages.errors.404.title', __('pages.errors.404.title', [], $locale));
        }
        $this->get('/p/nope')->assertNotFound()->assertSee(__('pages.errors.home'));
    }

    public function test_api_404_stays_json(): void
    {
        $this->getJson('/api/v1/nothing-here')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_error_views_exist_for_every_code_and_have_all_texts(): void
    {
        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            $this->assertFileExists(resource_path("views/errors/$code.blade.php"));
            foreach ($this->locales() as $locale) {
                foreach (['title', 'message'] as $part) {
                    $this->assertNotSame("pages.errors.$code.$part", __("pages.errors.$code.$part", [], $locale));
                }
            }
            $html = view("errors.$code")->render();
            $this->assertStringContainsString(__("pages.errors.$code.title"), html_entity_decode($html));
        }
    }

    public function test_pages_use_no_hard_coded_text_hex_colours_or_physical_directions(): void
    {
        $files = array_merge(
            glob(resource_path('views/pages/*.blade.php')),
            glob(resource_path('views/errors/*.blade.php')),
            [resource_path('views/admin/support.blade.php'), resource_path('views/partials/seo.blade.php'),
                resource_path('views/partials/breadcrumbs.blade.php'), resource_path('views/partials/jsonld.blade.php')],
        );
        foreach ($files as $file) {
            $src = file_get_contents($file);
            $this->assertStringNotContainsString('x-html', $src, basename($file));
            $this->assertDoesNotMatchRegularExpression('/(?<![&\w])#[0-9a-fA-F]{6}\b/', $src, basename($file) . ' hard-codes a colour');
            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*(?:^|\s)-?(?:ml|mr|pl|pr|left|right|text-left|text-right|border-l|border-r|rounded-l|rounded-r)-[\w\[]/',
                $src, basename($file) . ' uses physical left/right classes'
            );

            $text = preg_replace(['/^@section\(.*$/m', '/<script.*?<\/script>/s', '/<style.*?<\/style>/s', '/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s', '/@php.*?@endphp/s', '/<\/?[a-zA-Z][^\s>]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/s'], ' ', $src);
            $text = preg_replace('/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', ' ', $text);
            $this->assertSame('', trim(preg_replace('/\s+/u', ' ', $text)), basename($file) . ' contains hard-coded text');
        }
    }
}
