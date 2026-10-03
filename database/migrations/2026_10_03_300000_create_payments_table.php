<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 16);                          // bitcoin | monero
            $table->text('address');                               // encrypted at rest (model cast)
            $table->unsignedBigInteger('derivation_index')->nullable();
            $table->unsignedBigInteger('expected_atomic');         // satoshi / piconero
            $table->unsignedBigInteger('detected_atomic')->default(0);
            $table->unsignedBigInteger('confirmed_atomic')->default(0);
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('status', 16)->default('pending')->index(); // pending|detected|confirmed|expired|underpaid
            $table->json('meta')->nullable();                      // rate snapshot, txids
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'method']);
            $table->unique(['method', 'derivation_index']);       // an index can never be reused
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
