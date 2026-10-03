<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\CatalogModeration;
use App\Modules\Admin\Http\Requests\ArchiveProductRequest;
use App\Modules\Admin\Http\Requests\ReasonRequest;
use App\Modules\Admin\Http\Resources\AdminProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Admin: Catalog moderation
 */
class ProductAdminController extends Controller
{
    public function __construct(private CatalogModeration $moderation) {}

    /**
     * List all products, including drafts, archived and soft-deleted. Requires `admin`.
     *
     * Filters: `status`, `vendor_id`, `category_id`, `q` (title). Paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('admin');

        $products = Product::withTrashed()->with('vendor:id,handle')
            ->when($request->string('status')->value(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->integer('vendor_id'), fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->integer('category_id'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->string('q')->trim()->value(), fn ($q, $v) => $q->where('title', 'like', '%' . addcslashes($v, '%_\\') . '%'))
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return AdminProductResource::collection($products);
    }

    /** Force-archive a product with a reason. Requires `admin`. */
    public function archive(ArchiveProductRequest $request, int $product): AdminProductResource
    {
        $this->authorize('admin');
        $model = Product::withTrashed()->findOrFail($product);

        return new AdminProductResource($this->moderation->archive($request->user(), $model, $request->validated('reason')));
    }

    /** Restore an archived product to draft status. Requires `admin`. */
    public function restore(ReasonRequest $request, int $product): AdminProductResource
    {
        $this->authorize('admin');
        $model = Product::withTrashed()->findOrFail($product);

        return new AdminProductResource($this->moderation->restore($request->user(), $model, $request->validated('reason')));
    }
}
