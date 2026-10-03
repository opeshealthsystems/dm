<?php

namespace Tests\Feature\Web;

use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Every supported language must have exactly the same keys and the same :placeholders as
 * English, in every translation file. English is the source of truth.
 */
class TranslationParityTest extends TestCase
{
    private const FILES = ['common', 'buyer', 'seller', 'admin', 'community', 'security', 'pages'];

    private function load(string $locale, string $file): array
    {
        $path = lang_path("$locale/$file.php");
        $this->assertFileExists($path, "Missing lang/$locale/$file.php");

        return Arr::dot(require $path);
    }

    public function test_every_language_has_every_key_with_matching_placeholders(): void
    {
        $problems = [];

        foreach (self::FILES as $file) {
            $source = $this->load('en', $file);

            foreach (array_keys(config('locales.supported')) as $locale) {
                if ($locale === 'en') {
                    continue;
                }
                $target = $this->load($locale, $file);

                foreach (array_diff_key($source, $target) as $key => $_) {
                    $problems[] = "$locale/$file: missing $key";
                }
                foreach (array_diff_key($target, $source) as $key => $_) {
                    $problems[] = "$locale/$file: extra $key";
                }
                foreach ($source as $key => $english) {
                    if (! isset($target[$key])) {
                        continue;
                    }
                    preg_match_all('/:[a-z_]+/', (string) $english, $a);
                    preg_match_all('/:[a-z_]+/', (string) $target[$key], $b);
                    sort($a[0]);
                    sort($b[0]);
                    if ($a[0] !== $b[0]) {
                        $problems[] = "$locale/$file: placeholders differ in $key";
                    }
                    if (trim((string) $target[$key]) === '') {
                        $problems[] = "$locale/$file: empty $key";
                    }
                }
            }
        }

        $this->assertSame([], array_slice($problems, 0, 40), count($problems) . ' translation problems');
    }

    public function test_non_english_text_is_actually_translated(): void
    {
        // Guard against copying English into every file: most strings must differ from English.
        $source = $this->load('en', 'common');

        foreach (array_keys(config('locales.supported')) as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $target = $this->load($locale, 'common');
            $same = count(array_filter($source, fn ($v, $k) => ($target[$k] ?? null) === $v, ARRAY_FILTER_USE_BOTH));
            $this->assertLessThan(count($source) * 0.35, $same, "$locale/common looks untranslated");
        }
    }
}
