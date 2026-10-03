<?php

namespace App\Modules\Admin\Actions;

use App\Models\User;
use App\Modules\Admin\Exceptions\AdminException;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Admin product moderation and category management. */
class CatalogModeration
{
    public function __construct(private AuditLogger $audit) {}

    public function archive(User $actor, Product $product, string $reason): Product
    {
        return DB::transaction(function () use ($actor, $product, $reason) {
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);
            if ($product->status === Product::STATUS_ARCHIVED) {
                throw new AdminException('Product is already archived.', 409);
            }
            $before = $this->snap($product);
            $product->forceFill([
                'status' => Product::STATUS_ARCHIVED,
                'moderation_reason' => $reason,
                'moderated_at' => now(),
            ])->save();
            $this->audit->record('product.archived', 'product', $product->id, $before, $this->snap($product), $actor);

            return $product;
        });
    }

    /** Restore an archived product to draft so the vendor can republish it. */
    public function restore(User $actor, Product $product, ?string $reason = null): Product
    {
        return DB::transaction(function () use ($actor, $product, $reason) {
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);
            if ($product->status !== Product::STATUS_ARCHIVED) {
                throw new AdminException('Only archived products can be restored.', 409);
            }
            $before = $this->snap($product);
            $product->forceFill([
                'status' => Product::STATUS_DRAFT,
                'moderation_reason' => $reason,
                'moderated_at' => now(),
            ]);
            $product->save();
            $this->audit->record('product.restored', 'product', $product->id, $before, $this->snap($product), $actor);

            return $product;
        });
    }

    /** @param array{name:string, slug?:?string, parent_id?:?int} $data */
    public function createCategory(User $actor, array $data): Category
    {
        return DB::transaction(function () use ($actor, $data) {
            $cat = Category::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'parent_id' => $data['parent_id'] ?? null,
            ]);
            $this->audit->record('category.created', 'category', $cat->id, null, $this->catSnap($cat), $actor);

            return $cat;
        });
    }

    /** @param array{name?:string, slug?:string, parent_id?:?int} $data */
    public function updateCategory(User $actor, Category $category, array $data): Category
    {
        return DB::transaction(function () use ($actor, $category, $data) {
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);
            if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
                $this->assertNoCycle($category, (int) $data['parent_id']);
            }
            $before = $this->catSnap($category);
            $category->fill($data)->save();
            $this->audit->record('category.updated', 'category', $category->id, $before, $this->catSnap($category), $actor);

            return $category;
        });
    }

    public function deleteCategory(User $actor, Category $category): void
    {
        DB::transaction(function () use ($actor, $category) {
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);
            if (Category::where('parent_id', $category->id)->exists()) {
                throw new AdminException('Category has subcategories; move or delete them first.', 409);
            }
            if (Product::withTrashed()->where('category_id', $category->id)->exists()) {
                throw new AdminException('Category still has products; reassign them first.', 409);
            }
            $before = $this->catSnap($category);
            $category->delete();
            $this->audit->record('category.deleted', 'category', $category->id, $before, null, $actor);
        });
    }

    private function assertNoCycle(Category $category, int $parentId): void
    {
        $cursor = $parentId;
        $seen = 0;
        while ($cursor !== null && $seen++ < 100) {
            if ($cursor === $category->id) {
                throw new AdminException('A category cannot be its own ancestor.', 422);
            }
            $cursor = Category::query()->whereKey($cursor)->value('parent_id');
        }
    }

    /** @return array<string,mixed> */
    private function snap(Product $p): array
    {
        return ['status' => $p->status, 'moderation_reason' => $p->moderation_reason, 'deleted' => $p->trashed()];
    }

    /** @return array<string,mixed> */
    private function catSnap(Category $c): array
    {
        return ['name' => $c->name, 'slug' => $c->slug, 'parent_id' => $c->parent_id];
    }
}
