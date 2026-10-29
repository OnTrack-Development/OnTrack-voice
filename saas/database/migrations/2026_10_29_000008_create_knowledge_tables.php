<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('kind', 32)->default('text');
            $table->string('scope', 32)->default('project');
            $table->string('status', 32)->default('draft');
            $table->string('source_hash', 128)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('knowledge_source_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('content')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('ingestion_status', 32)->default('pending');
            $table->unsignedInteger('chunk_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_source_id', 'version']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_version_id')->constrained('knowledge_source_versions')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->timestamps();

            $table->unique(['source_version_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_source_versions');
        Schema::dropIfExists('knowledge_sources');
    }
};