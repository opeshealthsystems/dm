<?php

namespace App\Modules\Community\Http\Resources;

use App\Modules\Community\Models\CommunityCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommunityCategory */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->displayName(),
            'name_key' => $this->name_key,
            'description' => $this->displayDescription(),
            'description_key' => $this->description_key,
            'position' => $this->position,
            'staff_only' => $this->staff_only,
            'threads_count' => $this->when(isset($this->threads_count), fn () => (int) $this->threads_count),
            'posts_count' => $this->when(array_key_exists('posts_sum', $this->resource->getAttributes()), fn () => (int) $this->posts_sum),
        ];
    }
}
