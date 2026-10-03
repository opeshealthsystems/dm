<?php

namespace App\Modules\ContentSeo\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message sent through the public contact form. The message body is stored encrypted. */
class SupportRequest extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_HANDLED = 'handled';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['message' => 'encrypted', 'handled_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
