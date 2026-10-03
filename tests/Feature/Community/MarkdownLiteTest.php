<?php

namespace Tests\Feature\Community;

use App\Modules\Community\Support\MarkdownLite;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** The sanitiser is the only thing standing between user text and {!! !!}: prove it. */
class MarkdownLiteTest extends TestCase
{
    private const ALLOWED_TAGS = ['strong', 'em', 'code', 'br', 'a', 'div'];

    /** Hostile inputs, including nested and obfuscated ones. */
    public static function payloads(): array
    {
        $list = [
            '<script>alert(1)</script>',
            '<SCRIPT SRC=//evil.example/x.js></SCRIPT>',
            '<img src=x onerror=alert(1)>',
            '<img src="x" onerror="alert(document.cookie)">',
            '<svg/onload=alert(1)>',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<a href="javascript:alert(1)">x</a>',
            '[x](javascript:alert(1))',
            '[x](JaVaScRiPt:alert(1))',
            '[x]( javascript:alert(1))',
            '[x](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)',
            '[x](vbscript:msgbox(1))',
            '[x](//evil.example/path)',
            '[x](https://a.example" onmouseover="alert(1))',
            '[x](https://a.example/"onmouseover=alert(1)//)',
            "[x](https://a.example/'onfocus='alert(1))",
            '[<script>alert(1)</script>](https://ok.example)',
            '[<img src=x onerror=alert(1)>](https://ok.example)',
            '**<img src=x onerror=alert(1)>**',
            '*<script>alert(1)</script>*',
            '`<script>alert(1)</script>`',
            '[a](https://x.example/?q=[b](javascript:alert(1)))',
            '[**[x](javascript:alert(1))**](https://ok.example)',
            "java\0script:alert(1)",
            "[x](java\x1Ascript:alert(1))",
            "\x1A0\x1A<script>alert(1)</script>",
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '&#60;script&#62;alert(1)&#60;/script&#62;',
            '<<script>script>alert(1)<</script>/script>',
            '<div style="background:url(javascript:alert(1))">x</div>',
            '<a href="https://ok.example" onclick="alert(1)">x</a>',
            '<math><mtext></p><img src=x onerror=alert(1)>',
            '"><script>alert(1)</script>',
            "' onmouseover='alert(1)",
            '<a href=https://ok.example/**x**>t</a>',
            '[x](https://a.example/`code`)',
        ];

        return array_map(fn ($p) => [$p], $list);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('payloads')]
    public function test_output_only_contains_allowed_elements_and_attributes(string $payload): void
    {
        $html = MarkdownLite::render($payload);

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        foreach ($doc->getElementsByTagName('*') as $el) {
            /** @var DOMElement $el */
            $this->assertContains($el->tagName, self::ALLOWED_TAGS, "Unexpected <{$el->tagName}> for payload: $payload\nOutput: $html");
            foreach ($el->attributes as $attr) {
                if ($el->tagName === 'div') {
                    $this->assertSame('id', $attr->name); // our own wrapper

                    continue;
                }
                $this->assertContains($attr->name, ['href', 'rel', 'target'], "Unexpected attribute {$attr->name}: $html");
            }
            if ($el->tagName === 'a') {
                $this->assertMatchesRegularExpression('#^https?://#i', $el->getAttribute('href'), "Bad href: $html");
                $this->assertSame('nofollow ugc noopener noreferrer', $el->getAttribute('rel'));
            }
        }

        // Raw markup must never survive as markup.
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('<img', strtolower($html));
        $this->assertStringNotContainsString('<svg', strtolower($html));
        $this->assertStringNotContainsString('<iframe', strtolower($html));
        $this->assertDoesNotMatchRegularExpression('/<[^>]*\son\w+\s*=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/href="\s*(javascript|data|vbscript):/i', $html);
        $this->assertStringNotContainsString("\x1A", $html);
    }

    public function test_script_tag_is_escaped_to_text(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', MarkdownLite::render('<script>alert(1)</script>'));
    }

    public function test_event_handler_attributes_become_inert_text(): void
    {
        $html = MarkdownLite::render('<img src=x onerror=alert(1)>');
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_javascript_urls_are_never_linked(): void
    {
        $this->assertStringNotContainsString('<a', MarkdownLite::render('[x](javascript:alert(1))'));
        $this->assertStringNotContainsString('<a', MarkdownLite::render('[x](JAVASCRIPT:alert(1))'));
        $this->assertStringNotContainsString('<a', MarkdownLite::render('[x](data:text/html,hi)'));
    }

    public function test_quotes_in_urls_cannot_break_out_of_the_href_attribute(): void
    {
        $html = MarkdownLite::render('[x](https://a.example/" onmouseover="alert(1))');
        $this->assertStringNotContainsString('" onmouseover', $html);
        $this->assertDoesNotMatchRegularExpression('/<[^>]*\sonmouseover/i', $html);

        $html = MarkdownLite::render('[x](https://a.example/"onmouseover=alert(1)//)');
        $this->assertDoesNotMatchRegularExpression('/<[^>]*\sonmouseover/i', $html);
        $this->assertStringContainsString('&quot;onmouseover', $html); // the quote stays an entity inside href
    }

    public function test_nested_payload_in_link_text_and_bold_is_escaped(): void
    {
        $html = MarkdownLite::render('**[<script>alert(1)</script>](https://ok.example)**');
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_formatting_characters_inside_a_url_do_not_inject_markup(): void
    {
        $html = MarkdownLite::render('[a](https://x.example/**b**)');
        $this->assertSame('<a href="https://x.example/**b**" rel="nofollow ugc noopener noreferrer" target="_blank">a</a>', $html);
    }

    public function test_supported_formatting(): void
    {
        $this->assertSame('<strong>b</strong> <em>i</em> <em>u</em> <code>c</code>', MarkdownLite::render('**b** *i* _u_ `c`'));
        $this->assertSame("a<br>\nb", MarkdownLite::render("a\nb"));
        $this->assertSame("a<br>\n<br>\nb", MarkdownLite::render("a\n\n\n\nb"));
        $this->assertSame('<code>**not bold**</code>', MarkdownLite::render('`**not bold**`'));
        $this->assertSame('snake_case_word', MarkdownLite::render('snake_case_word'));
        $this->assertSame('', MarkdownLite::render(null));
    }

    public function test_links_get_nofollow_ugc_noopener(): void
    {
        $html = MarkdownLite::render('[shop](https://example.com/a?b=1&c=2)');
        $this->assertSame('<a href="https://example.com/a?b=1&amp;c=2" rel="nofollow ugc noopener noreferrer" target="_blank">shop</a>', $html);
        $this->assertStringContainsString('nofollow ugc noopener', $html);
    }

    public function test_bare_html_entities_are_not_decoded(): void
    {
        $this->assertSame('&amp;lt;script&amp;gt;', MarkdownLite::render('&lt;script&gt;'));
    }

    public function test_link_counter(): void
    {
        $this->assertSame(0, MarkdownLite::countLinks('no links here'));
        $this->assertSame(3, MarkdownLite::countLinks('http://a.example [x](https://b.example) www.c.example'));
    }

    public function test_safe_html_component_only_prints_sanitised_output(): void
    {
        $html = Blade::render('<x-community::safe-html :markdown="$m" />', ['m' => '<script>alert(1)</script> **ok** [x](javascript:alert(1))']);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong>ok</strong>', $html);
        $this->assertStringNotContainsString('<a', $html);
    }
}
