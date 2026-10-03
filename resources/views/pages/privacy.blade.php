@extends('layouts.store')

@section('title', __('pages.privacy.title') . ' | ' . config('app.name'))
@section('description', __('pages.privacy.meta'))

@section('content')
<article class="page-copy">
    @include('partials.breadcrumbs', ['crumbs' => [[__('pages.privacy.title'), route('pages.privacy')]]])
    <h1>{{ __('pages.privacy.title') }}</h1>
    <p class="updated">{{ __('pages.updated', ['date' => config('seo.legal_updated')]) }}</p>
    <p class="lead">{{ __('pages.privacy.lead', ['name' => config('app.name')]) }}</p>
    @foreach (trans('pages.privacy.sections') as $section)
        <h2>{{ strtr($section['title'], [':name' => config('app.name')]) }}</h2>
        <p>{{ strtr($section['body'], [':name' => config('app.name')]) }}</p>
    @endforeach
</article>
@endsection
