# CLAUDE.md — Mandatory Engineering Contract

Project: **OnTrack AI Agent Builder**, brand **OnTrack Development**. Owner decision: **launch audience is every kind of company from day one**. Primary interface **Arabic (ar-EG RTL), light theme**, OnTrack red / white / nearly black, English prepared for later. Product must be an independent Laravel SaaS, not coupled to WHMCS.

## Required reading order
1. **`docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md` — highest-priority owner decision**
2. `README_START_HERE_AR.md`
3. `PROMPT_FOR_CLAUDE_AR.md`
4. `docs/PRODUCT_REQUIREMENTS_AR.md`
4. `docs/DESIGN_SYSTEM_LIGHT_AR.md`
5. `docs/PAGE_MAP_AND_USER_FLOWS_AR.md`
6. `docs/ARCHITECTURE_DATABASE_AR.md`
7. `docs/PROVIDERS_ACTIONS_SECURITY_AR.md`
8. `docs/SMART_BUTTONS_CHANNELS_AR.md`
9. `docs/ARABIC_COPY_AND_CONTENT_AR.md`
10. `docs/DELIVERY_PHASES_TESTS_AR.md` + `docs/EXECUTION_AUTONOMY_NO_LOOP_AR.md`
11. `machine/*`, `design/tokens.css`, `preview/*.html` (updated HTML; old v1.0 screenshots intentionally omitted because they showed tenant provider controls)
12. `source-references/*` for provenance, not to copy a competitor's creative assets.

## Implementation order
- Inspect the *actual* working directory: files, routes, composer.json, composer.lock, package.json, PHP, extension compatibility, existing DB status, tests and Git status. If app exists, preserve its working code, credentials, integrations and migration history. Never delete or overwrite existing functions based on assumptions.
- Produce short gap matrix in `docs/IMPLEMENTATION_STATUS.md`, then **implement**, not only plan. Work vertical slices with functioning backend + real database + UI + tests. If workspace empty, bootstrap a compatible Laravel app.
- Stack default **Laravel 13 on PHP >=8.3**, **MySQL**, **Vue 3 / Inertia / TypeScript / Tailwind**, Redis for production queues/cache and Horizon if available. Existing project version compatibility overrides fresh-bootstrapping preferences; no destructive major upgrade without proof.
- Landing pages: fast SSR/prerender-appropriate SEO strategy with accessible semantic HTML and Arabic metadata. App dashboard: Vue/Inertia, genuinely wired routes and state.
- One source of truth for design tokens and translations; never duplicate layouts for Arabic/English.
- Separate the **platform-only AI Provider Hub (`/control/*`)** from tenant-facing agents. OnTrack exclusively owns all upstream AI keys/models; no tenant BYOK, no tenant AI provider selection. Distinguish business-system connectors that customers can create from AI upstream providers customers cannot access.
- Separate provider adapter contracts, tool runtime, channel adapters, identity verification, tenant boundaries, knowledge indexing and UI. Safe default on write-actions is denied/approval required.

## Design acceptance requirements
- Pixel-disciplined implementation of **our original** reference `preview/landing.html`, `preview/dashboard.html` and `preview/studio.html`, informed by `docs/DESIGN_SYSTEM_LIGHT_AR.md` and responsive adaptations; not a clone of The Agent Builder.
- No generic AI gradient/purple template, random emoji icon libraries, huge empty UI cards, mismatched shadows or RTL broken sidebars. Use clean Lucide-style SVG icons, coherent tables, micro-interactions and a high-quality hero built from *real component patterns*.
- Arabic natural copy, proper `dir="rtl"`, `lang="ar"`, readable Arabic typography, mixed LTR fields (URL/model IDs/token) handled safely. Header/sidebar right, content flows correctly.
- Full states on every actionable screen: loading, empty, validation, success, error, disabled, permission-denied; responsive 360/768/1280/1440 widths and keyboard-accessible controls.
- No invented testimonials, user counts, provider availability, customer quotes, percentages, pricing, financial numbers or functioning integrations.

