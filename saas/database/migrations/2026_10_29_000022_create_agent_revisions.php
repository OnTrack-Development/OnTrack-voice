<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('name', 120);
            $table->string('kind', 40)->default('custom');
            $table->text('instructions')->nullable();
            $table->string('persona', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_notes')->nullable();
            $table->timestamps();
            $table->unique(['agent_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_revisions');
    }
};