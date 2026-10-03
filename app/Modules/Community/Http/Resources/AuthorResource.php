<?php

namespace App\Modules\Community\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public author card. `badge` is `staff`, `verified_vendor` or null; nothing private is exposed. */
class AuthorResource extends JsonResource
{
    public static function badgeFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return match (true) {
            $user->isAdmin() => 'staff',
            $user->isVendor() && $user->is_verified_vendor => 'verified_vendor',
            default => null,
        };
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'handle' => $this->handle,
            'badge' => self::badgeFor($this->resource),
        ];
    }
}
