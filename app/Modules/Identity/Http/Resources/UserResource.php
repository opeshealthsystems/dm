<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'handle' => $this->handle,
            'email' => $this->email,
            'role' => $this->role,
            'shop_name' => $this->shop_name,
            'shop_description' => $this->shop_description,
            'email_verified' => $this->hasVerifiedEmail(),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'is_verified_vendor' => $this->is_verified_vendor,
            'rating_avg' => $this->rating_avg === null ? null : (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
        ];
    }
}
