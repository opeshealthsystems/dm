@extends('layouts.store')

@section('title', __('pages.about.title') . ' | ' . config('app.name'))
@section('description', __('pages.about.meta'))

@section('content')
<article class="page-copy">
    @include('partials.breadcrumbs', ['crumbs' => [[__('pages.about.title'), route('pages.about')]]])
    <h1>{{ __('pages.about.title') }}</h1>
    <p class="lead">{{ __('pages.about.lead') }}</p>
    @foreach (trans('pages.about.sections') as $section)
        <h2>{{ $section['title'] }}</h2>
        <p>{{ $section['body'] }}</p>
    @endforeach
    <h2>{{ __('pages.about.cta_title') }}</h2>
    <div class="flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('home') }}">{{ __('pages.about.cta_browse') }}</a>
        <a class="btn" href="{{ route('pages.sellers') }}">{{ __('pages.about.cta_sell') }}</a>
    </div>
</article>
@endsection
