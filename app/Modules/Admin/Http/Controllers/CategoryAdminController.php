<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\CatalogModeration;
use App\Modules\Admin\Http\Requests\CategoryRequest;
use App\Modules\Admin\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Admin: Categories
 */
class CategoryAdminController extends Controller
{
    public function __construct(private CatalogModeration $moderation) {}

    /** The full category tree (roots with nested `children`) and product counts. Requires `admin`. */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $all = Category::query()->withCount('products')->orderBy('name')->get();
        $byParent = $all->groupBy(fn ($c) => $c->parent_id ?? 0);
        $all->each(fn ($c) => $c->setAttribute('children_tree', $byParent->get($c->id, collect())->values()));

        return CategoryResource::collection($byParent->get(0, collect())->values());
    }

    /** Create a category. `slug` defaults to the slugified name and must be unique. Requires `admin`. */
    public function store(CategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);
        $category = $this->moderation->createCategory($request->user(), $request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    /** Update a category (rename, re-slug, re-parent without cycles). Requires `admin`. */
    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        return new CategoryResource($this->moderation->updateCategory($request->user(), $category, $request->validated()));
    }

    /** Delete a category that has no subcategories and no products. Requires `admin`. */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);
        $this->moderation->deleteCategory($request->user(), $category);

        return response()->json(null, 204);
    }
}
