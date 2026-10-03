@extends('layouts.store')

@section('title', __('pages.how.title') . ' | ' . config('app.name'))
@section('description', __('pages.how.meta'))

@section('content')
<article class="page-copy">
    @include('partials.breadcrumbs', ['crumbs' => [[__('pages.how.title'), route('pages.how')]]])
    <h1>{{ __('pages.how.title') }}</h1>
    <p class="lead">{{ __('pages.how.lead') }}</p>

    <h2>{{ __('pages.how.buyers_title') }}</h2>
    <ol class="steps">
        @foreach (trans('pages.how.buyer_steps') as $step)
            <li><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p></li>
        @endforeach
    </ol>

    <h2>{{ __('pages.how.sellers_title') }}</h2>
    <ol class="steps">
        @foreach (trans('pages.how.seller_steps') as $step)
            <li><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p></li>
        @endforeach
    </ol>

    <h2>{{ __('pages.how.escrow_title') }}</h2>
    <p>{{ __('pages.how.escrow_lead') }}</p>
    <ol class="steps">
        @foreach (trans('pages.how.escrow_points') as $point)
            <li><h3>{{ $point['title'] }}</h3><p>{{ $point['body'] }}</p></li>
        @endforeach
    </ol>
    <p class="card">{{ __('pages.how.escrow_note') }}</p>

    <div class="mt-6 flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('home') }}">{{ __('pages.how.cta_browse') }}</a>
        <a class="btn" href="{{ route('register') }}">{{ __('pages.how.cta_register') }}</a>
    </div>
</article>
@endsection
