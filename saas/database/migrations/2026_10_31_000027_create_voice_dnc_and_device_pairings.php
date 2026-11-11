<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_dnc_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Console account IDs belong to another schema; never add a false FK.
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('phone_normalized', 40);
            $table->string('reason', 160)->default('opt_out');
            $table->string('source', 32)->default('contact_optout');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'phone_normalized']);
            $table->index(['account_id', 'phone_normalized']);
        });

        Schema::create('voice_device_pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('device_id', 128)->unique();
            $table->char('pairing_token_hash', 64)->unique();
            $table->string('status', 24)->default('pending');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['account_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_device_pairings');
        Schema::dropIfExists('voice_dnc_entries');
    }
};
