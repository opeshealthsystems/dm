<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });

        // One order per vendor: a multi-vendor checkout is split at placement time.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('vendor_id')->constrained('users');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_cents');
            $table->string('payment_method', 16)->nullable();            // bitcoin | monero
            $table->string('status', 24)->default('pending_payment')->index();
            $table->string('escrow_status', 16)->default('pending')->index(); // pending | held | released | refunded
            $table->string('shipment_status', 16)->default('pending');      // pending | shipped | delivered
            $table->text('shipping_address');                               // encrypted at rest (model cast)
            $table->string('tracking_number')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'created_at']);
            $table->index(['vendor_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');                          // snapshot: survives product edits/deletes
            $table->unsignedBigInteger('unit_price_cents');   // snapshot
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
    }
};
