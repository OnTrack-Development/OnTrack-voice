<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_ai_routing_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('feature', 80)->default('messages');
            $table->string('risk_class', 40)->default('low');
            $table->string('writer_model_id', 200)->nullable();
            $table->string('reviewer_model_id', 200)->nullable();
            $table->string('fallback_model_id', 200)->nullable();
            $table->json('config_json')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature', 'risk_class']);
        });

        Schema::create('platform_ai_request_costs', function (Blueprint $table) {
            $table->id();
            $table->string('provider_model_id', 200);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedInteger('units')->default(0);
            $table->timestamps();
            $table->index(['provider_model_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_request_costs');
        Schema::dropIfExists('platform_ai_routing_policies');
    }
};