@extends('layouts.dashboard')

@section('title', __('common.nav.' . $page) . ' | ' . config('app.name'))

@section('content')
    <h1 class="mb-4 text-2xl font-medium">{{ __('common.nav.' . $page) }}</h1>
    <div class="card text-ink-2">{{ __('common.coming_soon') }}</div>
@endsection
