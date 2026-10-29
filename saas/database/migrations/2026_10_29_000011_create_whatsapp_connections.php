<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('public_id', 80)->unique();
            $table->string('method', 32);
            $table->string('phone_e164', 32)->nullable();
            $table->string('waba_id', 120)->nullable();
            $table->string('phone_number_id', 120)->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->string('state', 32)->default('draft');
            $table->string('status_reason_redacted', 500)->nullable();
            $table->timestamp('last_credentials_test_at')->nullable();
            $table->timestamp('last_webhook_verified_at')->nullable();
            $table->timestamp('last_send_test_at')->nullable();
            $table->timestamp('last_receive_test_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'phone_number_id']);
            $table->index(['tenant_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};