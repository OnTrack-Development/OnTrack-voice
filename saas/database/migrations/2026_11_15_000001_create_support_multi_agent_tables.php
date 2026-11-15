<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_specialist_agents')) {
            Schema::create('support_specialist_agents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('slug', 80)->index();
                $table->string('display_name', 150);
                $table->string('role_title', 150);
                $table->string('agent_type', 40)->default('consultant')->index(); // dispatcher, consultant, executor, reviewer
                $table->text('personality')->nullable();
                $table->longText('system_prompt')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->index(['account_id', 'slug']);
            });
        }

        if (! Schema::hasTable('support_contracts')) {
            Schema::create('support_contracts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('code', 100)->index();
                $table->string('name', 180);
                $table->text('description')->nullable();
                $table->longText('mission')->nullable();
                $table->json('allowed_tools')->nullable();
                $table->json('read_permissions')->nullable();
                $table->json('action_permissions')->nullable();
                $table->json('forbidden_actions')->nullable();
                $table->json('escalation_rules')->nullable();
                $table->string('status', 40)->default('active')->index();
                $table->timestamps();

                $table->index(['account_id', 'code']);
            });
        }

        if (! Schema::hasTable('support_agent_contracts')) {
            Schema::create('support_agent_contracts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->index();
                $table->unsignedBigInteger('contract_id')->index();
                $table->integer('priority')->default(100);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                $table->unique(['agent_id', 'contract_id']);
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('external_ticket_id', 100)->nullable()->index();
                $table->string('source', 50)->default('whmcs')->index(); // whmcs, web_chat, email, whatsapp, api
                $table->string('client_id', 100)->nullable()->index();
                $table->string('client_name', 190)->nullable();
                $table->string('client_email', 190)->nullable()->index();
                $table->string('client_phone', 60)->nullable()->index();
                $table->string('subject', 255);
                $table->string('department', 100)->nullable()->index();
                $table->string('priority', 30)->default('medium');
                $table->string('status', 40)->default('open')->index();
                $table->unsignedBigInteger('assigned_agent_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_human_id')->nullable()->index();
                $table->boolean('is_human_handoff')->default(false)->index();
                $table->string('handoff_reason', 150)->nullable();
                $table->longText('latest_message')->nullable();
                $table->longText('context_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'status']);
                $table->index(['account_id', 'source', 'external_ticket_id']);
            });
        }

        if (! Schema::hasTable('support_ticket_drafts')) {
            Schema::create('support_ticket_drafts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->longText('draft_text');
                $table->string('reviewer_verdict', 40)->default('pending')->index(); // pending, approved, modified, rejected
                $table->text('reviewer_feedback')->nullable();
                $table->text('evidence_summary')->nullable();
                $table->decimal('confidence', 5, 4)->nullable();
                $table->boolean('is_published')->default(false)->index();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_consultations')) {
            Schema::create('support_consultations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('requester_agent_slug', 80);
                $table->string('specialist_agent_slug', 80);
                $table->string('focus', 80);
                $table->text('query');
                $table->longText('response')->nullable();
                $table->json('evidence')->nullable();
                $table->string('status', 40)->default('scheduled'); // scheduled, completed, limited, failed
                $table->unsignedInteger('elapsed_ms')->default(0);
                $table->timestamps();

                $table->index(['ticket_id', 'specialist_agent_slug']);
            });
        }

        if (! Schema::hasTable('support_whatsapp_sessions')) {
            Schema::create('support_whatsapp_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('phone', 60)->index();
                $table->string('phone_hash', 64)->index();
                $table->string('driver', 30)->default('meta')->index(); // meta, qr
                $table->string('client_id', 100)->nullable()->index();
                $table->boolean('is_verified')->default(false)->index();
                $table->timestamp('verified_at')->nullable();
                $table->string('verification_token', 80)->nullable()->index();
                $table->timestamp('verification_expires_at')->nullable();
                $table->string('state', 60)->default('idle')->index(); // idle, wa_ticket_collect, wa_ticket_create, wa_ticket_confirm
                $table->json('state_payload')->nullable();
                $table->boolean('is_muted_for_human')->default(false)->index();
                $table->timestamp('muted_until')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->unique(['account_id', 'phone']);
            });
        }

        if (! Schema::hasTable('support_learning_runs')) {
            Schema::create('support_learning_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('status', 40)->default('draft');
                $table->unsignedInteger('discovered_count')->default(0);
                $table->unsignedInteger('processed_count')->default(0);
                $table->string('knowledge_version', 80)->nullable();
                $table->text('summary')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_learning_patterns')) {
            Schema::create('support_learning_patterns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('pattern_key', 80)->index();
                $table->string('category', 100)->index();
                $table->text('problem_signature');
                $table->longText('recommended_solution');
                $table->unsignedInteger('evidence_count')->default(1);
                $table->decimal('confidence', 5, 4)->default(0.85);
                $table->string('status', 40)->default('approved')->index();
                $table->timestamps();

                $table->unique(['account_id', 'pattern_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_learning_patterns');
        Schema::dropIfExists('support_learning_runs');
        Schema::dropIfExists('support_whatsapp_sessions');
        Schema::dropIfExists('support_consultations');
        Schema::dropIfExists('support_ticket_drafts');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_agent_contracts');
        Schema::dropIfExists('support_contracts');
        Schema::dropIfExists('support_specialist_agents');
    }
};
