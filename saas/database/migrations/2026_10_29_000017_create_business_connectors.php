<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_connector_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->default('rest');
            $table->string('name', 120);
            $table->string('base_url', 500)->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('connector_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_profile_id')->constrained('business_connector_profiles')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('method', 16)->default('GET');
            $table->string('path', 500);
            $table->json('permission_policy')->nullable();
            $table->json('request_schema')->nullable();
            $table->json('response_schema')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->index(['tenant_id', 'connector_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_endpoints');
        Schema::dropIfExists('business_connector_profiles');
    }
};