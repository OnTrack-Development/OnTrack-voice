# Provider Hub + Integrations + Security — Implementation Contract

## A. Fundamental boundary
**AI UPSTREAM PROVIDERS ARE ONTRACK-ONLY, NEVER TENANT-OWNED. Read `docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md` as authoritative. All Google, Custom, Omnira keys, tests, model IDs and routing are managed solely by platform staff. Any old reference to `tenant policy` for selecting a provider means a centralized OnTrack plan policy, never tenant access.**
This is a multi-tenant SaaS serving arbitrary businesses. Do not connect providers directly from the browser. All API credentials and custom tool credentials live encrypted server-side. **Upstream AI API credentials belong solely to OnTrack; tenant-owned connector credentials belong solely to each tenant’s third-party business system.** The model is **untrusted** for identity, authorization, URL routing and tool execution. Never reuse production WHMCS/Omnira credentials found in other projects.

## B. Configurable AI providers — PLATFORM SUPER ADMIN ONLY
### Profiles
Google Gemini native adapter and `OpenAICompatibleAdapter` for Custom Provider are the FIRST verified integrations to implement. Existing Omnira A/B may be optional custom profiles, not hard-coded dependencies. Also design typed adapter registry (`listCapabilities`, `testConnection`, `testModel`, `generate`, `normalizeError`, `health`) without assuming all providers offer model-list endpoints. Use official documented Google auth/endpoint. Do not hard-code deprecated model names as current.

### Exact Custom onboarding contract — `/control/ai/providers` (staff only)
1. New draft: `provider_type=custom`, visible name, HTTPS base URL and credential, protocol (`openai-chat-completions` first), optional custom headers only after validation. NEVER send unrestricted headers or arbitrary URLs supplied via user chat.
2. `Test connection` must initiate an authenticated request through the **configured upstream contract** (e.g. model discovery if available or safe health/auth probe); classify `dns_error`, `tls_error`, `auth_failed`, `rate_limited`, `provider_unreachable`, `invalid_response`, `timeout`, `unsupported_contract`. If endpoint cannot provide safe no-model connectivity, explain that auth cannot be validated until `Test Model` instead of falsely marking verified.
3. Only after successful verified connection **or explicitly documented auth-not-testable state** expose the model entry UI; product owner specifically requests manual names after successful connection, so for a provider lacking any testable connection endpoint offer a clearly labeled safe handshake/test approach rather than bypassing failure. Any relaxation requires clear UX warning and cannot mark green.
4. Store **model_id_exact** (e.g. `claude-fable-5.1[1m]`) byte-for-byte after trim of accidental outer whitespace only (warn if modification needed). Permit brackets, colons, dots, slashes, hyphens and underscores, bounded length; do not auto-convert dot/dash variants. Distinct labels from wire IDs.
5. `Test Model`: real short generation call. Assert text output, appropriate protocol shape, latency, token/usage if available. A 200 response with error JSON or empty content is **FAIL**.
6. `Activate`: only model with successful test may be assigned to role `writer`, `reviewer` or `fallback` by **OnTrack central routing policy (plan/feature/risk)**; never permit a tenant user to assign upstream models to their agents. A verified model is not a guarantee of ongoing availability: retries bounded and visible. Role config readback remains correct after reload.
7. Credentials never echoed to views, logs, error toasts, traces or CI. Rotate/revoke safely, with last four chars of a **server-generated identifier** rather than exposing token suffix if policy forbids.
8. Provider failures (429/402/5xx/connection/invalid JSON/model-not-found) show cause, HTTP category, request trace id and retry recommendation without sending customer secrets.

### Google Gemini
Google-managed credentials and native API endpoint, model listing supported only where API credential and provider contract permit. Require actual per-model generation test. Do not assume Google plan or pricing. Support separate writer and reviewer assignments if tested. All environment-specific Google keys stored encrypted.

### Fallback
OnTrack centrally configures allowed provider fallback, budgets, quotas and cost/risk policy with circuit breakers. Customers see generic service status and their plan entitlements, not provider or model identity. Failed reviewer must block unsafe outbound answers according to platform policy. Distinguish provider outage from authored content failures.

