<?php

namespace App\Modules\Reputation\Http\Resources;

use App\Modules\Reputation\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'vendor_id' => $this->vendor_id,
            'rating' => $this->rating,
            'body' => $this->body,
            'helpful_count' => $this->helpful_count,
            'buyer' => $this->whenLoaded('buyer', fn () => ['id' => $this->buyer->id, 'handle' => $this->buyer->handle]),
            'vendor_reply' => $this->vendor_reply,
            'vendor_replied_at' => $this->vendor_replied_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
