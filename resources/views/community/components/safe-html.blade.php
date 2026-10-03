{{--
    The ONLY place in the community UI that prints unescaped HTML, and it can only ever print the
    output of MarkdownLite::render(). This component takes the RAW markdown text, not HTML, so a
    caller cannot hand it markup by mistake: MarkdownLite escapes every character first and then emits
    just <strong>, <em>, <code>, <br> and <a href rel target> (http/https links only).
    See tests/Feature/Community/MarkdownLiteTest.php, which proves script, onerror, javascript: and nested payloads are neutralised.
--}}
@props(['markdown' => ''])
<div {{ $attributes->class(['min-w-0 break-words [&_a]:underline [&_code]:rounded [&_code]:bg-surface-2 [&_code]:px-1 [&_code]:text-sm']) }}>{!! \App\Modules\Community\Support\MarkdownLite::render($markdown) !!}</div>
