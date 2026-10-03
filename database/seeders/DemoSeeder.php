<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for local development only. Never run in production:
 *   php artisan db:seed --class=DemoSeeder
 * Creates a demo vendor and buyer (no admin; use `php artisan admin:create`).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder refuses to run in production.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        $password = Hash::make(bin2hex(random_bytes(8)));
        $vendor = User::firstOrCreate(['email' => 'demo-vendor@example.test'], ['name' => 'Demo Vendor', 'handle' => 'demo-vendor', 'password' => $password, 'shop_name' => 'Demo Shop']);
        $vendor->forceFill(['role' => User::ROLE_VENDOR, 'email_verified_at' => now()])->save();
        $buyer = User::firstOrCreate(['email' => 'demo-buyer@example.test'], ['name' => 'Demo Buyer', 'handle' => 'demo-buyer', 'password' => $password]);
        $buyer->forceFill(['email_verified_at' => now()])->save();

        $category = Category::where('slug', 'other')->first();
        foreach (['Demo widget' => 1999, 'Demo gadget' => 4999] as $title => $price) {
            $p = $vendor->products()->firstOrNew(['title' => $title]);
            $p->fill(['category_id' => $category?->id, 'price_cents' => $price, 'currency' => 'EUR', 'stock' => 10, 'status' => Product::STATUS_ACTIVE])->save();
        }

        $this->command?->info('Demo users have random passwords; reset them via tinker if you need to log in.');
    }
}
