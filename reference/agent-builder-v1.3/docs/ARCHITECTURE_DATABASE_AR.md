# Architecture + MySQL Data Design

## Technology baseline
Target from verified 2026 Laravel docs: **Laravel 13** requires PHP 8.3–8.5; its official Vue starter kit integrates Inertia / Vue 3 / TypeScript / Tailwind and shadcn-vue. Respect existing repo requirements if present. Database **MySQL 8** (user preference), Redis/Horizon for queues where possible; Laravel events/broadcast for real-time where infrastructure permits. Use Filesystem/S3 compatible encrypted private files for uploaded knowledge, not public uploads.

## Modules / services (modular monolith first)
1. `Identity`: users, email verification, MFA later, tenant membership, signed end-user identity.
2. `Tenancy`: organizations, workspaces, projects, environments, membership/roles.
3. `AgentStudio`: agent revisioning, instructions, persona, published revision pointer and approvals.
4. `ProviderHub`: **PLATFORM-ONLY** provider profiles, connection tests, model tests, encrypted credentials and routing/plan policies. Strict `/control/*` staff access; NO tenant BYOK.
5. `KnowledgeHub`: source files/FAQ, ingestion jobs, chunks, embedding strategy, retrieval permissions.
6. `ConversationCore`: contact identity/verification, conversation, messages, state/assignment, handoff.
7. `SmartActions`: tool definitions, policies, approval requests, idempotent invocation, button rules.
8. `IntegrationHub`: connectors, scoped keys, signed webhooks, retries, events and verification.
9. `Channels`: web chat adapter first, Meta WhatsApp adapter with independent proof, social adapters later.
10. `Operations`: immutable audit events, provider requests, message delivery attempts, usage, aggregated reports.
11. `Commercial`: OnTrack-managed subscriptions, plan entitlements, customer quotas, prepaid/usage ledger, internal upstream AI costs and profitability; actual payment gateway/prices uncommitted.

## Database entities (starter migration map)
All tenant-owned rows include `tenant_id` and appropriate indexes; a secondary `workspace_id`, `project_id`, `agent_id` where relevant. Uniques must include tenant boundaries. Referential ownership validated at application/service layer and, where feasible, composite indexed FKs. UUID/ULID opaque public IDs.

### Identity and tenant
`users(id, name, email, password, locale, email_verified_at, ...)`
`tenants(id, name, slug, industry_nullable, status, created_by)`
`tenant_memberships(id,tenant_id,user_id,role,status)`
`workspaces(id,tenant_id,name,slug,...)`
`workspace_memberships(id,tenant_id,workspace_id,user_id,role,...)`
`projects(id,tenant_id,workspace_id,name,slug,kind,status,...)`
`project_environments(id,tenant_id,project_id,name,api_public_key,...)`
`permission_roles`, `permission_grants` (or robust maintained scoped RBAC implementation).

### Agents & provider models (PLATFORM OWNERSHIP SEPARATE FROM TENANTS)
`agents(id,tenant_id,project_id,name,kind,status,published_revision_id_nullable,...)`
`agent_revisions(id,tenant_id,agent_id,version,system_instructions,policies_json,created_by,reviewed_at,...)`
`platform_ai_provider_profiles(id,type,name,base_url,encrypted_credentials,connection_status,last_tested_at,...)` **NO tenant_id**
`platform_ai_models(id,provider_profile_id,model_id_exact,label,test_status,last_tested_at,...)` **NO tenant_id**
`platform_ai_routing_policies(id,plan_id_nullable,feature,risk_class,writer_model_id,reviewer_model_id,fallback_model_id,config_json,...)` **NO tenant write access**
`platform_ai_provider_tests(id,provider_profile_id,model_id_nullable,latency_ms,classification,status,redacted_summary,...)`
`platform_ai_request_costs(id,request_id,provider_model_id,tenant_id_for_internal_accounting,actual_upstream_cost,...)` **Platform-only view**
`plans(id,name,status,...)`, `plan_entitlements(id,plan_id,feature,quota,...)`, `subscriptions(id,tenant_id,plan_id,status,...)`, `tenant_usage_ledgers(id,tenant_id,request_id_unique,kind,units,...)`
`platform_admin_roles`, `platform_admin_grants`, `platform_ai_audit_events`

### Knowledge
`knowledge_sources(id,tenant_id,workspace_id_nullable,project_id_nullable,agent_id_nullable,kind,scope,status,source_hash,...)`
`knowledge_source_versions(id,tenant_id,knowledge_source_id,version,object_key,sha256,ingestion_status,...)`
`knowledge_chunks(id,tenant_id,source_version_id,chunk_index,text_redacted_or_protected,embedding_ref_nullable,...)`
`ingestion_runs(id,tenant_id,source_version_id,job_id,status,counts_json,error_classification,...)`
For MySQL search start with exact text/fulltext or external vector index under a pluggable adapter; **do not pretend a vector engine exists** if not actually configured.

### CRM + conversations
`contacts(id,tenant_id,display_name,lead_status,...)`
`contact_identities(id,tenant_id,contact_id,channel,external_subject_hash,verified_at_nullable,verification_type_nullable,...)`
`conversations(id,tenant_id,project_id,agent_id,contact_id_nullable,channel,status,human_locked_at_nullable,assigned_user_id_nullable,...)`
`messages(id,tenant_id,conversation_id,role,body,delivery_status,provider_message_id_nullable,...)`
`conversation_events(id,tenant_id,conversation_id,type,payload_redacted,occurred_at,...)`
`handoff_records(id,tenant_id,conversation_id,reason,opened_by,closed_by,opened_at,closed_at,...)`

