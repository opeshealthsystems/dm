@extends('layouts.store')

@section('title', __('pages.sellers.title') . ' | ' . config('app.name'))
@section('description', __('pages.sellers.meta'))

@section('content')
<article class="page-copy">
    @include('partials.breadcrumbs', ['crumbs' => [[__('pages.sellers.title'), route('pages.sellers')]]])
    <h1>{{ __('pages.sellers.title') }}</h1>
    <p class="lead">{{ __('pages.sellers.lead') }}</p>

    <h2>{{ __('pages.sellers.start_title') }}</h2>
    <ol class="steps">
        @foreach (trans('pages.sellers.start_steps') as $step)
            <li><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p></li>
        @endforeach
    </ol>

    <h2>{{ __('pages.sellers.fees_title') }}</h2>
    <p>{{ __('pages.sellers.fees_body', ['percent' => \App\Modules\ContentSeo\Support\Seo::commissionPercent()]) }}</p>

    <h2>{{ __('pages.sellers.payouts_title') }}</h2>
    <p>{{ __('pages.sellers.payouts_body') }}</p>

    <h2>{{ __('pages.sellers.rules_title') }}</h2>
    <p>{{ __('pages.sellers.rules_body') }}</p>

    <h2>{{ __('pages.sellers.api_title') }}</h2>
    <p>{{ __('pages.sellers.api_body') }}</p>
    <ol class="steps">
        @foreach (trans('pages.sellers.api_points') as $point)
            <li><h3>{{ $point['title'] }}</h3><p>{{ $point['body'] }}</p></li>
        @endforeach
    </ol>

    <div class="mt-6 flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('register') }}">{{ __('pages.sellers.cta_register') }}</a>
        <a class="btn" href="{{ url('/docs/api') }}">{{ __('pages.sellers.cta_docs') }}</a>
    </div>
</article>
@endsection
