<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->string('client_name', 190);
                $table->string('client_phone', 64);
                $table->string('client_email', 190)->nullable();
                $table->string('service', 190)->default('استشارة عامة');
                $table->date('date')->index();
                $table->string('time', 20)->default('10:00 AM');
                $table->unsignedInteger('duration_minutes')->default(30);
                $table->string('status', 32)->default('confirmed')->index(); // confirmed, pending, completed, no-show, cancelled
                $table->string('booked_by', 100)->default('Website Widget');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'date']);
                $table->index(['account_id', 'status']);
            });
        }

        if (! Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->string('name', 190);
                $table->string('phone', 64)->index();
                $table->string('email', 190)->nullable();
                $table->string('company', 190)->nullable();
                $table->string('status', 32)->default('new')->index(); // new, qualified, waiting_for_human, closed, contacted
                $table->string('source', 50)->default('manual')->index(); // voice_call, whatsapp, website_widget, whmcs, manual
                $table->decimal('value', 12, 2)->nullable();
                $table->text('notes')->nullable();
                $table->string('assigned_to', 100)->nullable();
                $table->timestamp('last_contact_at')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'status']);
                $table->index(['account_id', 'phone']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('leads');
    }
};
