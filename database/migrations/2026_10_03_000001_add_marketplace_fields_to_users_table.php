<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('buyer')->after('password')->index(); // buyer | vendor | admin
            $table->string('handle', 64)->nullable()->unique()->after('name');
            $table->string('shop_name')->nullable();
            $table->text('shop_description')->nullable();
            $table->boolean('is_verified_vendor')->default(false);
            $table->timestamp('suspended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'handle', 'shop_name', 'shop_description', 'is_verified_vendor', 'suspended_at']);
        });
    }
};
