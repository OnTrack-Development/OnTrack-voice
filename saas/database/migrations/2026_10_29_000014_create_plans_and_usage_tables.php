<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('monthly_quota_credits')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 80);
            $table->string('unit', 32)->default('credits');
            $table->unsignedBigInteger('quota')->default(0);
            $table->timestamps();
            $table->unique(['plan_id', 'feature']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamp('started_at');
            $table->timestamp('renews_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_usage_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 80);
            $table->unsignedBigInteger('units')->default(1);
            $table->string('ref_type', 100)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('idempotency_key', 160);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'kind', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_ledgers');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('plans');
    }
};