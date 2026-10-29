<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->string('event_key', 200);
            $table->timestamp('signature_verified_at')->nullable();
            $table->string('processing_status', 32)->default('received');
            $table->json('payload_redacted')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'event_key']);
            $table->index(['tenant_id', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};