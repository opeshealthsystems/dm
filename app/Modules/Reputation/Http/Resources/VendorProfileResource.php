<?php

namespace App\Modules\Reputation\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public vendor profile with rating aggregate.
 *
 * @mixin User
 */
class VendorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'handle' => $this->handle,
            'shop_name' => $this->shop_name,
            'shop_description' => $this->shop_description,
            'is_verified_vendor' => $this->is_verified_vendor,
            'rating_avg' => $this->rating_avg === null ? null : (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'followers_count' => $this->when(isset($this->followers_count), fn () => (int) $this->followers_count),
        ];
    }
}
