@extends('layouts.store')

@section('title', __('pages.terms.title') . ' | ' . config('app.name'))
@section('description', __('pages.terms.meta'))

@section('content')
<article class="page-copy">
    @include('partials.breadcrumbs', ['crumbs' => [[__('pages.terms.title'), route('pages.terms')]]])
    <h1>{{ __('pages.terms.title') }}</h1>
    <p class="updated">{{ __('pages.updated', ['date' => config('seo.legal_updated')]) }}</p>
    <p class="lead">{{ __('pages.terms.lead', ['name' => config('app.name')]) }}</p>
    @foreach (trans('pages.terms.sections') as $section)
        <h2>{{ $section['title'] }}</h2>
        <p>{{ $section['body'] }}</p>
    @endforeach
</article>
@endsection
