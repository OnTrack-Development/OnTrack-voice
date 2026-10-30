<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('account_label', 160);
            $table->string('external_account_id', 200)->nullable();
            $table->text('encrypted_config')->nullable();
            $table->text('encrypted_webhook_secret')->nullable();
            $table->string('state', 32)->default('draft');
            $table->json('capabilities')->nullable();
            $table->string('status_reason_redacted', 500)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'channel', 'state']);
            $table->unique(['tenant_id', 'channel', 'external_account_id']);
        });

        Schema::create('social_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_connection_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 220);
            $table->string('processing_status', 32)->default('received');
            $table->json('payload_redacted')->nullable();
            $table->timestamps();
            $table->unique(['social_connection_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_webhook_events');
        Schema::dropIfExists('social_connections');
    }
};