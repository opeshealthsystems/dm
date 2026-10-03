<?php

namespace App\Modules\Community\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CommunityThread extends Model
{
    use SoftDeletes;

    protected $table = 'community_threads';

    // Pin/lock/counters change only through CommunityService.
    protected $fillable = ['category_id', 'user_id', 'product_id', 'title'];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean', 'is_locked' => 'boolean', 'posts_count' => 'integer',
            'last_posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $thread) {
            $thread->slug = Str::slug(Str::limit($thread->title, 80, '')) ?: 'thread';
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CommunityCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'thread_id');
    }

    /** Canonical path: /community/t/{id}-{slug}. */
    public function path(): string
    {
        return '/community/t/' . $this->id . '-' . $this->slug;
    }
}