## Safety and correctness
- Never commit .env, secrets or private real customer records. Encrypt provider keys. Backend-only provider credentials.
- Enforce tenant_id authorization at every read/write; test cross-tenant attacks. Never let an LLM choose tenant/user identity or blindly execute URL/tool JSON.
- Custom URL: HTTPS only, DNS/IP SSRF protections incl redirect and re-resolution, allow-list safe API path, TLS pinned validation, timeout, max payload, no private/loopback host.
- **ONLY platform AI administrators** can access provider profile, model, connection test, routing or upstream cost functionality. Tenant owner and members must be 403 at HTTP + service policy level; tenant UI never includes provider setup.
- Custom provider sequence: **save profile as draft -> test connection -> enter models manually -> test each model -> activate**. Preserve exact model ID including punctuation. A transport HTTP 200 is NOT a successful generation test.
- Tools and dynamic buttons validate user identity, scopes, actual business state, approval, idempotency, audit, normalized errors. Do not fabricate WhatsApp sends or cross-channel handoff.
- UI displays "غير متصل" / "تجريبي" for mock-only integrations; NEVER fake green connected.
- Add tests proving no provider controls or secret/model data is present anywhere in the tenant-facing UI/API, quota enforcement, admin-only routing.
- Phase-specific real tests required; never claim all passed unless actually executed and outputs captured.

## Execution behavior
- Don't repeatedly ask the user for facts already in these docs. Make justified defaults for unresolved non-critical choices, label them as **implementation assumptions**, and continue.
- Iterate Plan (brief) -> Code -> Test -> Browser screenshot/visual compare -> Fix -> Record. Do not stop after UI mockups or one page.
- Up to 2 identical repair attempts without new evidence, then isolate blocker, report and continue independent work. Don't burn API calls in an endless loop.
- Record every phase: implemented files, routes, migrations, tests run/passed/failed/skipped, screenshots if possible, blocking integrations and next immediate step in `docs/IMPLEMENTATION_STATUS.md`.
- Deploy only to an explicitly available safe target; don't silently deploy to OnTrack production domains or modify live WHMCS/Gateway.

## Definitive status terms
`IMPLEMENTED_LOCAL` code exists; `TESTED_LOCAL` has real tests; `VERIFIED_STAGING` uses actual staging evidence; `CONNECTED_LIVE` provider/channel has verified real traffic; `PLANNED` docs only; `MOCK_ONLY` preview/UI without transport. These are not interchangeable.

## Owner's latest non-stop execution directive (2026-10-27)
The owner explicitly requests: **do not pause for a chat reply until all implementable work is complete; if a loop occurs, escape it; if terminal stops, use an alternative available method.** Detailed execution ladder and external blocker exceptions: `docs/DELIVERY_PHASES_TESTS_AR.md` and `PROMPT_FOR_CLAUDE_AR.md`. Treat user statements as authorization to continue **within actual project/tool permissions**, never as permission to defeat security, leak secrets, overwrite production, skip mandatory approval gates, or falsely report completion. If a session must end, write a durable resumable checkpoint first.

## Owner-approved WhatsApp onboarding — v1.2 override
MUST read `docs/META_WHATSAPP_ONBOARDING_AR.md` before building any channel UI/API/DB. The tenant WhatsApp connection MUST have three options: (1) Meta Embedded Signup (2) WhatsApp Business App Coexistence where supported (3) Manual Official Meta Cloud API credentials exactly like the existing OnTrack WhatsApp Notifications setup. This is **tenant-owned WhatsApp CHANNEL configuration** (WABA ID, Phone Number ID, access token, App ID/secret, webhook URL + verify token), NOT permission for a tenant to enter Google/Custom AI provider keys. AI upstream provisioning remains `/control` platform-only. Implement all 3 methods, persist onboarding mode, guarded state machine, signed webhooks, real credential test and tenant isolation, with independent per-channel E2E. Do not claim live Meta success without evidence.

## **MANDATORY v1.3 — Real-time WhatsApp Inbox + automatic 15-minute human pause**
Read `docs/WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md` immediately after `docs/META_WHATSAPP_ONBOARDING_AR.md`. Every connected Cloud API number gets a real-time, light/RTL WhatsApp-style Inbox (messages, supported emoji/stickers/audio/video/files, statuses, notifications, staff tools). **When human tenant staff sends a message in one conversation, suppress AI in that conversation for 15 minutes by default; owner can change per-account pause duration in integer minutes (1..1440) and optionally override per-number. Every human send refreshes countdown; auto-resume at deadline without an open browser; catch up only eligible unanswered inbound once. Timed pause is distinct from permanent human lock, which must never auto-expire.** Enforce at final AI outbox send gate and delayed tasks, not only the UI. All states persist with tenant scope and idempotency. Require tests before claiming completed. v1.2 was only the 3 WhatsApp onboarding methods; v1.3 adds the Inbox and auto-pause. These are specs/mock preview until Laravel code and real Meta evidence exist.
