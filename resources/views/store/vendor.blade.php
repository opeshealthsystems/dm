@extends('layouts.store')

@section('title', ($vendor->shop_name ?: $vendor->handle ?: __('buyer.vendor.page_title')) . ' | ' . config('app.name'))
@section('description', \App\Modules\ContentSeo\Support\Seo::limit(filled($vendor->shop_description) ? $vendor->shop_description : __('pages.seo.vendor_fallback', ['shop' => $vendor->shop_name ?: $vendor->handle])))
@section('canonical_url', route('store.vendor', $vendor->id))
@if (! $indexable)
    @section('robots', 'noindex,nofollow')
@endif

@section('content')
@include('partials.jsonld', ['schema' => \App\Modules\ContentSeo\Support\Seo::vendor($vendor, route('store.vendor', $vendor->id))])
<div x-data="vendorPage(@js($vendorId), @js(auth()->check()))">
    <a href="{{ route('home') }}" class="back-link">{{ __('buyer.product.back') }}</a>
    @include('partials.breadcrumbs', ['crumbs' => [[$vendor->shop_name ?: $vendor->handle, route('store.vendor', $vendor->id)]]])

    {{-- Server-rendered copy for search engines and first paint; removed once Alpine has loaded the live data. --}}
    <section x-effect="if (vendor) $el.remove()" class="card mb-6">
        <h1 class="flex flex-wrap items-center gap-2 text-2xl font-medium">
            <span>{{ $vendor->shop_name ?: $vendor->handle }}</span>
            @if ($vendor->is_verified_vendor)
                <span class="badge badge-ok">{{ __('buyer.vendor.verified') }}</span>
            @endif
        </h1>
        <p class="text-sm text-ink-2">{{ $vendor->rating_count > 0 ? __('buyer.shared.rating', ['avg' => number_format((float) $vendor->rating_avg, 1), 'count' => $vendor->rating_count]) : __('buyer.shared.no_ratings') }}</p>
        @if (filled($vendor->shop_description))
            <p class="mt-2 whitespace-pre-line">{{ $vendor->shop_description }}</p>
        @endif
    </section>

    <p x-show="loading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
    <div x-show="error" x-cloak class="card border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="init()">{{ __('buyer.shared.retry') }}</button>
    </div>

    <template x-if="vendor">
        <div>
            <section class="card mb-6 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="flex flex-wrap items-center gap-2 text-2xl font-medium">
                        <span x-text="vendor.shop_name || vendor.handle"></span>
                        <span class="badge badge-ok" x-show="vendor.is_verified_vendor">{{ __('buyer.vendor.verified') }}</span>
                    </h1>
                    <p class="text-sm text-ink-2"
                       x-text="(vendor.rating_count > 0 ? t('buyer.shared.rating', {avg: Number(vendor.rating_avg).toFixed(1), count: vendor.rating_count}) : t('buyer.shared.no_ratings')) + ' · ' + t('buyer.vendor.followers', {count: vendor.followers_count ?? 0})"></p>
                    <p class="mt-2 whitespace-pre-line" x-show="vendor.shop_description" x-text="vendor.shop_description"></p>
                </div>
                <div class="flex flex-col items-start gap-1">
                    <button type="button" class="btn" :class="following ? '' : 'btn-primary'" :disabled="busy" @click="toggleFollow()"
                            x-text="following ? t('buyer.vendor.unfollow') : t('buyer.vendor.follow')"></button>
                    <p class="text-sm text-bad" x-show="actionError" x-cloak role="alert" x-text="actionError"></p>
                </div>
            </section>

            <h2 class="mb-3 text-xl font-medium">{{ __('buyer.vendor.products') }}</h2>
            <p x-show="productsLoading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
            <p x-show="productsError" x-cloak class="text-bad" role="alert" x-text="productsError"></p>
            <p x-show="!productsLoading && !productsError && products.length === 0" x-cloak class="card text-ink-2">{{ __('buyer.vendor.no_products') }}</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="p in products" :key="p.id">
                    <article class="card card-hover relative flex flex-col gap-1">
                        <a :href="'/p/' + encodeURIComponent(p.slug)" class="font-medium text-ink no-underline after:absolute after:inset-0 after:content-[''] hover:underline" x-text="p.title"></a>
                        <span class="font-medium" x-text="money(p.price_cents, p.currency)"></span>
                    </article>
                </template>
            </div>
            @include('partials.pager', ['pg' => 'pPage', 'last' => 'pLast', 'ld' => 'productsLoading', 'goFn' => 'goProducts'])

            <h2 class="mb-3 mt-8 text-xl font-medium">{{ __('buyer.product.reviews') }}</h2>
            <p x-show="reviewsLoading" class="text-ink-2" role="status">{{ __('common.loading') }}</p>
            <p x-show="reviewsError" x-cloak class="text-bad" role="alert" x-text="reviewsError"></p>
            <p x-show="!reviewsLoading && !reviewsError && reviews.length === 0" x-cloak class="card text-ink-2">{{ __('buyer.product.no_reviews') }}</p>
            <ul class="flex flex-col gap-3">
                <template x-for="r in reviews" :key="r.id">
                    <li class="card">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium" x-text="t('buyer.shared.stars', {count: r.rating})"></span>
                            <span class="text-sm text-ink-3" x-text="(r.buyer?.handle ?? t('buyer.product.anonymous')) + ' · ' + fmtDate(r.created_at)"></span>
                        </div>
                        <p class="mt-1 whitespace-pre-line" x-show="r.body" x-text="r.body"></p>
                        <div class="mt-2 border-s-2 border-line ps-3 text-sm text-ink-2" x-show="r.vendor_reply">
                            <span class="font-medium">{{ __('buyer.product.vendor_reply') }}</span>
                            <p class="whitespace-pre-line" x-text="r.vendor_reply"></p>
                        </div>
                    </li>
                </template>
            </ul>
            @include('partials.pager', ['pg' => 'rPage', 'last' => 'rLast', 'ld' => 'reviewsLoading', 'goFn' => 'goReviews'])
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('vendorPage', (id, signedIn) => ({
        id, signedIn, vendor: null, following: false, busy: false, actionError: '',
        loading: true, error: '',
        products: [], productsLoading: false, productsError: '', pPage: 1, pLast: 1,
        reviews: [], reviewsLoading: false, reviewsError: '', rPage: 1, rLast: 1,
        async init() {
            this.loading = true; this.error = '';
            try {
                this.vendor = (await api('vendors/' + this.id)).data;
            } catch (e) {
                this.error = e.status === 404 ? t('buyer.vendor.not_found') : errText(e);
                this.loading = false;
                return;
            }
            this.loading = false;
            document.title = (this.vendor.shop_name || this.vendor.handle) + ' | ' + document.title.split('|').pop().trim();
            this.goProducts(1);
            this.goReviews(1);
            if (this.signedIn) this.loadFollowing();
        },
        async loadFollowing() {
            try {
                const r = await api('following', {query: {per_page: 100}});
                this.following = r.data.some(v => v.id === this.id);
            } catch (e) { /* follow state unknown: keep the follow button */ }
        },
        async goProducts(n) {
            this.pPage = n; this.productsLoading = true; this.productsError = '';
            try {
                const r = await api('products', {query: {vendor_id: this.id, page: n, per_page: 12}});
                this.products = r.data; this.pLast = r.meta?.last_page ?? 1;
            } catch (e) { this.productsError = errText(e); }
            finally { this.productsLoading = false; }
        },
        async goReviews(n) {
            this.rPage = n; this.reviewsLoading = true; this.reviewsError = '';
            try {
                const r = await api('vendors/' + this.id + '/reviews', {query: {page: n, per_page: 10}});
                this.reviews = r.data; this.rLast = r.meta?.last_page ?? 1;
            } catch (e) { this.reviewsError = errText(e); }
            finally { this.reviewsLoading = false; }
        },
        async toggleFollow() {
            if (this.busy) return;
            this.busy = true; this.actionError = '';
            try {
                await api('vendors/' + this.id + '/follow', {method: this.following ? 'DELETE' : 'POST'});
                this.following = !this.following;
            } catch (e) { this.actionError = errText(e); }
            finally { this.busy = false; }
        },
    }));
});
</script>
@endsection
