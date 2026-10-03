<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One dispute per order (legacy: eligible orders exclude those already disputed).
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders');
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('against_user')->constrained('users');
            $table->text('reason');                         // encrypted at rest
            $table->string('status', 16)->default('open')->index(); // open | resolved
            $table->string('order_status_before', 24);
            $table->string('outcome', 16)->nullable();       // buyer | vendor
            $table->text('resolution')->nullable();          // encrypted at rest
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('kind', 16)->default('message');  // message | evidence
            $table->text('body');                            // encrypted at rest
            $table->timestamps();
        });

        // Legacy vendor_fees: entry_fee / yearly_bond, unpaid -> paid.
        Schema::create('vendor_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users');
            $table->string('fee_type', 16);                  // entry_fee | yearly_bond
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 16)->default('unpaid')->index(); // unpaid | paid
            $table->string('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'fee_type', 'status']);
        });

        // Commission schedule: the tier with the highest min_sales_cents <= vendor lifetime sales wins.
        Schema::create('vendor_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->unsignedBigInteger('min_sales_cents')->default(0)->unique();
            $table->unsignedSmallInteger('commission_bps');  // 500 = 5.00%
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_fee_tiers');
        Schema::dropIfExists('vendor_fees');
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('disputes');
    }
};
