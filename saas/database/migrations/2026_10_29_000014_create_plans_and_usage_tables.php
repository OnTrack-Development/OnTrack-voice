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
            $table->string('kind', 64)->nullable()->default('customer');
            $table->string('name', 190);
            $table->string('status', 32)->default('active');
            $table->unsignedBigInteger('monthly_quota_credits')->default(0);
            $table->unsignedBigInteger('call_quota')->default(0);
            $table->unsignedInteger('site_limit')->default(1);
            $table->unsignedInteger('agent_limit')->default(1);
            $table->unsignedInteger('staff_limit')->default(3);
            $table->unsignedInteger('reseller_customers')->default(0);
            $table->bigInteger('price_minor')->default(0);
            $table->unsignedInteger('overage_minor')->default(0);
            $table->char('currency', 3)->default('EGP');
            $table->string('billing_interval', 24)->default('monthly');
            $table->unsignedInteger('google_key_limit')->default(0);
            $table->unsignedInteger('assistant_key_limit')->default(0);
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
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->string('starts_at', 40)->nullable();
            $table->string('ends_at', 40)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['account_id', 'status', 'ends_at']);
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