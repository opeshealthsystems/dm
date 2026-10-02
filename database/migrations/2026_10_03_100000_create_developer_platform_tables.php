<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('prefix', 16);
            $t->string('key_hash', 64)->unique();
            $t->json('scopes');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('api_key_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $t->date('day');
            $t->unsignedBigInteger('requests')->default(0);
            $t->unique(['api_key_id', 'day']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('url', 500);
            $t->text('secret');
            $t->json('events');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $t->uuid('event_id');
            $t->string('event', 50);
            $t->json('payload');
            $t->string('status', 20)->default('pending'); // pending|retrying|delivered|failed
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index(['webhook_endpoint_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('api_key_usages');
        Schema::dropIfExists('api_keys');
    }
};
