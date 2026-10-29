<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('button_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label_ar', 160);
            $table->string('action_key', 100);
            $table->string('min_identity_level', 32)->default('guest');
            $table->json('business_state_in')->nullable();
            $table->json('condition_ast')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->index(['tenant_id', 'workspace_id', 'status']);
        });

        Schema::create('button_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('button_rule_id')->constrained()->cascadeOnDelete();
            $table->string('subject_key', 160);
            $table->string('state_revision', 120);
            $table->string('presentation_token_hash', 128)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['tenant_id', 'subject_key']);
        });

        Schema::create('button_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('button_rule_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 160);
            $table->string('state_revision', 120);
            $table->string('status', 24)->default('accepted');
            $table->text('result_summary')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('button_invocations');
        Schema::dropIfExists('button_presentations');
        Schema::dropIfExists('button_rules');
    }
};