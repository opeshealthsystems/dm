<?php

namespace App\Models;

use App\Modules\Catalog\Models\Product;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'handle', 'email', 'password', 'shop_name', 'shop_description'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_last_step'])]
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_BUYER = 'buyer';
    public const ROLE_VENDOR = 'vendor';
    public const ROLE_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'is_verified_vendor' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(\App\Modules\Identity\Models\RecoveryCode::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** Mail the verification link (see Identity\Actions\EmailVerification). */
    public function sendEmailVerificationNotification(): void
    {
        app(\App\Modules\Identity\Actions\EmailVerification::class)->send($this);
    }

    /** Mail the password reset link (see Identity\Actions\PasswordReset). */
    public function sendPasswordResetNotification($token): void
    {
        app(\App\Modules\Identity\Actions\PasswordReset::class)->mail($this, (string) $token);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isVendor(): bool
    {
        return $this->role === self::ROLE_VENDOR;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * OAuth scopes a user may hold, by role. Tokens are never issued with more.
     *
     * @return list<string>
     */
    public function allowedScopes(): array
    {
        return match ($this->role) {
            self::ROLE_ADMIN => ['profile', 'catalog:read', 'catalog:write', 'orders:read', 'orders:write', 'vendor:manage', 'admin'],
            self::ROLE_VENDOR => ['profile', 'catalog:read', 'catalog:write', 'orders:read', 'vendor:manage'],
            default => ['profile', 'catalog:read', 'orders:read', 'orders:write'],
        };
    }
}
