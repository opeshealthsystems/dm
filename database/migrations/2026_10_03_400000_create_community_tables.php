<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 80);
            $table->string('name_key', 120)->nullable();         // lang key; wins over `name` when set
            $table->string('description', 255)->nullable();
            $table->string('description_key', 120)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('staff_only')->default(false);        // only admins may start threads
            $table->timestamps();
        });

        Schema::create('community_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('community_categories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 190);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->timestamp('last_posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_pinned', 'last_posted_at']);
        });

        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('community_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thread_id', 'id']);
        });

        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 24);
            $table->string('note', 500)->nullable();
            $table->string('status', 16)->default('open')->index(); // open | dismissed | actioned
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('community_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('community_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
        });

        Schema::create('community_thread_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('community_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_post_id')->default(0);
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_thread_reads');
        Schema::dropIfExists('community_subscriptions');
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_threads');
        Schema::dropIfExists('community_categories');
    }
};
