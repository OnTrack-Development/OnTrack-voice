# Implementation Program, Test Gates & No-Loop Recovery Contract

## Mission
Deliver a working, high-quality Laravel application, **not only page mocks, TODOs, a plan, or a PR with no runtime proof**. Work independently through tasks in dependency order. The owner asked: **do not return to ask for more instructions until you have exhausted all accessible implementation paths**, and **do not get stuck in a terminal/tool or model-output loop**. This does NOT justify falsifying tests, bypassing security, spending unapproved money, or unauthorized production changes.

## Persistent progress contract
Maintain `docs/IMPLEMENTATION_STATUS.md` **inside actual Laravel application**, updated after EACH major capability. Track: completed code paths, file/commit refs, migrations, test commands + actual results, screenshots, deficits, blockers, phase, next action. Add `.claude/state.json` (no secrets) with current phase, last good test command, last error signature, attempt count, next safe action. The resume instruction should be: `Read CLAUDE.md and docs/IMPLEMENTATION_STATUS.md; continue from next unfinished acceptance criterion; do not redo PASS work.`

## Work phases / verifiable exit criteria
### Phase 0 — baseline/environment
- List PHP, Composer, MySQL, Node, PHP extensions; inspect existing Laravel files and git. Ensure DB usable or document genuine infrastructure blocker.
- Create branch or snapshot. Never overwrite active DB/files without reversible backup.
- CI/task commands discovered, package install compatible, application boots.

### Phase 1 — website, RTL design system, auth, shell
- Landing/Home, platform, features, industries (+ 7 sector pages), integrations, pricing, security, developers, docs, contact, login/register/recovery. Real links, semantic metadata, no fake numbers.
- Vue/Inertia components share design tokens; Arabic RTL full; responsive nav/menu; no placeholder routes.
- Actual authenticated dashboard shell, sidebar, workspace selector; sensible empty states.
- `npm run build`, typecheck, lint and browser/screenshot responsive checks where available.

### Phase 2 — tenant projects agents (CRUD & persistence)
- Signup, verified email, create tenant/workspace/project/agent, template/custom, wizard autosave, revision history, agent draft/test/publish lifecycle.
- MySQL migrations, policies scoped to tenant, sample seeders synthetic only.
- User path persists across browser reload, unauthorized A→B tenant attempts rejected.

### Phase 3 — OnTrack Super Admin AI Provider Hub and Tenant Playground
- Strict Platform-only `/control` auth (separate from tenant roles) and Google adapter + Custom OpenAI-compatible with real backend credential handling, profile test and model test, exact model ID, Writer/Reviewer/Fallback selections, saved readback.
- Tenant Playground calls platform-managed routing with plan/quota/ledger; NO tenant provider setup. Playground with real calls ONLY when platform keys + outbound connection are available. Without credentials, accurately label UI as Demo and test offline adapters; don't call false live success.
- Provider failing/timeout/invalid JSON state visible with trace, bounded retry.

### Phase 4 — Knowledge, smart actions, integration APIs
- Working upload/FAQ ingestion pipeline with source scope, indexes or fulltext search actually configured; revision/re-index and accurate status.
- Smart button rule editor; state resolver and server-side revalidation; safe demo state tests; action invocation idempotency and authorization.
- Generic API connectors, signed webhooks, OpenAPI draft importer, minimal framework SDK/adapter docs. Native WP/Laravel/WHMCS packages should be genuinely created/tested if marked complete, or leave explicit separately measurable tasks.

### Phase 5 — operational channels, Inbox, CRM, Reviewer
- Actual web widget JS bundle, published config and verified identity flow; message writes to DB and agent reply delivery where configured. Test end-to-end through a browser page with widget.
- Meta Cloud API adapter setup screen, webhook verification/handlers with mocks; only mark WhatsApp Connected Live after real credentials and send+receive evidence.
- CRM contacts and staff handoff with server lock, reviewer flow, audit/traces, analytics based on true events.

### Phase 6 — production readiness audit
- Tenant data leakage tests and nested ownership; SSRF/rebinding checks; XSS/CSRF/CORS/CSP/session tests, throttle, signed webhook replay protection; queue retry/idempotency; PHP lint; tests and build.
- Visual QA 360,390,768,1024,1440px. Arabic typography, text overflow, alignment, modal z-index, focus/hover/error/loading/empty states.
- Need actual `php artisan test`, `npm run build`, `npm run typecheck` if present; create test commands and recorded output. Security and functional E2E scenarios in `machine/acceptance-tests.json`.
- Actual staging deploy only if authenticated safe target is explicitly available and authorized; otherwise record `STAGING_NOT_CONNECTED`, do not mark ready for live traffic.

