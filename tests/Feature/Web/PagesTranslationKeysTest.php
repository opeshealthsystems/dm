<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

/** Every pages.* key used by the public-site views, controllers and SEO helpers exists, and none is left unused. */
class PagesTranslationKeysTest extends TestCase
{
    private function sources(): array
    {
        return array_merge(
            glob(resource_path('views/pages/*.blade.php')),
            glob(resource_path('views/errors/*.blade.php')),
            glob(resource_path('views/partials/{seo,breadcrumbs,jsonld}.blade.php'), GLOB_BRACE),
            [
                resource_path('views/layouts/store.blade.php'),
                resource_path('views/home.blade.php'),
                resource_path('views/admin/support.blade.php'),
                resource_path('views/store/product.blade.php'),
                resource_path('views/store/vendor.blade.php'),
                app_path('Modules/ContentSeo/Support/Seo.php'),
            ],
        );
    }

    /** @return list<string> literal keys such as pages.about.title or prefixes such as pages.faq.items */
    private function usedKeys(): array
    {
        $keys = [];
        foreach ($this->sources() as $file) {
            preg_match_all("/['\"](pages\.[A-Za-z0-9_.]+)['\"]/", file_get_contents($file), $m);
            $keys = array_merge($keys, $m[1]);
        }
        // Error views build their keys from the status code: pages.errors.$code.title
        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            $keys[] = "pages.errors.$code.title";
            $keys[] = "pages.errors.$code.message";
        }

        return array_values(array_unique($keys));
    }

    public function test_every_used_key_exists_in_english(): void
    {
        $missing = [];
        foreach ($this->usedKeys() as $key) {
            if (trans($key, [], 'en') === $key) {
                $missing[] = $key;
            }
        }
        $this->assertGreaterThan(60, count($this->usedKeys()), 'Scanner found suspiciously few keys');
        $this->assertSame([], $missing, "Missing keys:\n" . implode("\n", $missing));
    }

    public function test_no_unused_strings_in_the_pages_file(): void
    {
        $used = $this->usedKeys();
        $unused = [];
        foreach (array_keys(\Illuminate\Support\Arr::dot(trans('pages', [], 'en'))) as $key) {
            $full = "pages.$key";
            $hit = false;
            foreach ($used as $u) {
                // exact key, or a used prefix that is iterated over (sections, items, ...), or a key reached by sub-index
                if ($full === $u || str_starts_with($full, $u . '.')) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                $unused[] = $full;
            }
        }
        $this->assertSame([], $unused, "Unused keys:\n" . implode("\n", $unused));
    }

    public function test_list_entries_have_the_same_shape_in_every_language(): void
    {
        $lists = ['about.sections', 'how.buyer_steps', 'how.seller_steps', 'how.escrow_points', 'faq.items', 'terms.sections',
            'privacy.sections', 'sellers.start_steps', 'sellers.api_points'];
        foreach (array_keys(config('locales.supported')) as $locale) {
            foreach ($lists as $path) {
                $en = trans("pages.$path", [], 'en');
                $tr = trans("pages.$path", [], $locale);
                $this->assertCount(count($en), $tr, "$locale pages.$path has a different number of entries");
            }
        }
        $this->assertGreaterThanOrEqual(10, count(trans('pages.faq.items', [], 'en')));
        $this->assertLessThanOrEqual(12, count(trans('pages.faq.items', [], 'en')));
    }

    public function test_pages_are_actually_translated(): void
    {
        $source = \Illuminate\Support\Arr::dot(trans('pages', [], 'en'));
        foreach (array_keys(config('locales.supported')) as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $target = \Illuminate\Support\Arr::dot(trans('pages', [], $locale));
            $same = count(array_filter($source, fn ($v, $k) => ($target[$k] ?? null) === $v, ARRAY_FILTER_USE_BOTH));
            $this->assertLessThan(count($source) * 0.1, $same, "$locale/pages looks untranslated");
        }
    }
}
