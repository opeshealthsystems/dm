<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Production-safe seeder: default categories only (idempotent).
 * Demo users/products live in DemoSeeder and must be requested explicitly:
 *   php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const CATEGORIES = [
        'Electronics' => ['Phones', 'Computers', 'Audio'],
        'Fashion' => ['Clothing', 'Shoes', 'Accessories'],
        'Home & Garden' => ['Furniture', 'Kitchen', 'Garden'],
        'Books & Media' => [],
        'Sports & Outdoors' => [],
        'Health & Beauty' => [],
        'Toys & Games' => [],
        'Services' => [],
        'Other' => [],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $children) {
            $parent = Category::firstOrCreate(['slug' => str($name)->slug()->value()], ['name' => $name]);
            foreach ($children as $child) {
                Category::firstOrCreate(['slug' => str($child)->slug()->value()], ['name' => $child, 'parent_id' => $parent->id]);
            }
        }
    }
}
