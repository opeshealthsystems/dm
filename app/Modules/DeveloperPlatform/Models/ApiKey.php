<?php

namespace App\Modules\DeveloperPlatform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiKey extends Model
{
    public const ALLOWED_SCOPES = ['catalog:read', 'orders:read'];

    protected $fillable = ['user_id', 'name', 'prefix', 'key_hash', 'scopes', 'expires_at'];
    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ApiKeyUsage::class);
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
