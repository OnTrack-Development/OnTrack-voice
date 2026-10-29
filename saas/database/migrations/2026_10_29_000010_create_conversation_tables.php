<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_key', 200);
            $table->string('channel', 32)->default('web');
            $table->string('status', 24)->default('open');
            $table->timestamp('human_locked_at')->nullable();
            $table->timestamp('ai_pause_until_utc')->nullable();
            $table->timestamp('last_human_reply_at_utc')->nullable();
            $table->unsignedBigInteger('pause_generation')->nullable();
            $table->unsignedBigInteger('conversation_version')->default(1);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'channel']);
            $table->unique(['tenant_id', 'contact_key', 'channel']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24)->default('customer');
            $table->text('body');
            $table->string('delivery_status', 32)->default('accepted');
            $table->string('provider_message_id', 160)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['conversation_id', 'occurred_at']);
        });

        Schema::create('conversation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->json('payload_redacted')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['conversation_id', 'occurred_at']);
        });

        Schema::create('handoff_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 200)->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_automation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ai_reply_pause_minutes')->default(15);
            $table->timestamps();
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_automation_settings');
        Schema::dropIfExists('handoff_records');
        Schema::dropIfExists('conversation_events');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};