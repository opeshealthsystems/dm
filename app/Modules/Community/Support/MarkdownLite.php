<?php

namespace App\Modules\Community\Support;

/**
 * Markdown-lite renderer for forum posts. SAFE BY CONSTRUCTION:
 *
 *   1. Control characters are stripped (the \x1A placeholder byte can never come from input).
 *   2. EVERYTHING is HTML-escaped first, so no raw HTML, attribute or entity survives.
 *   3. Only then are a few patterns turned into a fixed set of tags that this class writes itself:
 *      **bold**, *italic* / _italic_, `code`, [text](http(s)://url) and line breaks.
 *   4. Link URLs must start with http:// or https:// (checked after escaping, so quotes in a URL
 *      are already &quot;). javascript:, data:, vbscript: etc. therefore never match and stay as text.
 *   5. Code spans and links are stashed behind placeholders while inline formatting runs, so
 *      formatting characters inside a URL can never inject markup into an attribute.
 *
 * The output contains only: <strong> <em> <code> <br> and <a href rel target>.
 */
final class MarkdownLite
{
    private const PH = "\x1A";
    private const REL = 'nofollow ugc noopener noreferrer';

    public static function render(?string $source): string
    {
        $text = (string) $source;
        $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', str_replace(["\r\n", "\r"], "\n", $text)) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text)) ?? '';
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        $stash = [];
        $hold = function (string $html) use (&$stash): string {
            $stash[] = $html;

            return self::PH . (count($stash) - 1) . self::PH;
        };

        // Code spans first: nothing inside them is formatted or linked.
        $text = preg_replace_callback('/`([^`\n]{1,500})`/u', fn ($m) => $hold('<code>' . $m[1] . '</code>'), $text) ?? '';

        // Links. The URL group excludes whitespace, parentheses and angle brackets; quotes are already entities.
        $text = preg_replace_callback(
            '/\[([^\]\n]{1,200})\]\((https?:\/\/[^\s()<>\x1A]{1,2000})\)/iu',
            fn ($m) => $hold('<a href="' . $m[2] . '" rel="' . self::REL . '" target="_blank">' . self::inline($m[1]) . '</a>'),
            $text
        ) ?? '';

        $text = self::inline($text);

        // Restore stashed fragments (a link's text may itself hold a code placeholder).
        for ($i = 0; $i < 4 && str_contains($text, self::PH); $i++) {
            $text = preg_replace_callback('/\x1A(\d+)\x1A/', fn ($m) => $stash[(int) $m[1]] ?? '', $text) ?? '';
        }

        return str_replace("\n", "<br>\n", $text);
    }

    /** Bold and italics on already-escaped text. */
    private static function inline(string $text): string
    {
        $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<![\w*])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![\w*])/u', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![\w_])_(?=[^\s_])(.+?)(?<=[^\s_])_(?![\w_])/u', '<em>$1</em>', $text) ?? $text;

        return $text;
    }

    /** Number of links in raw (unrendered) text: scheme URLs and bare www. hosts. */
    public static function countLinks(?string $source): int
    {
        return (int) preg_match_all('~https?://|(?<![\w.])www\.~i', (string) $source);
    }
}
