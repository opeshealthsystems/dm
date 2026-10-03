{{-- Adds one JSON-LD block to the page head. Usage: @include('partials.jsonld', ['schema' => [...]]) --}}
@push('jsonld')
{!! \App\Modules\ContentSeo\Support\Seo::jsonLd($schema) !!}
@endpush
