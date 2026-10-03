<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cached running balance, one row per vendor and currency. Always changed under a row lock
        // in the same transaction as the ledger entry that explains the change.
        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->char('currency', 3);
            $table->bigInteger('balance_cents')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'currency']);
        });

        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_cents');
            $table->string('method', 16);                  // bitcoin | monero
            $table->text('destination_address');           // encrypted at rest (model cast)
            $table->string('status', 16)->default('pending')->index(); // pending | approved | rejected | paid
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->text('admin_note')->nullable();
            $table->string('txid', 128)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // Append-only ledger: rows are never updated or deleted. Signed integer cents.
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->char('currency', 3);
            $table->string('type', 24);                    // sale_credit | platform_fee | payout_debit | payout_reversal
            $table->bigInteger('amount_cents');            // + credit, - debit
            $table->bigInteger('balance_after_cents');
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->foreignId('payout_request_id')->nullable()->constrained('payout_requests');
            $table->string('idempotency_key', 64)->unique();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('wallet_accounts');
    }
};
