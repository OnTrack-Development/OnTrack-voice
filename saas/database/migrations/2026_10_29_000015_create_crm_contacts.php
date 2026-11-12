<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('display_name', 160)->nullable();
            $table->string('name', 190)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone_e164', 32)->nullable();
            $table->string('phone', 64)->nullable();
            $table->unsignedTinyInteger('dnc')->default(0);
            $table->string('gender_hint', 20)->nullable();
            $table->string('lead_status', 32)->default('new');
            $table->string('assigned_user_id')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'lead_status']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone_e164']);
            $table->index(['account_id', 'phone']);
            $table->index(['account_id', 'dnc']);
        });

        Schema::create('contact_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('external_subject_hash', 128);
            $table->string('verification_status', 24)->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'channel', 'external_subject_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_identities');
        Schema::dropIfExists('contacts');
    }
};