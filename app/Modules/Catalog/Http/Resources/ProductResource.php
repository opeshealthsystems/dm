<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'stock' => $this->stock,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'rating_avg' => $this->rating_avg === null ? null : (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'vendor' => [
                'id' => $this->vendor_id,
                'handle' => $this->whenLoaded('vendor', fn () => $this->vendor->handle),
                'shop_name' => $this->whenLoaded('vendor', fn () => $this->vendor->shop_name),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
