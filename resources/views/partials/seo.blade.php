{{-- Search/social meta for every page. Pages fill the sections: title, description, robots, canonical_url, og_type. --}}
@php
    // Inline @section values arrive HTML-escaped; decode them so {{ }} below escapes exactly once.
    $seoYield = fn (string $name) => html_entity_decode(trim($__env->yieldContent($name)), ENT_QUOTES);
    $seoTitle = $seoYield('title') ?: config('app.name');
    $seoDescription = $seoYield('description') ?: __('pages.seo.default_description');
    $seoRobots = trim($__env->yieldContent('robots')) ?: 'index,follow';
    $seoIndexable = ! str_contains($seoRobots, 'noindex');
    $seoBase = $seoYield('canonical_url') ?: url()->current();
    $seoLocale = app()->getLocale();
    $seoCanonical = \App\Modules\ContentSeo\Support\Seo::localeUrl($seoBase, $seoLocale);
    $seoType = trim($__env->yieldContent('og_type')) ?: 'website';
@endphp
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoRobots }}">
@if ($seoIndexable)
<link rel="canonical" href="{{ $seoCanonical }}">
@foreach (\App\Modules\ContentSeo\Support\Seo::alternates($seoBase) as $code => $href)
<link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $seoBase }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ \App\Modules\ContentSeo\Support\Seo::ogLocale($seoLocale) }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
@endif
@stack('jsonld')
