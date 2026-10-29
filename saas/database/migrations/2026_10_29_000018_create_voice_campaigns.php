<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('status', 24)->default('draft');
            $table->string('timezone', 64)->default('Africa/Cairo');
            $table->unsignedInteger('delay_seconds')->default(30);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('voice_campaign_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('voice_campaigns')->cascadeOnDelete();
            $table->string('phone_e164', 32);
            $table->string('display_name', 160)->nullable();
            $table->json('source_fields')->nullable();
            $table->string('queue_status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('telephony_outcome', 32)->nullable();
            $table->string('business_outcome', 32)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_call_ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'phone_e164']);
            $table->index(['tenant_id', 'queue_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_campaign_entries');
        Schema::dropIfExists('voice_campaigns');
    }
};