### Integrations & workflow
`business_connector_profiles(id,tenant_id,project_id,type,base_url,encrypted_credentials,status,...)` — external BUSINESS APIs only; never used to configure AI upstream
`connector_endpoints(id,tenant_id,connector_profile_id,name,method,path,request_schema,response_schema,permission_policy,...)`
`webhook_subscriptions(id,tenant_id,project_id,event_name,endpoint_url,secret_encrypted,status,...)`
`webhook_deliveries(id,tenant_id,webhook_subscription_id,event_id,status,attempts,next_attempt_at,...)`
`tool_invocations(id,tenant_id,conversation_id,connector_endpoint_id,idempotency_key,state,request_hash,response_summary,...)`
`approval_requests(id,tenant_id,tool_invocation_id,status,requested_by,decided_by,expires_at,...)`
`button_rules(id,tenant_id,project_id,agent_id_nullable,action_key,label_ar,label_en_nullable,conditions_json,scope,priority,status,...)`
`button_presentations(id,tenant_id,conversation_id,button_rule_id,state_snapshot_hash,expired_at,...)`
`audit_events(id,tenant_id,actor_type,actor_id,action,resource_type,resource_id,redacted_diff,trace_id,created_at,...)`
`usage_events(id,tenant_id,project_id,kind,units,token_units_internal_nullable,occurred_at,...)` — tenant-safe usage; **no upstream model/secret/cost detail**

## Multi-tenant mandatory rules
- Context from authenticated membership, never from user JSON `tenant_id` or LLM. Validate nested resource ownership on create/update/link.
- Queries, jobs, websockets, caches, file prefixes, webhooks, search and logs must carry the tenant context safely; do not rely only on a global scope.
- Auth token types: **Business integration/project API keys** scoped by workspace/project; **NOT AI upstream provider API keys**; Browser widget publishable key not an API secret; signed short-lived customer assertion from customer's backend with verified subject.
- Unit/feature tests simulate `Tenant A` trying `Tenant B` resource IDs including nested request payload and async job.

## Laravel directory guidance
`app/Domain/{Identity,Tenancy,AgentStudio,ProviderHub,KnowledgeHub,Conversations,SmartActions,Integrations,Channels,Operations}`
`app/Http/Controllers/{Marketing,App,Api}`; `app/Policies`; `app/Jobs`; `app/Events`; `app/Support`.
`resources/js/{components,layouts,pages,composables,lib,types}`; public pages can live in marketing layout with SSR when correctly configured.
`lang/ar/*.php`, `lang/en/*.php` scaffolds; `tests/Feature`, `tests/Unit`, `tests/Browser`.
Prefer contracts/adapters and small services, not monster controllers and static helpers.

## Production environment
Use queue worker/Redis when installed; database queue fallback for local tests and document its limitations. Scheduled tasks for indexing/retry/health with lock and explicit budgets. Backup/migrate transactional where possible. Separate credentials per environment. No production hostname assumed.

## WhatsApp per-tenant channel ownership (v1.2)
Suggested separate entities, owned by tenant/workspace/project rather than by Platform AI ProviderHub:
`whatsapp_connections(id, tenant_id, workspace_id, project_id_nullable, label, public_id_unique, method_enum[embedded_signup|coexistence|manual_cloud_api], phone_e164_nullable, phone_number_id_unique_nullable, waba_id_nullable, meta_app_id_nullable, meta_app_secret_encrypted_nullable, meta_access_token_encrypted_nullable, webhook_verify_token_encrypted_nullable, state, last_credentials_test_at_nullable, last_webhook_verified_at_nullable, last_send_test_at_nullable, last_receive_test_at_nullable, assigned_agent_id_nullable, status_reason_redacted_nullable, created_by, revoked_at_nullable, ...)`
`whatsapp_connection_agents(id,tenant_id,connection_id,agent_id,rule_or_priority,...)` optional for authorized multi-agent routing; prevent duplicate/mismatched project assignments.
`whatsapp_webhook_events(id,tenant_id,connection_id,meta_message_id_or_event_key_unique,signature_verified_at,processing_status,...)`, `whatsapp_message_deliveries(id,tenant_id,connection_id,external_message_id_nullable,idempotency_key_unique,status,attempts,...)`, `whatsapp_template_mappings(...)`, `whatsapp_connection_tests(...)`.
On manual mode, required confidential Meta App Secret is used to validate `X-Hub-Signature-256` using raw body. Callback endpoint per connection is stable (public connection ID is not an authentication secret), challenge verify token belongs to correct app/callback. Never activate before outbound/inbound verification as policy requires. Check phone number uniqueness and ownership across tenants; never leak secret fields in JSON responses. Mobile and Inbox operate on selected connection.
Keep `platform_ai_provider_profiles` independently global: customer can enter **WhatsApp channel secrets** but NEVER upstream AI API keys.

## v1.3 WhatsApp conversation-specific timed AI suppression
Use independent `tenant_automation_settings`/`whatsapp_connections.human_reply_pause_override_minutes`, and `conversations.ai_pause_until_utc`, `last_human_reply_at_utc`, `pause_generation`, `conversation_version`, `human_lock_at_utc`; create `message_outbox` with idempotency and auditable `conversation_events`. Default tenant config 15 minutes; allowed 1..1440. Human manual outgoing intent updates pause in transaction BEFORE sending so old AI jobs cannot race. Gate AI SEND under row lock using current pause, human lock, opt-out, active channel, 24-hour Meta rule, conversation version and user ownership. At timeout, durable Laravel scheduler/delayed job compares `pause_generation` and clears expired timed pause; never lifts permanent human lock. Consider pending inbound catch-up exactly once. All events broadcast scoped by tenant/workspace, no credentials included. See `docs/WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md`.
