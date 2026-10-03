<?php

namespace Database\Seeders;

use App\Modules\Community\Models\CommunityCategory;
use Illuminate\Database\Seeder;

/** Default forum categories (idempotent). Names come from lang/<locale>/community.php via the keys. */
class CommunitySeeder extends Seeder
{
    /** slug => [English fallback name, staff_only] */
    public const CATEGORIES = [
        'announcements' => ['Announcements', true],
        'buyer-tips' => ['Buyer tips', false],
        'seller-help' => ['Seller help', false],
        'general' => ['General', false],
    ];

    public function run(): void
    {
        $position = 0;
        foreach (self::CATEGORIES as $slug => [$name, $staffOnly]) {
            $key = 'community.categories.' . str_replace('-', '_', $slug);
            CommunityCategory::query()->firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'name_key' => $key . '.name',
                'description_key' => $key . '.description',
                'position' => ++$position,
                'staff_only' => $staffOnly,
            ]);
        }
    }
}
