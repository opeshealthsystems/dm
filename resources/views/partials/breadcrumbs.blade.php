{{-- Visible breadcrumb trail plus BreadcrumbList JSON-LD. $crumbs = [[name, url], ...] after "Home". --}}
@php($trail = array_merge([[__('pages.breadcrumb.home'), url('/')]], $crumbs))
<nav aria-label="{{ __('pages.breadcrumb.label') }}" class="mb-2 text-sm text-ink-2">
    <ol class="crumbs flex flex-wrap items-center gap-x-2">
        @foreach ($trail as $crumb)
            <li class="flex items-center">
                @if ($loop->last)
                    <span aria-current="page">{{ $crumb[0] }}</span>
                @else
                    <a class="link-tap" href="{{ $crumb[1] }}">{{ $crumb[0] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@include('partials.jsonld', ['schema' => \App\Modules\ContentSeo\Support\Seo::breadcrumbs($trail)])
