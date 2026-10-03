@extends('layouts.store')

@php
    $replace = [':percent' => \App\Modules\ContentSeo\Support\Seo::commissionPercent(), ':name' => config('app.name')];
    $items = collect(trans('pages.faq.items'))->map(fn ($i) => ['q' => strtr($i['q'], $replace), 'a' => strtr($i['a'], $replace)])->all();
@endphp

@section('title', __('pages.faq.title') . ' | ' . config('app.name'))
@section('description', __('pages.faq.meta'))

@section('content')
<article class="page-copy">
    @include('partials.breadcrumbs', ['crumbs' => [[__('pages.faq.title'), route('pages.faq')]]])
    @include('partials.jsonld', ['schema' => \App\Modules\ContentSeo\Support\Seo::faq($items)])
    <h1>{{ __('pages.faq.title') }}</h1>
    <p class="lead">{{ __('pages.faq.lead') }}</p>
    <div class="faq">
        @foreach ($items as $item)
            <details>
                <summary>{{ $item['q'] }}</summary>
                <p>{{ $item['a'] }}</p>
            </details>
        @endforeach
    </div>
    <h2>{{ __('pages.faq.more_title') }}</h2>
    <p>{{ __('pages.faq.more_body') }}</p>
    <a class="btn btn-primary" href="{{ route('pages.contact') }}">{{ __('pages.faq.more_cta') }}</a>
</article>
@endsection
