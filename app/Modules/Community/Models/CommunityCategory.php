<?php

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityCategory extends Model
{
    protected $table = 'community_categories';

    protected $fillable = ['slug', 'name', 'name_key', 'description', 'description_key', 'position', 'staff_only'];

    protected function casts(): array
    {
        return ['staff_only' => 'boolean', 'position' => 'integer'];
    }

    public function threads(): HasMany
    {
        return $this->hasMany(CommunityThread::class, 'category_id');
    }

    /** Display name: the translation key wins when present and translated. */
    public function displayName(): string
    {
        return $this->name_key && __($this->name_key) !== $this->name_key ? __($this->name_key) : $this->name;
    }

    public function displayDescription(): ?string
    {
        return $this->description_key && __($this->description_key) !== $this->description_key
            ? __($this->description_key) : $this->description;
    }
}
