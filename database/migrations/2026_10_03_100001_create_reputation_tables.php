<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->text('vendor_reply')->nullable();
            $table->timestamp('vendor_replied_at')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'buyer_id']); // one review per buyer per product
            $table->index(['vendor_id', 'created_at']);
        });

        Schema::create('review_helpful_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('vendor_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'vendor_id']);
        });

        // Denormalised aggregates so list endpoints never need per-row queries.
        foreach (['products', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('rating_avg', 3, 2)->nullable();
                $table->unsignedInteger('rating_count')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'users'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropColumn(['rating_avg', 'rating_count']));
        }
        Schema::dropIfExists('vendor_follows');
        Schema::dropIfExists('review_helpful_votes');
        Schema::dropIfExists('reviews');
    }
};
