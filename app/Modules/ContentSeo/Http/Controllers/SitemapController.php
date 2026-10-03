<?php

namespace App\Modules\ContentSeo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\ContentSeo\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * robots.txt and sitemap.xml. The sitemap is one file while the site is small and becomes an
 * index of chunk files (pages, products-N, vendors-N) once it passes `seo.sitemap_chunk` URLs.
 */
class SitemapController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /account',
            'Disallow: /seller',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /oauth',
            'Disallow: /docs',
            'Allow: /',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
        ];

        return $this->respond(implode("\n", $lines) . "\n", 'text/plain');
    }

    public function index(): Response
    {
        $xml = Cache::remember('sitemap:' . url('/') . ':index', config('seo.cache_ttl'), function () {
            $chunk = $this->chunkSize();
            $products = Product::query()->active()->count();
            $vendors = $this->vendors()->count();
            $pages = $this->pageUrls();

            if (count($pages) + $products + $vendors <= $chunk) {
                return $this->urlset(array_merge($pages, $this->productUrls(0, $chunk), $this->vendorUrls(0, $chunk)));
            }

            $maps = [url('/sitemaps/pages-1.xml')];
            foreach (['products' => $products, 'vendors' => $vendors] as $type => $count) {
                for ($n = 1; $n <= (int) ceil($count / $chunk); $n++) {
                    $maps[] = url("/sitemaps/$type-$n.xml");
                }
            }

            return $this->sitemapIndex(array_slice($maps, 0, (int) config('seo.sitemap_max_urls')));
        });

        return $this->respond($xml, 'application/xml');
    }

    public function part(string $type, int $n): Response
    {
        abort_unless(in_array($type, ['pages', 'products', 'vendors'], true) && $n >= 1, 404);

        $xml = Cache::remember('sitemap:' . url('/') . ":$type:$n", config('seo.cache_ttl'), function () use ($type, $n) {
            $chunk = $this->chunkSize();
            $offset = ($n - 1) * $chunk;

            return $this->urlset(match ($type) {
                'pages' => $n === 1 ? $this->pageUrls() : [],
                'products' => $this->productUrls($offset, $chunk),
                'vendors' => $this->vendorUrls($offset, $chunk),
            });
        });

        return $this->respond($xml, 'application/xml');
    }

    private function chunkSize(): int
    {
        return max(1, min((int) config('seo.sitemap_chunk'), (int) config('seo.sitemap_max_urls')));
    }

    private function vendors()
    {
        return User::query()->where('role', User::ROLE_VENDOR)->whereNull('suspended_at');
    }

    /** @return list<array{loc:string,lastmod:?string}> */
    private function pageUrls(): array
    {
        $urls = [['loc' => url('/'), 'lastmod' => null]];
        foreach (Seo::STATIC_PAGES as $route) {
            $urls[] = ['loc' => route($route), 'lastmod' => null];
        }
        foreach (Category::query()->orderBy('id')->limit((int) config('seo.sitemap_max_urls') - 20)->pluck('id') as $id) {
            $urls[] = ['loc' => url('/') . '?category=' . $id, 'lastmod' => null];
        }

        return $urls;
    }

    private function productUrls(int $offset, int $limit): array
    {
        return Product::query()->active()->orderBy('id')->skip($offset)->take($limit)->get(['id', 'slug', 'updated_at'])
            ->map(fn ($p) => ['loc' => route('store.product', $p->slug), 'lastmod' => $p->updated_at?->toIso8601String()])->all();
    }

    private function vendorUrls(int $offset, int $limit): array
    {
        return $this->vendors()->orderBy('id')->skip($offset)->take($limit)->get(['id', 'updated_at'])
            ->map(fn ($v) => ['loc' => route('store.vendor', $v->id), 'lastmod' => $v->updated_at?->toIso8601String()])->all();
    }

    private function urlset(array $urls): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach (array_slice($urls, 0, (int) config('seo.sitemap_max_urls')) as $u) {
            $out .= '<url><loc>' . e($u['loc']) . '</loc>';
            if ($u['lastmod']) {
                $out .= '<lastmod>' . e($u['lastmod']) . '</lastmod>';
            }
            // The loc may already carry a query string (categories).
            $sep = str_contains($u['loc'], '?') ? '&' : '?';
            foreach (array_keys(config('locales.supported')) as $code) {
                $href = $code === config('locales.default') ? $u['loc'] : $u['loc'] . $sep . 'lang=' . $code;
                $out .= '<xhtml:link rel="alternate" hreflang="' . e($code) . '" href="' . e($href) . '"/>';
            }
            $out .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . e($u['loc']) . '"/>';
            $out .= "</url>\n";
        }

        return $out . '</urlset>' . "\n";
    }

    private function sitemapIndex(array $maps): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($maps as $loc) {
            $out .= '<sitemap><loc>' . e($loc) . "</loc></sitemap>\n";
        }

        return $out . '</sitemapindex>' . "\n";
    }

    private function respond(string $body, string $type): Response
    {
        return response($body, 200, [
            'Content-Type' => $type . '; charset=UTF-8',
            'Cache-Control' => 'public, max-age=' . config('seo.cache_ttl'),
        ]);
    }
}
