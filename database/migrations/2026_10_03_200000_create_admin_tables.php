<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();           // e.g. user.suspended
            $table->string('target_type', 32)->nullable();   // user | product | category | setting | admin
            $table->string('target_id', 64)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['target_type', 'target_id']);
            $table->index(['actor_id', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string'); // string | int | bool
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('moderation_reason', 500)->nullable();
            $table->timestamp('moderated_at')->nullable();
        });

        // Indexes backing the dashboard aggregates.
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'currency'], 'orders_status_currency_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'users_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('users_created_at_index'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropIndex('orders_status_currency_index'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['moderation_reason', 'moderated_at']));
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
    }
};
