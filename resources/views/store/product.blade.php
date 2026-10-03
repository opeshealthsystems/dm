@extends('layouts.store')

@section('title', ($product->title ?: __('buyer.product.page_title')) . ' | ' . config('app.name'))
@section('description', \App\Modules\ContentSeo\Support\Seo::limit(\App\Modules\ContentSeo\Support\Seo::productDescription($product)))
@section('canonical_url', route('store.product', $product->slug))
@section('og_type', 'product')
@if (! $indexable)
    @section('robots', 'noindex,nofollow')
@endif

@section('content')
@include('partials.jsonld', ['schema' => \App\Modules\ContentSeo\Support\Seo::product($product, route('store.product', $product->slug))])
<div x-data="productPage(@js($slug), @js(auth()->user()?->role))">
    <a href="{{ route('home') }}" class="back-link">{{ __('buyer.product.back') }}</a>
    @include('partials.breadcrumbs', ['crumbs' => [[$product->title, route('store.product', $product->slug)]]])

    {{-- Server-rendered copy for search engines and first paint; removed once Alpine has loaded the live data. --}}
    <section x-effect="if (product) $el.remove()" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div>
            <h1 class="mb-2 text-2xl font-medium sm:text-3xl">{{ $product->title }}</h1>
            <p class="mb-3 text-ink-2">{{ $product->rating_count > 0 ? __('buyer.shared.rating', ['avg' => number_format((float) $product->rating_avg, 1), 'count' => $product->rating_count]) : __('buyer.shared.no_ratings') }}</p>
            <div class="whitespace-pre-line text-ink">{{ $product->description ?: __('buyer.product.no_description') }}</div>
        </div>
        <div class="card flex flex-col gap-3 self-start">
            <div class="text-3xl font-medium">{{ \App\Modules\ContentSeo\Support\Seo::price((int) $product->price_cents, $product->currency) }}</div>
            <div>
                <span class="badge {{ $product->stock > 0 ? 'badge-ok' : 'badge-bad' }}">{{ $product->stock > 0 ? __('buyer.product.stock', ['count' => $product->stock]) : __('buyer.store.out_of_stock') }}</span>
            </div>
            <div class="border-t border-line pt-3">
                <div class="text-sm text-ink-3">{{ __('buyer.product.sold_by') }}</div>
                <a href="{{ route('store.vendor', $product->vendor_id) }}" class="font-medium">{{ $product->vendor?->shop_name ?: $product->vendor?->handle }}</a>
            </div>
        </div>
    </section>
    <div x-show="error" x-cloak class="card border-bad text-bad" role="alert">
        <span x-text="error"></span>
        <button type="button" class="btn btn-sm ms-2" @click="init()">{{ __('buyer.shared.retry') }}</button>
    </div>

    <template x-if="product">
        <div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section>
                    <h1 class="mb-2 text-2xl font-medium sm:text-3xl" x-text="product.title"></h1>
                    <p class="mb-3 text-ink-2" x-text="ratingText(product.rating_avg, product.rating_count)"></p>
                    <div class="whitespace-pre-line text-ink" x-text="product.description || t('buyer.product.no_description')"></div>
                </section>

                <aside class="card flex flex-col gap-3 self-start">
                    <div class="text-3xl font-medium" x-text="money(product.price_cents, product.currency)"></div>
                    <div>
                        <span class="badge" :class="product.stock > 0 ? 'badge-ok' : 'badge-bad'"
                              x-text="product.stock > 0 ? t('buyer.product.stock', {count: product.stock}) : t('buyer.store.out_of_stock')"></span>
                    </div>

                    <form x-show="canBuy" class="flex flex-col gap-3" @submit.prevent="addToCart()">
                        <div>
                            <label for="qty">{{ __('buyer.product.quantity') }}</label>
                            <input id="qty" type="number" min="1" :max="product.stock" x-model.number="quantity" :disabled="product.stock < 1" inputmode="numeric">
                        </div>
                        <button type="submit" class="btn btn-primary" :disabled="adding || product.stock < 1"
                                x-text="adding ? t('common.loading') : t('buyer.product.add_to_cart')"></button>
                    </form>
                    <p x-show="!canBuy" x-cloak class="text-sm text-ink-2">{{ __('buyer.product.buyers_only') }}</p>

                    <p x-show="added" x-cloak class="text-sm text-ok" role="status">
                        {{ __('buyer.product.added') }}
                        <a href="{{ route('buyer.cart') }}">{{ __('buyer.product.view_cart') }}</a>
                    </p>
                    <p x-show="actionError" x-cloak class="text-sm text-bad" role="alert" x-text="actionError"></p>

                    <div class="border-t border-line pt-3">
                        <div class="text-sm text-ink-3">{{ __('buyer.product.sold_by') }}</div>
                        <a :href="'/v/' + product.vendor.id" class="font-medium" x-text="product.vendor.shop_name || product.vendor.handle"></a>
                        <div class="text-sm text-ink-2" x-show="vendor" x-text="vendor ? ratingText(vendor.rating_avg, vendor.rating_count) : ''"></div>
                    </div>
                </aside>
            </div>

            <section class="mt-8" aria-labelledby="reviews-h">
                <h2 id="reviews-h" class="mb-3 text-xl font-medium">{{ __('buyer.product.reviews') }}</h2>
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
                @include('partials.pager', ['ld' => 'reviewsLoading'])
            </section>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('productPage', (slug, role) => ({
        slug, role, product: null, vendor: null, reviews: [], quantity: 1,
        loading: true, error: '', reviewsLoading: false, reviewsError: '',
        adding: false, added: false, actionError: '',
        page: 1, lastPage: 1,
        get canBuy() { return this.role === null || this.role === 'buyer'; },
        ratingText(avg, count) {
            return count > 0 ? t('buyer.shared.rating', {avg: Number(avg).toFixed(1), count}) : t('buyer.shared.no_ratings');
        },
        async init() {
            this.loading = true; this.error = '';
            try {
                this.product = (await api('products/' + encodeURIComponent(this.slug))).data;
                document.title = this.product.title + ' | ' + document.title.split('|').pop().trim();
            } catch (e) {
                this.error = e.status === 404 ? t('buyer.product.not_found') : errText(e);
                this.loading = false;
                return;
            }
            this.loading = false;
            api('vendors/' + this.product.vendor.id).then(r => { this.vendor = r.data; }).catch(() => {});
            this.loadReviews();
        },
        go(n) { this.page = n; this.loadReviews(); },
        async loadReviews() {
            this.reviewsLoading = true; this.reviewsError = '';
            try {
                const r = await api('products/' + this.product.id + '/reviews', {query: {page: this.page, per_page: 10}});
                this.reviews = r.data; this.lastPage = r.meta?.last_page ?? 1;
            } catch (e) { this.reviewsError = errText(e); }
            finally { this.reviewsLoading = false; }
        },
        async addToCart() {
            if (this.adding) return;
            this.adding = true; this.added = false; this.actionError = '';
            try {
                await api('cart/items', {method: 'POST', body: {product_id: this.product.id, quantity: this.quantity || 1}});
                this.added = true;
            } catch (e) { this.actionError = errText(e); }
            finally { this.adding = false; }
        },
    }));
});
</script>
@endsection
