<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 40)->nullable()->index();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('name', 190)->nullable();
                $table->string('slug', 190)->nullable()->unique();
                $table->string('status', 40)->default('active');
                $table->text('brand_name')->nullable();
                $table->text('logo_path')->nullable();
                $table->string('domain', 190)->nullable()->index();
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('memberships')) {
            Schema::create('memberships', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('role', 60)->nullable();
                $table->string('created_at', 40)->nullable();
                $table->unique(['user_id', 'account_id']);
                $table->index(['account_id', 'role']);
            });
        }

        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('name', 190)->nullable();
                $table->string('domain', 190)->nullable();
                $table->string('status', 40)->default('active');
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('domains')) {
            Schema::create('domains', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('hostname', 190)->nullable()->unique();
                $table->string('verification_token', 190)->nullable();
                $table->string('status', 40)->default('pending');
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->primary();
                $table->string('contact_name', 190)->default('');
                $table->string('contact_email', 190)->default('');
                $table->string('phone', 45)->default('');
                $table->text('address');
                $table->text('notes');
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('brand_identity_settings')) {
            Schema::create('brand_identity_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->primary();
                $table->string('display_mode', 12)->default('both');
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('console_theme_palettes')) {
            Schema::create('console_theme_palettes', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->primary();
                $table->text('palette_json');
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('console_font_preferences')) {
            Schema::create('console_font_preferences', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('user_id');
                $table->string('font_code', 24);
                $table->string('updated_at', 19);
                $table->primary(['account_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('platform_feature_settings')) {
            Schema::create('platform_feature_settings', function (Blueprint $table) {
                $table->string('feature_key', 64)->primary();
                $table->integer('enabled')->default(1);
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('recycle_items')) {
            Schema::create('recycle_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('kind', 16);
                $table->unsignedBigInteger('item_id');
                $table->string('archived_at', 19);
                $table->string('restored_at', 19)->nullable();
                $table->unique(['account_id', 'kind', 'item_id']);
            });
        }

        if (! Schema::hasTable('recycle_settings')) {
            Schema::create('recycle_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->primary();
                $table->integer('retention_days')->default(30);
            });
        }

        if (! Schema::hasTable('reseller_plan_owners')) {
            Schema::create('reseller_plan_owners', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->primary();
                $table->unsignedBigInteger('reseller_account_id')->index();
                $table->unsignedBigInteger('source_plan_id')->nullable();
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('subscription_cancellation_intents')) {
            Schema::create('subscription_cancellation_intents', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->primary();
                $table->unsignedBigInteger('subscription_id');
                $table->string('effective_at', 19);
                $table->string('requested_at', 19);
            });
        }

        if (! Schema::hasTable('admin_mfa')) {
            Schema::create('admin_mfa', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->primary();
                $table->text('secret_enc');
                $table->bigInteger('last_step')->default(-1);
                $table->string('created_at', 40);
            });
        }

        if (! Schema::hasTable('auth_login_throttle')) {
            Schema::create('auth_login_throttle', function (Blueprint $table) {
                $table->char('bucket_hash', 64)->primary();
                $table->integer('attempts')->default(0);
                $table->string('first_attempt_at', 19);
                $table->string('blocked_until', 19)->nullable();
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('password_reset_requests')) {
            Schema::create('password_reset_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('token_hash', 128)->unique();
                $table->string('expires_at', 40);
                $table->string('status', 24)->default('pending');
                $table->string('created_at', 40);
            });
        }

        if (! Schema::hasTable('signup_requests')) {
            Schema::create('signup_requests', function (Blueprint $table) {
                $table->id();
                $table->string('email', 190)->unique();
                $table->string('name', 120);
                $table->string('company_name', 120);
                $table->text('password_hash');
                $table->string('token_hash', 128)->nullable()->unique();
                $table->string('status', 24);
                $table->string('expires_at', 40);
                $table->string('created_at', 40);
                $table->string('registration_kind', 16)->default('customer');
                $table->unsignedBigInteger('parent_account_id')->nullable();
            });
        }

        if (! Schema::hasTable('staff_invitations')) {
            Schema::create('staff_invitations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('email', 190);
                $table->string('name', 190);
                $table->string('role', 40);
                $table->string('status', 24)->default('pending');
                $table->unsignedBigInteger('created_by');
                $table->string('created_at', 40);
                $table->string('expires_at', 40);
                $table->unsignedBigInteger('accepted_by')->nullable();
                $table->string('accepted_at', 40)->nullable();
                $table->unique(['account_id', 'email']);
            });
        }

        if (! Schema::hasTable('support_grants')) {
            Schema::create('support_grants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
                $table->unsignedBigInteger('target_account_id')->nullable()->index();
                $table->text('reason')->nullable();
                $table->string('expires_at', 40)->nullable();
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('support_policies')) {
            Schema::create('support_policies', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->primary();
                $table->string('access_mode', 16)->default('full');
            });
        }

        if (! Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('name', 190)->nullable();
                $table->string('prefix', 64)->nullable();
                $table->string('token_hash', 128)->nullable()->unique();
                $table->string('scopes', 190)->nullable();
                $table->string('status', 40)->default('active');
                $table->string('last_used_at', 40)->nullable();
                $table->string('created_at', 40)->nullable();
                $table->text('secret_enc')->nullable();
            });
        }

        if (! Schema::hasTable('integrations')) {
            Schema::create('integrations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('name', 190)->nullable();
                $table->string('provider', 120)->nullable();
                $table->string('token_hash', 128)->nullable()->unique();
                $table->text('token_prefix')->nullable();
                $table->string('status', 40)->default('active');
                $table->string('created_at', 40)->nullable();
                $table->text('secret_enc')->nullable();
            });
        }

        if (! Schema::hasTable('integration_facts')) {
            Schema::create('integration_facts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('integration_id');
                $table->string('category', 30);
                $table->string('fact_key', 80);
                $table->text('fact_value');
                $table->string('updated_at', 19);
                $table->unique(['account_id', 'integration_id', 'category', 'fact_key'], 'uq_int_facts_scope');
            });
        }

        if (! Schema::hasTable('external_identity_links')) {
            Schema::create('external_identity_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_id');
                $table->string('external_subject', 128);
                $table->unsignedBigInteger('user_id');
                $table->string('created_at', 40);
                $table->unique(['integration_id', 'external_subject']);
                $table->unique(['integration_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('sso_used_tickets')) {
            Schema::create('sso_used_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_id');
                $table->string('jti_hash', 64);
                $table->string('expires_at', 40);
                $table->string('created_at', 40);
                $table->unique(['integration_id', 'jti_hash']);
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('external_order_id', 160)->nullable();
                $table->unsignedBigInteger('integration_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->text('customer_name')->nullable();
                $table->text('customer_email')->nullable();
                $table->bigInteger('amount_minor')->nullable();
                $table->string('status', 60)->nullable();
                $table->string('payment_reference', 190)->nullable();
                $table->string('created_at', 40)->nullable();
                $table->unique(['integration_id', 'external_order_id']);
            });
        }

        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('integration_id')->nullable();
                $table->string('external_id', 160)->nullable();
                $table->string('event_type', 120)->nullable();
                $table->longText('payload')->nullable();
                $table->string('status', 40)->nullable();
                $table->string('created_at', 40)->nullable();
                $table->unique(['integration_id', 'external_id']);
            });
        }

        if (! Schema::hasTable('billing_invoices')) {
            Schema::create('billing_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_no', 42)->nullable()->unique();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('issuer_account_id');
                $table->unsignedBigInteger('order_id')->unique();
                $table->unsignedBigInteger('plan_id');
                $table->string('bill_to_name', 190);
                $table->string('bill_to_email', 190);
                $table->string('issuer_name', 190);
                $table->string('plan_name', 190);
                $table->bigInteger('amount_minor');
                $table->char('currency', 3)->default('EGP');
                $table->string('status', 24);
                $table->string('issued_at', 19);
                $table->string('due_at', 19);
                $table->string('paid_at', 19)->nullable();
                $table->string('payment_reference', 190)->nullable();
                $table->string('cancelled_at', 19)->nullable();
            });
        }

        if (! Schema::hasTable('billing_invoice_events')) {
            Schema::create('billing_invoice_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('event', 32);
                $table->string('reference', 190)->nullable();
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('kashier_refund_events')) {
            Schema::create('kashier_refund_events', function (Blueprint $table) {
                $table->string('refund_reference', 160)->primary();
                $table->unsignedBigInteger('order_id');
                $table->string('original_reference', 160);
                $table->string('event_kind', 24);
                $table->bigInteger('amount_minor');
                $table->string('status', 24);
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('usage_ledger')) {
            Schema::create('usage_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('kind', 120)->nullable();
                $table->bigInteger('units')->nullable();
                $table->string('reference', 190)->nullable()->unique();
                $table->string('created_at', 40)->nullable();
                $table->index(['account_id', 'kind', 'created_at']);
            });
        }

        if (! Schema::hasTable('audit_events')) {
            Schema::create('audit_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->text('action')->nullable();
                $table->longText('detail')->nullable();
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('agent_answer_policies')) {
            Schema::create('agent_answer_policies', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('agent_id');
                $table->longText('policy_json');
                $table->string('updated_at', 40);
                $table->primary(['account_id', 'agent_id']);
            });
        }

        if (! Schema::hasTable('agent_flow_versions')) {
            Schema::create('agent_flow_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('agent_id');
                $table->integer('revision');
                $table->string('flow_hash', 64);
                $table->string('snapshot_hash', 64);
                $table->longText('flow_json');
                $table->string('voice', 190);
                $table->longText('instructions');
                $table->longText('knowledge');
                $table->unsignedBigInteger('published_by');
                $table->string('published_at', 40);
                $table->longText('answer_policy_json')->nullable();
                $table->unique(['agent_id', 'revision']);
                $table->index(['account_id', 'site_id', 'agent_id'], 'ix_tenant_agent_flow');
            });
        }

        if (! Schema::hasTable('ai_relay_entries')) {
            Schema::create('ai_relay_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('scope', 16);
                $table->string('driver', 32);
                $table->string('label', 120);
                $table->string('model', 120);
                $table->string('endpoint', 600)->default('');
                $table->text('secret_enc');
                $table->integer('priority');
                $table->integer('enabled')->default(1);
                $table->string('cooldown_until', 19)->nullable();
                $table->string('last_status', 32)->default('new');
                $table->string('last_checked_at', 19)->nullable();
                $table->string('created_at', 19);
                $table->index(['account_id', 'scope', 'enabled', 'priority'], 'ix_relay_scope_prio');
            });
        }

        if (! Schema::hasTable('google_voice_key_overrides')) {
            Schema::create('google_voice_key_overrides', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->primary();
                $table->integer('max_keys');
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('google_voice_live_calls')) {
            Schema::create('google_voice_live_calls', function (Blueprint $table) {
                $table->string('ticket', 48)->primary();
                $table->unsignedBigInteger('binding_id')->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('pool_owner_id');
                $table->unsignedBigInteger('current_key_id');
                $table->unsignedBigInteger('agent_id');
                $table->unsignedBigInteger('site_id');
                $table->integer('sim_slot');
                $table->integer('revision');
                $table->string('flow_hash', 64);
                $table->integer('generation')->default(0);
                $table->string('state', 16)->default('active');
                $table->string('created_at', 19);
                $table->string('expires_at', 19);
            });
        }

        if (! Schema::hasTable('flow_ai_chats')) {
            Schema::create('flow_ai_chats', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('agent_id');
                $table->longText('messages_json');
                $table->longText('pending_graph_json')->nullable();
                $table->string('updated_at', 19);
                $table->longText('pending_profile_json')->nullable();
                $table->primary(['account_id', 'user_id', 'agent_id']);
            });
        }

        if (! Schema::hasTable('flow_ai_jobs')) {
            Schema::create('flow_ai_jobs', function (Blueprint $table) {
                $table->char('job_id', 32)->primary();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('agent_id');
                $table->string('kind', 12);
                $table->string('status', 16);
                $table->longText('payload_json');
                $table->longText('result_json')->nullable();
                $table->text('error_text')->nullable();
                $table->integer('attempts')->default(0);
                $table->string('created_at', 19);
                $table->string('started_at', 19)->nullable();
                $table->string('completed_at', 19)->nullable();
                $table->index(['account_id', 'user_id', 'agent_id', 'status'], 'ix_flow_ai_jobs_scope');
            });
        }

        if (! Schema::hasTable('voices')) {
            Schema::create('voices', function (Blueprint $table) {
                $table->id();
                $table->string('code', 190)->nullable()->unique();
                $table->string('name', 190)->nullable();
                $table->string('provider', 60)->nullable();
                $table->string('tier', 40)->nullable();
                $table->integer('ready')->default(0);
                $table->string('status', 40)->default('active');
                $table->string('gender', 24)->default('neutral');
                $table->string('dialect', 80)->default('');
                $table->string('source_ref_id', 120)->default('');
                $table->text('sample_path')->nullable();
                $table->text('sample_url')->nullable();
                $table->longText('meta_json')->nullable();
            });
        }

        if (! Schema::hasTable('voice_grants')) {
            Schema::create('voice_grants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('voice_id')->nullable();
                $table->unique(['account_id', 'voice_id']);
            });
        }

        if (! Schema::hasTable('devices')) {
            Schema::create('devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('name', 190)->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('status', 40)->default('offline');
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('device_bindings')) {
            Schema::create('device_bindings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id')->index();
                $table->unsignedBigInteger('agent_id')->index();
                $table->string('installation_id', 160);
                $table->integer('sim_slot');
                $table->string('slot_claim', 128)->nullable()->unique();
                $table->string('device_name', 160);
                $table->string('token_hash', 128)->unique();
                $table->string('status', 24);
                $table->string('created_at', 40);
                $table->string('last_seen_at', 40)->nullable();
                $table->string('revoked_at', 40)->nullable();
                $table->integer('agent_revision')->default(1);
                $table->index(['account_id', 'status', 'last_seen_at'], 'ix_device_bindings_health');
            });
        }

        if (! Schema::hasTable('device_agent_revisions')) {
            Schema::create('device_agent_revisions', function (Blueprint $table) {
                $table->unsignedBigInteger('device_id');
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('agent_id');
                $table->integer('agent_revision');
                $table->string('created_at', 40);
                $table->primary(['device_id', 'agent_revision']);
            });
        }

        if (! Schema::hasTable('device_pairings')) {
            Schema::create('device_pairings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('agent_id');
                $table->string('code_hash', 128)->unique();
                $table->string('status', 24);
                $table->string('expires_at', 40);
                $table->unsignedBigInteger('created_by');
                $table->string('created_at', 40);
                $table->string('redeemed_at', 40)->nullable();
                $table->unsignedBigInteger('device_id')->nullable();
            });
        }

        if (! Schema::hasTable('device_pair_attempts')) {
            Schema::create('device_pair_attempts', function (Blueprint $table) {
                $table->string('bucket_hash', 128)->primary();
                $table->bigInteger('window_epoch');
                $table->integer('attempts');
            });
        }

        if (! Schema::hasTable('calls')) {
            Schema::create('calls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('name', 190)->nullable();
                $table->integer('duration_seconds')->nullable()->default(0);
                $table->string('status', 40)->nullable();
                $table->string('direction', 16)->nullable()->default('inbound');
                $table->integer('is_demo')->default(1);
                $table->string('created_at', 40)->nullable();
                $table->string('external_call_id', 64)->nullable();
                $table->unsignedBigInteger('device_binding_id')->nullable();
                $table->bigInteger('sim_slot')->nullable();
                $table->string('ended_at', 19)->nullable();
                $table->unsignedBigInteger('agent_id')->nullable();
                $table->string('agent_name', 190)->nullable();
                $table->string('caller_gender', 16)->nullable();
                $table->tinyInteger('ai_rating')->nullable();
                $table->string('ai_mood', 32)->nullable();
                $table->string('recording_url', 500)->nullable();
                $table->integer('recording_duration')->nullable();
                $table->unique(['device_binding_id', 'external_call_id'], 'tenant_calls_unique');
                $table->index(['account_id', 'created_at'], 'ix_calls_acct_created');
                $table->index(['account_id', 'status', 'created_at'], 'ix_calls_acct_status_created');
                $table->index(['account_id', 'sim_slot', 'created_at'], 'ix_calls_acct_sim_created');
            });
        }

        if (! Schema::hasTable('tenant_call_events')) {
            Schema::create('tenant_call_events', function (Blueprint $table) {
                $table->unsignedBigInteger('device_binding_id');
                $table->string('call_id', 64);
                $table->integer('sequence');
                $table->string('created_at', 19);
                $table->primary(['device_binding_id', 'call_id', 'sequence']);
            });
        }

        if (! Schema::hasTable('tenant_call_reports')) {
            Schema::create('tenant_call_reports', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('device_binding_id');
                $table->string('call_id', 64);
                $table->text('summary');
                $table->text('customer_notes');
                $table->string('outcome', 32)->default('unknown');
                $table->string('handoff_status', 24)->default('not_requested');
                $table->text('handoff_reason');
                $table->string('source', 24)->default('manual');
                $table->integer('revision')->default(1);
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
                $table->primary(['device_binding_id', 'call_id']);
                $table->index(['account_id', 'outcome', 'updated_at'], 'ix_call_reports_acct');
            });
        }

        if (! Schema::hasTable('tenant_call_transcripts')) {
            Schema::create('tenant_call_transcripts', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('device_binding_id');
                $table->integer('sim_slot');
                $table->string('call_id', 64);
                $table->longText('transcript');
                $table->string('source', 40);
                $table->string('created_at', 40);
                $table->primary(['device_binding_id', 'call_id']);
                $table->index(['account_id', 'site_id', 'device_binding_id', 'sim_slot'], 'ix_transcript_scope');
            });
        }

        if (! Schema::hasTable('voice_call_answer_evidence')) {
            Schema::create('voice_call_answer_evidence', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('device_binding_id');
                $table->integer('sim_slot');
                $table->string('call_id', 64);
                $table->string('observed_at', 19);
                $table->primary(['device_binding_id', 'call_id']);
            });
        }

        if (! Schema::hasTable('voice_call_timeline')) {
            Schema::create('voice_call_timeline', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('device_binding_id');
                $table->integer('sim_slot');
                $table->string('call_id', 64);
                $table->string('event_id', 64);
                $table->string('speaker', 16);
                $table->string('event_type', 20);
                $table->text('content');
                $table->string('origin', 24)->default('device_reported');
                $table->string('occurred_at', 19);
                $table->string('received_at', 19);
                $table->unique(['device_binding_id', 'call_id', 'event_id']);
            });
        }

        if (! Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('name', 190)->nullable();
                $table->string('status', 40)->default('draft');
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('tenant_campaigns')) {
            Schema::create('tenant_campaigns', function (Blueprint $table) {
                $table->string('id', 24)->primary();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('device_binding_id');
                $table->integer('sim_slot');
                $table->string('name', 160);
                $table->string('starts_at', 19);
                $table->string('ends_at', 19);
                $table->text('scenario');
                $table->longText('contact_ids_json');
                $table->string('status', 20);
                $table->string('created_at', 19);
                $table->index(['account_id', 'status']);
            });
        }

        if (! Schema::hasTable('tenant_campaign_attempts')) {
            Schema::create('tenant_campaign_attempts', function (Blueprint $table) {
                $table->char('id', 32)->primary();
                $table->string('campaign_id', 24)->index();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('device_binding_id');
                $table->integer('sim_slot');
                $table->unsignedBigInteger('contact_id');
                $table->string('phone_key', 45);
                $table->string('status', 24);
                $table->bigInteger('leased_epoch');
                $table->bigInteger('verified_epoch')->nullable();
                $table->string('result', 24)->nullable();
                $table->string('finished_at', 19)->nullable();
                $table->unique(['campaign_id', 'contact_id']);
            });
        }

        if (! Schema::hasTable('tenant_campaign_consents')) {
            Schema::create('tenant_campaign_consents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('contact_id');
                $table->string('phone_key', 45);
                $table->string('method', 24);
                $table->char('proof_hash', 64);
                $table->string('created_at', 19);
                $table->unique(['account_id', 'site_id', 'contact_id']);
            });
        }

        if (! Schema::hasTable('dnc_suppressions')) {
            Schema::create('dnc_suppressions', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->string('phone_key', 64);
                $table->string('source', 64);
                $table->string('created_at', 40);
                $table->primary(['account_id', 'phone_key']);
            });
        }

        if (! Schema::hasTable('human_escalation_contacts')) {
            Schema::create('human_escalation_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('name', 140);
                $table->string('phone_e164', 20);
                $table->string('recipient_role', 20);
                $table->string('status', 20)->default('active');
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
                $table->unique(['account_id', 'phone_e164']);
                $table->index(['account_id', 'status'], 'he_contact_owner');
            });
        }

        if (! Schema::hasTable('human_escalation_routes')) {
            Schema::create('human_escalation_routes', function (Blueprint $table) {
                $table->unsignedBigInteger('agent_id')->primary();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->integer('enabled')->default(0);
                $table->string('updated_at', 19);
                $table->integer('delay_seconds')->default(10);
            });
        }

        if (! Schema::hasTable('human_escalation_jobs')) {
            Schema::create('human_escalation_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('device_binding_id');
                $table->integer('sim_slot');
                $table->string('call_id', 64);
                $table->unsignedBigInteger('contact_id');
                $table->bigInteger('due_epoch');
                $table->string('status', 24)->default('pending');
                $table->bigInteger('leased_epoch')->nullable();
                $table->integer('attempt_count')->default(0);
                $table->string('dial_at', 19)->nullable();
                $table->string('finished_at', 19)->nullable();
                $table->string('result', 40)->nullable();
                $table->string('created_at', 19);
                $table->unique(['device_binding_id', 'call_id']);
                $table->index(['device_binding_id', 'status', 'due_epoch'], 'he_job_ready');
            });
        }

        if (! Schema::hasTable('voice_staff_numbers')) {
            Schema::create('voice_staff_numbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('user_id');
                $table->string('phone_e164', 20);
                $table->string('pin_hash', 255);
                $table->text('scopes_json');
                $table->string('status', 20);
                $table->integer('failed_attempts')->default(0);
                $table->string('locked_until', 19)->nullable();
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
                $table->unique(['account_id', 'phone_e164']);
            });
        }

        if (! Schema::hasTable('voice_knowledge_folders')) {
            Schema::create('voice_knowledge_folders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->string('name', 100);
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('voice_knowledge_sources')) {
            Schema::create('voice_knowledge_sources', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->string('name', 150);
                $table->string('kind', 25);
                $table->longText('content');
                $table->string('visibility', 20)->default('public');
                $table->string('status', 18);
                $table->string('sha256', 64);
                $table->string('created_at', 19);
                $table->unsignedBigInteger('folder_id')->default(0);
                $table->integer('version')->default(1);
                $table->string('updated_at', 19)->nullable();
                $table->index(['account_id', 'site_id', 'folder_id', 'status'], 'idx_voice_kb_scope');
            });
        }

        if (! Schema::hasTable('voice_knowledge_revisions')) {
            Schema::create('voice_knowledge_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_id')->index();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->integer('version');
                $table->longText('content');
                $table->string('sha256', 64);
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('voice_kb_agent_scope')) {
            Schema::create('voice_kb_agent_scope', function (Blueprint $table) {
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('agent_id');
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->primary(['source_id', 'agent_id']);
            });
        }

        if (! Schema::hasTable('voice_kb_meta')) {
            Schema::create('voice_kb_meta', function (Blueprint $table) {
                $table->unsignedBigInteger('source_id')->primary();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->string('scope_mode', 10)->default('all');
                $table->integer('priority')->default(100);
                $table->string('expires_at', 19)->nullable();
                $table->string('origin_url', 500)->nullable();
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('voice_kb_search_index')) {
            Schema::create('voice_kb_search_index', function (Blueprint $table) {
                $table->unsignedBigInteger('source_id')->primary();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->char('content_hash', 64);
                $table->longText('terms_json');
                $table->string('indexed_at', 19);
            });
        }

        if (! Schema::hasTable('voice_kb_web_links')) {
            Schema::create('voice_kb_web_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('folder_id')->default(0);
                $table->string('url', 320);
                $table->string('name', 150);
                $table->string('visibility', 10)->default('public');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('status', 20)->default('draft');
                $table->char('last_hash', 64)->nullable();
                $table->string('last_error', 90)->nullable();
                $table->string('last_fetched_at', 19)->nullable();
                $table->string('next_check_at', 19)->nullable();
                $table->integer('interval_hours')->default(24);
                $table->string('created_at', 19);
                $table->unique(['account_id', 'site_id', 'url'], 'uq_voice_kb_weblink');
            });
        }

        if (! Schema::hasTable('voice_data_connectors')) {
            Schema::create('voice_data_connectors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id');
                $table->string('type', 25);
                $table->string('name', 120);
                $table->string('base_url', 500);
                $table->string('read_path', 500);
                $table->string('auth_mode', 25);
                $table->text('secret_enc')->nullable();
                $table->string('status', 20);
                $table->string('last_tested_at', 19)->nullable();
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('voice_connector_public_cache')) {
            Schema::create('voice_connector_public_cache', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('connector_id');
                $table->longText('content');
                $table->char('source_sha256', 64);
                $table->string('retrieved_at', 19);
                $table->string('expires_at', 19);
                $table->string('updated_at', 19);
                $table->primary(['account_id', 'site_id', 'connector_id']);
            });
        }

        if (! Schema::hasTable('voice_connector_public_priority')) {
            Schema::create('voice_connector_public_priority', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('connector_id');
                $table->integer('priority')->default(100);
                $table->string('updated_at', 19);
                $table->primary(['account_id', 'site_id', 'connector_id'], 'pk_voice_conn_prio');
            });
        }

        if (! Schema::hasTable('voice_connector_public_refresh_schedule')) {
            Schema::create('voice_connector_public_refresh_schedule', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('connector_id');
                $table->string('next_attempt_at', 19);
                $table->integer('failures')->default(0);
                $table->string('updated_at', 19);
                $table->primary(['account_id', 'site_id', 'connector_id'], 'pk_voice_conn_sched');
            });
        }

        if (! Schema::hasTable('voice_tool_policies')) {
            Schema::create('voice_tool_policies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('connector_id');
                $table->string('operation', 48);
                $table->string('path', 350)->default('');
                $table->integer('enabled')->default(0);
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
                $table->unique(['account_id', 'site_id', 'connector_id', 'operation'], 'uq_voice_tool_policy');
            });
        }

        if (! Schema::hasTable('voice_tool_requests')) {
            Schema::create('voice_tool_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('connector_id');
                $table->unsignedBigInteger('policy_id');
                $table->string('call_id', 64)->default('');
                $table->string('operation', 48);
                $table->unsignedBigInteger('actor_id');
                $table->string('idempotency_key', 80);
                $table->text('payload_enc');
                $table->char('payload_sha256', 64);
                $table->char('approval_sha256', 64);
                $table->string('status', 24)->default('prepared');
                $table->string('reference_id', 120)->nullable();
                $table->text('result_summary')->nullable();
                $table->string('error_code', 80)->nullable();
                $table->string('prepared_at', 19);
                $table->string('completed_at', 19)->nullable();
                $table->unique(['account_id', 'site_id', 'idempotency_key'], 'uq_voice_tool_req_idem');
            });
        }

        if (! Schema::hasTable('voice_tool_traces')) {
            Schema::create('voice_tool_traces', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('request_id');
                $table->string('call_id', 64)->default('');
                $table->string('operation', 48);
                $table->string('status', 24);
                $table->string('detail', 250);
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('voice_flow_node_tools')) {
            Schema::create('voice_flow_node_tools', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('agent_id');
                $table->string('node_id', 64);
                $table->unsignedBigInteger('policy_id');
                $table->string('updated_at', 19);
                $table->primary(['account_id', 'site_id', 'agent_id', 'node_id'], 'pk_voice_node_tool');
            });
        }

        if (! Schema::hasTable('voice_flow_sessions')) {
            Schema::create('voice_flow_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('agent_id');
                $table->unsignedBigInteger('actor_id')->default(0);
                $table->unsignedBigInteger('device_binding_id')->default(0);
                $table->string('source', 20);
                $table->string('call_id', 64);
                $table->integer('published_revision');
                $table->string('flow_hash', 64);
                $table->longText('state_json');
                $table->integer('step_version')->default(1);
                $table->string('status', 20)->default('active');
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
                $table->unique(['account_id', 'site_id', 'agent_id', 'source', 'call_id'], 'uq_voice_flow_sess');
            });
        }

        if (! Schema::hasTable('voice_flow_traces')) {
            Schema::create('voice_flow_traces', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('agent_id');
                $table->string('node_id', 64);
                $table->string('event_type', 32);
                $table->string('event_detail', 500);
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('wa_connections')) {
            Schema::create('wa_connections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->string('public_id', 64)->unique();
                $table->string('label', 120);
                $table->string('phone_number_id', 100);
                $table->text('access_token_enc');
                $table->text('app_secret_enc');
                $table->string('verify_token_hash', 64);
                $table->string('whmcs_bridge_url', 400)->nullable();
                $table->text('whmcs_bridge_token_enc')->nullable();
                $table->string('whmcs_bridge_verified_at', 19)->nullable();
                $table->string('state', 24)->default('draft');
                $table->string('last_tested_at', 19)->nullable();
                $table->string('last_webhook_at', 19)->nullable();
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
                $table->unsignedBigInteger('ai_agent_id')->nullable();
                $table->integer('ai_enabled')->default(0);
                $table->string('waba_id', 100)->nullable();
                $table->string('meta_app_id', 100)->nullable();
                $table->string('graph_version', 8)->default('v26.0');
                $table->text('webhook_relay_secret_enc')->nullable();
                $table->integer('webhook_relay_enabled')->default(0);
                $table->string('onboarding_method', 24)->default('meta_direct');
                $table->string('source_callback_url', 400)->nullable();
                $table->string('source_verify_hash', 64)->nullable();
                $table->unique(['account_id', 'phone_number_id']);
            });
        }

        if (! Schema::hasTable('wa_conversations')) {
            Schema::create('wa_conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('connection_id');
                $table->string('contact', 32);
                $table->string('contact_name', 150)->nullable();
                $table->string('status', 24)->default('open');
                $table->string('ai_pause_until', 19)->nullable();
                $table->string('human_locked_at', 19)->nullable();
                $table->integer('generation')->default(0);
                $table->string('last_customer_at', 19)->nullable();
                $table->string('last_message_at', 19);
                $table->string('created_at', 19);
                $table->unique(['connection_id', 'contact']);
            });
        }

        if (! Schema::hasTable('wa_messages')) {
            Schema::create('wa_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('connection_id');
                $table->string('provider_message_id', 160)->nullable();
                $table->string('role', 20);
                $table->text('body');
                $table->string('delivery_status', 26);
                $table->string('created_at', 19);
                $table->unique(['connection_id', 'provider_message_id']);
            });
        }

        if (! Schema::hasTable('wa_ai_jobs')) {
            Schema::create('wa_ai_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('connection_id');
                $table->string('source_message_id', 160);
                $table->integer('generation');
                $table->string('status', 22)->default('pending');
                $table->integer('attempts')->default(0);
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
                $table->unique(['connection_id', 'source_message_id']);
            });
        }

        if (! Schema::hasTable('wa_audit')) {
            Schema::create('wa_audit', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->string('event', 55);
                $table->string('detail', 180);
                $table->string('created_at', 19);
            });
        }

        if (! Schema::hasTable('wa_platform_onboarding')) {
            Schema::create('wa_platform_onboarding', function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('app_id', 100)->default('');
                $table->text('app_secret_enc')->nullable();
                $table->string('signup_config_id', 100)->default('');
                $table->string('coexist_config_id', 100)->default('');
                $table->string('graph_version', 8)->default('v26.0');
                $table->string('verify_token_hash', 64)->nullable();
                $table->integer('signup_enabled')->default(0);
                $table->integer('coexist_enabled')->default(0);
                $table->string('updated_at', 19)->nullable();
            });
        }

        if (! Schema::hasTable('outbox')) {
            Schema::create('outbox', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->unsignedBigInteger('integration_id')->nullable();
                $table->string('kind', 120)->nullable();
                $table->longText('payload')->nullable();
                $table->string('status', 40)->default('pending');
                $table->bigInteger('attempts')->default(0);
                $table->string('created_at', 40)->nullable();
            });
        }

        if (! Schema::hasTable('operational_email_jobs')) {
            Schema::create('operational_email_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('purpose', 24);
                $table->char('recipient_hash', 64);
                $table->text('payload_enc')->nullable();
                $table->string('status', 24)->default('pending');
                $table->integer('attempts')->default(0);
                $table->string('retry_at', 19);
                $table->string('expires_at', 19);
                $table->string('last_error', 60)->nullable();
                $table->string('created_at', 19);
                $table->string('updated_at', 19);
            });
        }

        if (! Schema::hasTable('operational_event_receipts')) {
            Schema::create('operational_event_receipts', function (Blueprint $table) {
                $table->unsignedBigInteger('outbox_id')->primary();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('integration_id')->nullable();
                $table->string('kind', 80);
                $table->char('payload_hash', 64);
                $table->string('accepted_at', 19);
            });
        }

        if (! Schema::hasTable('operational_outbox_leases')) {
            Schema::create('operational_outbox_leases', function (Blueprint $table) {
                $table->unsignedBigInteger('outbox_id')->primary();
                $table->string('claimed_at', 19);
            });
        }

        if (! Schema::hasTable('operational_worker_heartbeat')) {
            Schema::create('operational_worker_heartbeat', function (Blueprint $table) {
                $table->string('worker', 70)->primary();
                $table->string('last_run_at', 19);
                $table->string('last_success_at', 19)->nullable();
                $table->bigInteger('scanned')->default(0);
                $table->bigInteger('delivered')->default(0);
                $table->bigInteger('failed')->default(0);
            });
        }

        // Enterprise Post-Call Automations & Webhook Delivery Ledger (new in v0.4.0)
        if (! Schema::hasTable('automation_rules')) {
            Schema::create('automation_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->string('name', 160);
                $table->string('trigger_event', 48)->default('call.ended');
                $table->string('condition_field', 48)->default('outcome');
                $table->string('condition_operator', 24)->default('equals');
                $table->string('condition_value', 160)->default('any');
                $table->string('action_type', 48)->default('webhook');
                $table->string('webhook_url', 500)->nullable();
                $table->text('webhook_secret_enc')->nullable();
                $table->text('whatsapp_template')->nullable();
                $table->string('crm_status', 40)->nullable();
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('trigger_count')->default(0);
                $table->string('last_triggered_at', 19)->nullable();
                $table->timestamps();
                $table->index(['account_id', 'enabled', 'trigger_event'], 'ix_auto_rules_active');
            });
        }

        if (! Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->unsignedBigInteger('automation_rule_id')->nullable()->index();
                $table->string('call_id', 64)->nullable()->index();
                $table->string('event_type', 64);
                $table->string('target_url', 500);
                $table->text('request_headers_json')->nullable();
                $table->longText('payload_json');
                $table->integer('http_status')->nullable();
                $table->text('response_body')->nullable();
                $table->unsignedInteger('attempt_number')->default(1);
                $table->string('status', 24)->default('pending');
                $table->string('error_message', 250)->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->string('created_at', 19);
                $table->index(['account_id', 'status', 'created_at'], 'ix_webhook_logs_acct');
            });
        }

        if (! Schema::hasTable('voice_flow_tool_consumptions')) {
            Schema::create('voice_flow_tool_consumptions', function (Blueprint $table) {
                $table->unsignedBigInteger('request_id')->primary();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('session_id');
                $table->string('node_id', 64);
                $table->string('consumed_at', 19);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'voice_flow_tool_consumptions', 'webhook_logs', 'automation_rules', 'operational_worker_heartbeat', 'operational_outbox_leases',
            'operational_event_receipts', 'operational_email_jobs', 'outbox', 'wa_platform_onboarding',
            'wa_audit', 'wa_ai_jobs', 'wa_messages', 'wa_conversations', 'wa_connections',
            'voice_flow_traces', 'voice_flow_sessions', 'voice_flow_node_tools', 'voice_tool_traces',
            'voice_tool_requests', 'voice_tool_policies', 'voice_connector_public_refresh_schedule',
            'voice_connector_public_priority', 'voice_connector_public_cache', 'voice_data_connectors',
            'voice_kb_web_links', 'voice_kb_search_index', 'voice_kb_meta', 'voice_kb_agent_scope',
            'voice_knowledge_revisions', 'voice_knowledge_sources', 'voice_knowledge_folders',
            'voice_staff_numbers', 'human_escalation_jobs', 'human_escalation_routes',
            'human_escalation_contacts', 'dnc_suppressions', 'tenant_campaign_consents',
            'tenant_campaign_attempts', 'tenant_campaigns', 'campaigns', 'voice_call_timeline',
            'voice_call_answer_evidence', 'tenant_call_transcripts', 'tenant_call_reports',
            'tenant_call_events', 'calls', 'device_pair_attempts', 'device_pairings',
            'device_agent_revisions', 'device_bindings', 'devices', 'voice_grants', 'voices',
            'flow_ai_jobs', 'flow_ai_chats', 'google_voice_live_calls', 'google_voice_key_overrides',
            'ai_relay_entries', 'agent_flow_versions', 'agent_answer_policies', 'audit_events',
            'usage_ledger', 'kashier_refund_events', 'billing_invoice_events', 'billing_invoices',
            'events', 'orders', 'sso_used_tickets', 'external_identity_links', 'integration_facts',
            'integrations', 'api_keys', 'support_policies', 'support_grants', 'staff_invitations',
            'signup_requests', 'password_reset_requests', 'auth_login_throttle', 'admin_mfa',
            'subscription_cancellation_intents', 'reseller_plan_owners', 'recycle_settings',
            'recycle_items', 'platform_feature_settings', 'console_font_preferences',
            'console_theme_palettes', 'brand_identity_settings', 'customer_profiles', 'domains',
            'sites', 'memberships', 'accounts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