## Recovery ladder — MUST automatically execute
1. On failure inspect **full error, exit code and environment**. Classify as tool transport, CLI binary, command, dependency, application code, provider quota, network, credentials or permissions.
2. Fix root cause and retry ONCE; repeat identical fix at most **two times**. Record signature and attempted repair. Exponential backoff only for genuine transient HTTP errors with a bounded limit. Don't hammer provider API.
3. Change method. For hanging shell/terminal: try a **different available integrated tool** (IDE file API/edits, authorized repository connector, native editor, task runner, existing PHP/unit test runner, host panel terminal if actually connected). Prefer file APIs over terminal for file writes when shell unavailable. Test with PHP binary path discovered rather than assuming alias. Distinguish I/O tool hang from app hang.
4. For package-manager/network outage: reuse vendor/node_modules or lock-file/cache if legitimate; implement independent code and tests offline; record dependency build pending. Do not fabricate install.
5. For migration problems: inspect schema and migrations, isolate failing migration in disposable DB, repair forward migration; **never** run `migrate:fresh`, `db:wipe`, force reset on a real DB without explicit authorization.
6. For provider/model failing structured JSON: bounded repair of parsing/schema, small safe prompt, test with deterministic fixtures; after two equivalent failures switch to local validation/offline work and mark provider path blocked. Never spin costly retries forever.
7. For UI browser automation failed: inspect built assets/screenshots with alternative available browser; perform static DOM/CSS checks and record visual verification pending if no browser.
8. If one feature is truly blocked by missing key/payment/external channel: **don't stop entire project**. Mark `BLOCKED_EXTERNAL`, build/test unrelated features, provide admin setup path. Return only after all feasible work attempted. Don't ask user to paste secrets into chat.
9. If session/tool budget approaches limit, save accurate checkpoint immediately and use supported resume mechanism when available; don't claim ability to run forever or continue after the session ends. Provide concise final blocker list only when unavoidable.

## Acceptance checklist (minimum)
- [ ] Public routes reachable with complete Arabic copy; internal links never broken.
- [ ] Branded light UI and demo interactive states; usable desktop/mobile; visual screenshots.
- [ ] Laravel database migrations and auth verified.
- [ ] Tenant B cannot read or mutate Tenant A records, including async/webhooks.
- [ ] Create agent wizard complete + reload/publish states persisted.
- [ ] **Platform-only** provider test connection separated from model test; custom exact ID readback.
- [ ] Customer owner/member prohibited (403) from all platform provider/model/routing endpoints; UI and JSON expose no AI provider URL, key, name, model ID or cost.
- [ ] Customer creates and publishes agent using OnTrack-managed AI without BYOK.
- [ ] Plan entitlement and quota checked before paid generation, idempotent usage metering/limits, no double charging on retries.
- [ ] Knowledge indexed/retrievable with scope; failed ingestion not shown successful.
- [ ] Smart buttons server-derived and change after actual state event; stale click rejected.
- [ ] Web widget genuine with isolated user session; messages appear in Inbox.
- [ ] Staff human lock suppresses queued AI replies until authorized release.
- [ ] Tool/API connector action requires verified identity and approval if risky; duplicate invocation safe.
- [ ] Logs trace failures and token/usage without leaking keys.
- [ ] Tests run and output recorded with failures investigated.
- [ ] Production readiness only when actual deployment and configured external services verified.

## Suggested real commands (discover installation first)
`php artisan about`; `php artisan route:list`; `php artisan migrate:status`; `php artisan test`; `composer validate`; `npm run build`; `npm run typecheck`; `npm run lint` if project defines those scripts; Playwright browser checks if installed. Capture actual status/code/output. Beware commands return success without executing target suite due to wrong cwd.

## Definition of done
Done only if working code, reachable pages, actual persistence, secure API/action flow, tested demos, built assets, self-check outcomes and deployment evidence match the claims. The owner wants continuation until complete, but there is no permission to invent completion when external credentials, providers or hosting are unavailable. Distinguish `READY_FOR_STAGING`, `VERIFIED_STAGING`, `CONNECTED_LIVE`, `PRODUCTION_READY` distinctly.

## Additional mandatory WhatsApp connection acceptance (v1.2)
- [ ] Customer Channels > WhatsApp shows **three** cards: Meta Embedded Signup, Meta WhatsApp Business App Coexistence (eligibility gated), Manual Cloud API.
- [ ] Manual mode stores WABA ID, Phone Number ID, Meta Token, App ID/Secret and verify token encrypted; callback URL fixed to approved HTTPS route, per-connection readback masked only.
- [ ] Bad Meta credentials, mismatched WABA/phone and bad webhook HMAC block activation; no fake connected state.
- [ ] Tenant B cannot see/edit/connect Tenant A's number or access token; unique number ownership enforced.
- [ ] Incoming signed webhook and outgoing confirmed send are observed independently before `CONNECTED_LIVE`; queue dedupe and human takeover suppress double/AI sends.
- [ ] Multiple numbers can be attached to one company and mapped to appropriate agents/projects without collisions; interactive button fallback and templates follow Meta rules.
- [ ] OnTrack AI Provider API keys and model IDs remain inaccessible to tenants despite manual WhatsApp channel setup.

## v1.3 Mandatory WhatsApp Inbox / 15-minute pause acceptance
- [ ] Each verified Cloud API number shows an independent WhatsApp Inbox (multi-number filtering) with realtime WebSockets and reconnect replay, message delivery/read statuses, supported attachments/emoji/stickers/media, search, unread and browser notifications.
- [ ] Owner configures human reply AI pause minutes, default 15 (validate 1..1440); persists after refresh; per-number override optional. No cross-tenant control.
- [ ] Manual staff outgoing message pauses AI instantly in that **single** conversation; another human reply resets the deadline. Other conversations respond as normal.
- [ ] Incoming customer messages while paused still visible and notify staff; AI cannot send any in-flight or stale queued answer in the pause window.
- [ ] Exactly after expiry, backend automatically resumes without open browser and optionally answers only still-unanswered inbound once; does not send unsolicited greetings. Staff human-locked conversations NEVER resume automatically.
- [ ] Stale scheduled resumes and out-of-order staff messages cannot shorten or reset a later pause; duplicate Meta webhook/outbox operations handled idempotently.
- [ ] Browser E2E + Laravel fake clock feature tests + real Meta-inbound/outbound evidence gate; no fake `CONNECTED_LIVE` in previews.
