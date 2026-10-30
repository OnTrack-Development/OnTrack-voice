<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('status', 24)->default('draft');
            $table->unsignedBigInteger('draft_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamps();
            $table->unique(['agent_id', 'name']);
            $table->index(['tenant_id', 'workspace_id', 'status']);
        });

        Schema::create('agent_flow_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained('agent_flows')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('graph_schema_version')->default(1);
            $table->string('status', 24)->default('draft');
            $table->string('entry_node_key', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->text('change_notes')->nullable();
            $table->timestamps();
            $table->unique(['flow_id', 'version']);
        });

        Schema::create('agent_flow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flow_revision_id')->constrained('agent_flow_revisions')->cascadeOnDelete();
            $table->string('stable_key', 120);
            $table->string('type', 40);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->decimal('position_x', 10, 2)->default(0);
            $table->decimal('position_y', 10, 2)->default(0);
            $table->json('config_json')->nullable();
            $table->string('status', 24)->default('valid');
            $table->timestamps();
            $table->unique(['flow_revision_id', 'stable_key']);
        });

        Schema::create('agent_flow_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flow_revision_id')->constrained('agent_flow_revisions')->cascadeOnDelete();
            $table->foreignId('source_node_id')->constrained('agent_flow_nodes')->cascadeOnDelete();
            $table->foreignId('target_node_id')->constrained('agent_flow_nodes')->cascadeOnDelete();
            $table->string('label', 180)->nullable();
            $table->string('type', 32)->default('default');
            $table->json('condition_json')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['flow_revision_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_flow_edges');
        Schema::dropIfExists('agent_flow_nodes');
        Schema::dropIfExists('agent_flow_revisions');
        Schema::dropIfExists('agent_flows');
    }
};