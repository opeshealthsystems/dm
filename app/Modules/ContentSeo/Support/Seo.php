<?php

namespace App\Modules\ContentSeo\Support;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/** Helpers for meta tags, hreflang alternates and JSON-LD. Everything here is pure and testable. */
class Seo
{
    /** Open Graph locale codes. */
    private const OG_LOCALES = [
        'en' => 'en_US', 'nl' => 'nl_NL', 'de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES', 'it' => 'it_IT',
        'pt' => 'pt_PT', 'ru' => 'ru_RU', 'zh-hans' => 'zh_CN', 'ja' => 'ja_JP', 'ko' => 'ko_KR', 'ar' => 'ar_AR',
    ];

    /** Route names of the static pages, in footer order. */
    public const STATIC_PAGES = ['pages.about', 'pages.how', 'pages.faq', 'pages.sellers', 'pages.contact', 'pages.terms', 'pages.privacy'];

    /** The default marketplace commission as a plain percentage, e.g. "5" or "2.5". */
    public static function commissionPercent(): string
    {
        return rtrim(rtrim(number_format(((int) config('marketplace.default_commission_bps')) / 100, 2, '.', ''), '0'), '.');
    }

    public static function ogLocale(string $code): string
    {
        return self::OG_LOCALES[$code] ?? 'en_US';
    }

    /** URL of one language version: the default language is the bare URL, the others use ?lang=. */
    public static function localeUrl(string $baseUrl, string $code): string
    {
        return $code === config('locales.default') ? $baseUrl : $baseUrl . '?lang=' . $code;
    }

    /** @return array<string,string> locale code => absolute URL */
    public static function alternates(string $baseUrl): array
    {
        $out = [];
        foreach (array_keys(config('locales.supported')) as $code) {
            $out[$code] = self::localeUrl($baseUrl, $code);
        }

        return $out;
    }

    public static function limit(string $text, int $max = 160): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($text))), $max, '…');
    }

    /** A <script type="application/ld+json"> tag. Encoding is HTML-safe (no raw "<" or "&"). */
    public static function jsonLd(array $data): HtmlString
    {
        $json = json_encode(
            ['@context' => 'https://schema.org'] + $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        );

        return new HtmlString('<script type="application/ld+json">' . $json . '</script>');
    }

    /** "12.50" from integer cents (two-decimal currencies). */
    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Human price in the active language, e.g. "€12.50"; falls back to "12.50 EUR". */
    public static function price(int $cents, string $currency): string
    {
        try {
            $text = Number::currency($cents / 100, $currency, str_replace('-', '_', app()->getLocale()));
            if ($text !== false && $text !== '') {
                return $text;
            }
        } catch (\Throwable) {
            // fall through
        }

        return self::decimal($cents) . ' ' . $currency;
    }

    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => url('/'),
            'description' => __('pages.seo.org_description'),
        ];
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => url('/'),
            'inLanguage' => app()->getLocale(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('/') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param list<array{0:string,1:string}> $items [name, url] pairs, home first */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($i, $n) => [
                '@type' => 'ListItem', 'position' => $n + 1, 'name' => $i[0], 'item' => $i[1],
            ])->all(),
        ];
    }

    /** @param list<array{q:string,a:string}> $items */
    public static function faq(array $items): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => collect($items)->map(fn ($i) => [
                '@type' => 'Question', 'name' => $i['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']],
            ])->all(),
        ];
    }

    public static function vendorName(Product $p): string
    {
        return $p->vendor?->shop_name ?: $p->vendor?->handle ?: config('app.name');
    }

    public static function productDescription(Product $p): string
    {
        return self::limit((string) ($p->description ?: __('pages.seo.product_fallback', ['title' => $p->title, 'vendor' => self::vendorName($p)])), 5000);
    }

    public static function product(Product $p, string $url): array
    {
        $price = self::decimal((int) $p->price_cents);
        $schema = [
            '@type' => 'Product',
            'name' => $p->title,
            'description' => self::productDescription($p),
            'sku' => (string) $p->id,
            'url' => $url,
            'offers' => [
                '@type' => 'AggregateOffer',
                'lowPrice' => $price,
                'highPrice' => $price,
                'priceCurrency' => $p->currency,
                'offerCount' => 1,
                'offers' => [[
                    '@type' => 'Offer',
                    'price' => $price,
                    'priceCurrency' => $p->currency,
                    'url' => $url,
                    'availability' => $p->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'seller' => ['@type' => 'Organization', 'name' => self::vendorName($p)],
                ]],
            ],
        ];
        if ((int) $p->rating_count > 0 && $p->rating_avg !== null) {
            $schema['aggregateRating'] = self::rating($p->rating_avg, $p->rating_count);
        }

        return $schema;
    }

    public static function vendor(User $v, string $url): array
    {
        $schema = ['@type' => 'Organization', 'name' => $v->shop_name ?: $v->handle, 'url' => $url];
        if (filled($v->shop_description)) {
            $schema['description'] = self::limit($v->shop_description, 500);
        }
        if ((int) $v->rating_count > 0 && $v->rating_avg !== null) {
            $schema['aggregateRating'] = self::rating($v->rating_avg, $v->rating_count);
        }

        return $schema;
    }

    private static function rating(mixed $avg, mixed $count): array
    {
        return [
            '@type' => 'AggregateRating',
            'ratingValue' => round((float) $avg, 2),
            'reviewCount' => (int) $count,
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }
}
