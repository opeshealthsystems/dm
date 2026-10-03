<?php

namespace App\Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityReport extends Model
{
    public const REASONS = ['spam', 'abuse', 'illegal', 'off_topic', 'other'];
    public const STATUS_OPEN = 'open';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ACTIONED = 'actioned';

    protected $table = 'community_reports';

    protected $fillable = ['post_id', 'user_id', 'reason', 'note'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id')->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
