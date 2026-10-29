<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-admin grants — separate from tenant membership entirely.
        Schema::create('platform_admin_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('platform_ai_ops');
            $table->timestamps();

            $table->unique('user_id');
        });

        // AI provider profiles are GLOBAL — intentionally no tenant_id.
        Schema::create('platform_ai_provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->default('openai_compatible');
            $table->string('name', 120);
            $table->string('base_url', 500)->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->json('config_json')->nullable();
            $table->string('connection_status', 32)->default('not_configured');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        // Exact model IDs — platform maintainers only.
        Schema::create('platform_ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained('platform_ai_provider_profiles')->cascadeOnDelete();
            $table->string('model_id_exact', 200);
            $table->string('label', 120)->nullable();
            $table->string('test_status', 24)->default('untested');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_profile_id', 'model_id_exact']);
        });

        Schema::create('platform_ai_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('resource_type', 64)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('redacted_summary', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_audit_events');
        Schema::dropIfExists('platform_ai_models');
        Schema::dropIfExists('platform_ai_provider_profiles');
        Schema::dropIfExists('platform_admin_grants');
    }
};