<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('public_key', 100)->unique();
            $table->string('status', 24)->default('active');
            $table->json('allowed_origins')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('widget_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('widget_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_hash', 128)->unique();
            $table->string('subject_hash', 128)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'widget_key_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_sessions');
        Schema::dropIfExists('widget_keys');
    }
};