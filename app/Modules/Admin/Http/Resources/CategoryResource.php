<?php

namespace App\Modules\Admin\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'products_count' => $this->when(isset($this->products_count), fn () => (int) $this->products_count),
            'children' => $this->when(isset($this->children_tree), fn () => self::collection($this->children_tree)),
        ];
    }
}
