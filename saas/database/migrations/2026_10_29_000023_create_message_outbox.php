<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24)->default('ai_reply');
            $table->string('idempotency_key', 180);
            $table->text('body')->nullable();
            $table->timestamp('scheduled_at');
            $table->unsignedBigInteger('evaluated_conversation_version')->nullable();
            $table->string('status', 40)->default('pending');
            $table->string('last_block_reason', 60)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('provider_message_id', 180)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_outbox');
    }
};