## C. Tool / action design
- Typed action definition: `connector_id`, `endpoint_id`, `verb`, `path_template`, `input_schema`, `response_schema`, `description_ar`, `required_end_user_scope`, `risk_level` (`read`, `confirmed_write`, `privileged_write`), `approval_policy`, `rate_limit`.
- Admin imports OpenAPI spec into **draft** action list. Never auto-activate destructive methods. Support GET/POST/PUT/PATCH/DELETE where business APIs permit, but confirmation/approval for writes, and stronger protections for irreversible operations.
- Restrict base URL to HTTPS public hosts, reject credentials in URL, IP literals, unsafe ports, redirects to internal destinations, DNS rebinding, invalid certs, metadata services and local network/private IPs for cloud-hosted connector calls. Pin resolved public addresses while connecting; validate on every call. Size/time limits and safe error redaction.
- Never allow arbitrary hostnames or runtime JS/PHP execution requested by an LLM. Tool calls are parsed against registered schema, with server-side ownership checks and strict argument allowlists.
- Every invocation requires `tenant`, `project`, `actor`, `verified_end_user_subject`, `resource_owner`, action policy, trace_id, idempotency key for writes and activity audit. Request body does not grant ownership.
- Two-step writes: propose -> validate -> optional customer confirmation -> optional staff approval -> execute -> verify state from source -> audit -> update presentation. If downstream response uncertain, use idempotent re-query, do not repeat action blindly.
- Webhook delivery: HMAC SHA-256 signature with nonce/time, replay window, UUID event id, exponential bounded retry + dead-letter; receipt verification separate from request dispatch. Return 2xx only after durable acceptance.

## D. End-user identity and access
- Web: secure signed short-lived verified user assertion issued from the **integrated project's backend**; use origin allowlists, anti-replay, CORS and HttpOnly session where feasible. Public widget key is not privileged.
- WhatsApp: phone number alone may identify a conversation but is NOT authorization for sensitive ERP/customer records; require separate verification according to connector policy.
- Never merge cross-channel customer identities based solely on similar name or unverified number. Explicit link/verify flow, provenance, unlink ability, tenant isolation.
- Authorization via Policies/Service Gate with nested ownership checks. Tenant derived from staff membership and active workspace, not from incoming caller-supplied tenant_id.
- All writes and permission changes logged with actor, reason, target, old/new redacted values, action outcome, timestamp and trace id. Secure retention controls; scrub PII in logs.

## E. API examples (illustrative contracts, not public endpoints already running)
`POST /api/v1/agents/{id}/messages` authenticated platform/project token; output `conversation_id`, `reply`, `button_presentations`, `trace_id`. 
`POST /api/v1/widget/sessions` restricted publishable widget config + signed identity grant; output short-lived widget session scoped to project/agent/origin.
`POST /api/v1/actions/{actionKey}/invoke` signed end-user session, idempotency key; server validates live state and tool rules.
`POST /api/v1/webhooks/{connector}` validated signature and event dedupe; accepted event changes a backend business state only after signature validation.

## F. Non-negotiable tests
- No direct XSS via model answer / widget messages; sanitize output.
- Mocked 200 but invalid provider response must FAIL Test Model.
- Connection fail means custom manual entry stays unavailable under normal flow.
- Create/edit model ID exact roundtrip, including bracketed IDs and slash-containing IDs — **platform operators only**.
- Tenant owner/member cannot access any platform AI provider/admin/model/routing route, service or JSON response; assert 403 and no secret leakage.
- Plan limit enforced before generation (even in playground); idempotent per-turn usage ledger.
- Customer Business Connector settings must not be confused with AI provider settings or used to proxy unrestricted AI calls.
- SSRF: localhost, link-local, metadata hostname/IP, redirect, rebinding, foreign protocols and headers rejected.
- Cross-tenant direct and nested resource ID attacks rejected for API/tool/knowledge/upload.
- Action replay twice has one business effect; no duplicate charge/booking/email.
- Human locked conversation blocks AI sending even with queued work.

## Approved exception: customer-owned WhatsApp channel credentials
AI provider secrets remain platform-only. A tenant's authorized company administrator **may** manually enter Meta Cloud API *channel* credentials (WABA ID, Phone Number ID, access token, App ID, App Secret, webhook verify token) for a business number the company controls. These are NOT model-provider credentials or customer BYOK. Enforce per-tenant encrypted storage, no API echoes/log leaks, raw-body Meta signature validation, per-app callback verification, token rotation/revocation, duplicate-number protection and explicit send tests. Full schema/workflow in `META_WHATSAPP_ONBOARDING_AR.md`.
