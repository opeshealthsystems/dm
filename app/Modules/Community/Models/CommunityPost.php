<?php

namespace App\Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model
{
    use SoftDeletes;

    protected $table = 'community_posts';

    protected $fillable = ['thread_id', 'user_id', 'body'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunityThread::class, 'thread_id')->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityReport::class, 'post_id');
    }

    /** Within the author's edit window (config community.edit_window_minutes). */
    public function isWithinEditWindow(): bool
    {
        return $this->created_at !== null
            && $this->created_at->gt(now()->subMinutes((int) config('community.edit_window_minutes')));
    }
}
