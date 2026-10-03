<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

/** Every buyer.* key used in the buyer and storefront views must exist in lang/en/buyer.php. */
class BuyerTranslationKeysTest extends TestCase
{
    private function viewFiles(): array
    {
        return array_merge(
            glob(resource_path('views/buyer/*.blade.php')),
            glob(resource_path('views/store/*.blade.php')),
            [
                resource_path('views/home.blade.php'),
                resource_path('views/layouts/store.blade.php'),
                resource_path('views/partials/pager.blade.php'),
            ],
        );
    }

    public function test_every_used_key_exists_in_english(): void
    {
        $missing = [];
        $used = 0;
        foreach ($this->viewFiles() as $file) {
            preg_match_all("/(?:__|\bt)\(\s*['\"](buyer\.[A-Za-z0-9_.]+)['\"]/", file_get_contents($file), $m);
            foreach (array_unique($m[1]) as $key) {
                $used++;
                if (str_ends_with($key, '_')) { // dynamic suffix, e.g. 'buyer.shared.method_' + value
                    [$group, $stem] = explode('.', substr($key, strlen('buyer.')), 2);
                    $this->assertNotEmpty(
                        array_filter(array_keys(trans("buyer.$group", [], 'en')), fn ($k) => str_starts_with($k, $stem)),
                        "No keys for dynamic prefix $key in " . basename($file)
                    );

                    continue;
                }
                if (trans($key, [], 'en') === $key) {
                    $missing[] = basename($file) . ": $key";
                }
            }
        }
        $this->assertGreaterThan(100, $used, 'Scanner found suspiciously few keys');
        $this->assertSame([], $missing, "Missing translation keys:\n" . implode("\n", $missing));
    }

    public function test_no_unused_strings_in_the_buyer_file(): void
    {
        $all = '';
        foreach ($this->viewFiles() as $file) {
            $all .= file_get_contents($file);
        }
        $unused = [];
        foreach (trans('buyer', [], 'en') as $group => $items) {
            foreach (array_keys($items) as $k) {
                $literal = str_contains($all, "buyer.$group.$k'");
                $dynamic = preg_match("/buyer\\.$group\\.(\\w+_)'\\s*\\+/", $all, $mm) && str_starts_with($k, $mm[1]);
                if (! $literal && ! $dynamic) {
                    $unused[] = "buyer.$group.$k";
                }
            }
        }
        $this->assertSame([], $unused, "Unused keys:\n" . implode("\n", $unused));
    }

    public function test_templates_contain_no_hard_coded_visible_text(): void
    {
        foreach ($this->viewFiles() as $file) {
            $src = file_get_contents($file);
            $src = preg_replace(['/^@section\(.title.*$/m','/<script.*?<\/script>/s', '/<style.*?<\/style>/s', '/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/@php.*?@endphp/s', '/<\/?[a-zA-Z][^\s>]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/s'], ' ', $src);
            $src = preg_replace('/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', ' ', $src);
            $text = trim(preg_replace('/\s+/u', ' ', $src));
            $this->assertSame('', $text, basename($file) . " contains hard-coded text: $text");
        }
    }
}
