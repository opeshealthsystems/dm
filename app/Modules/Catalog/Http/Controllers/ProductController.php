<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\ProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * List active products (public).
     *
     * Filter with `category_id`, `vendor_id`, `q` (title search). Paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()->active()->with('vendor:id,handle,shop_name')
            ->when($request->integer('category_id'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->integer('vendor_id'), fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->string('q')->trim()->value(), fn ($q, $v) => $q->where('title', 'like', '%' . addcslashes($v, '%_\\') . '%'))
            ->latest('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ProductResource::collection($products);
    }

    /** Show one active product (public). */
    public function show(string $slug): ProductResource
    {
        return new ProductResource(
            Product::query()->active()->with('vendor:id,handle,shop_name')->where('slug', $slug)->firstOrFail()
        );
    }

    /** Create a product owned by the authenticated vendor. Requires `catalog:write`. */
    public function store(ProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $request->user()->products()->create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /** Update one of your own products. Requires `catalog:write`. */
    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /** Delete one of your own products. Requires `catalog:write`. */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return response()->json(null, 204);
    }
}
