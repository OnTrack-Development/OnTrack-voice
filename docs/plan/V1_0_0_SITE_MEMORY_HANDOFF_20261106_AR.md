# OnTrack Voice — Stable v1.0.0 — Site-only execution memory / handoff
> Snapshot: 2026-11-06, after PR #71 creation. This is a time-bound factual record, **not** proof that all tasks are complete. Refresh GitHub/host state before making new claims.
> User mandate: **work on SaaS website code only**. Never modify, rebuild, publish or re-pair Android / APK / Native / OTA / SIM under this task. User performs manual/live E2E himself; do not block coding progress on requesting his tests.

## Single sources of truth
- Private repo: `OnTrack-Development/OnTrack-voice`; release branch: `release/voice-v1-20261106`.
- Canonical specification: `docs/plan/V1_0_0_PHASE1_RELEASE_20261106_AR.md`.
- The 21 site checklist entries: `docs/plan/V1_0_0_SITE_TODO_20261106_AR.md`.
- Existing site-only publisher workflow: `.github/workflows/voice-v1-site-only-publish.yml`; on successful checks, publisher uses `python tools/publish_v1_one_shot.py site-only` and host queue/receipts.
- Production SaaS: `https://app.ontrackegy.com`; host PHP 8.3. Never edit production data in a CI fixture. Deployment backup/rollback and Android/OTA-pointer preservation mandatory.
- Before execution, inspect `AGENTS.md`, workflow YAML, latest branch HEAD, PRs, GitHub Actions statuses and the TODO. Preserve current operator work; avoid unnecessary parallel workflow jobs.

## Most recent completed coding/merges (site only)
1. **PR #66** merged: public WHMCS catalogue-cache retry failures now increment correctly across SQLite/PDO; 900-second failure backoff; knowledge context denies archived sites/suspended accounts; server Flow rejects fake/unpaired device IDs and revoked bindings. Evidence: `https://github.com/OnTrack-Development/OnTrack-voice/pull/66`, site-only run **42281470780 SUCCESS**.
2. **PR #67** merged: scheduled knowledge URL scans and scheduled public connector refresh deny suspended/inactive tenants; API cache reads fail closed and resume after reactivation. Evidence `https://github.com/OnTrack-Development/OnTrack-voice/pull/67`, site-only run **42282003279 SUCCESS**, merged SHA `1162cb2fe42254330a1b31839b9102f32682b61b`, host reports QA **67 PHP** and published **83 SaaS files**, signed mobile unchanged.
3. **PR #68** merged: Google Live key failover checks active SIM/account and published agent and rejects disabled, cooled or model-mismatched key after mint. `https://github.com/OnTrack-Development/OnTrack-voice/pull/68`.
4. **PR #69** merged: additional failover and call evidence/billing callback idempotency, UTC timestamp checks; `https://github.com/OnTrack-Development/OnTrack-voice/pull/69`. Site-only run **42284952887 SUCCESS**, SHA `9b6b209004130df7182f88867c5c4a3fb59a63e6` (verify host receipt rather than assuming deploy solely from CI conclusion).
5. **PR #70** merged: `VoiceToolGateway.php` now denies creation/resolution/confirmation of WHMCS and booking operations for suspended tenant or archived site; refuses bool or zero IDs for reservations/tickets; tests added to `tools/test_voice_tool_gateway.php`. Merge SHA `8e74c8c8ac53e15b6d92e0161a9c3276f5ef254e`. `https://github.com/OnTrack-Development/OnTrack-voice/pull/70`. Workflow **42285720424 IN_PROGRESS** at last lookup: **do not claim deployed or passed** without fresh result/receipt.
6. **PR #71 OPEN / NOT MERGED** at this snapshot: `VoiceAccess.php` rechecks live enrolled-number status, tenant status, user membership and current scopes for each integration-fact read after PIN authentication; changes `voice_ingest_facts` to validate the entire payload before atomic DB writes, rejects inactive connectors/tenants; tests in `tools/test_voice_access.php`; adds that test to the SaaS-only CI workflow. Head SHA `77d6604d2c9140a9b831115150783ab13e6ca159`. `https://github.com/OnTrack-Development/OnTrack-voice/pull/71`. **Review and run CI before merging; do not mark it shipped.**
7. There was an independent workflow **42286122533 PENDING** for SHA `5a24b7da449c1bd7362a853ad00bc58aaa535850` at snapshot. Do not create redundant runs just to poll. Workflow concurrency group serializes site-only release publication.

## Important distinctions
- MERGED ≠ CI_PASS ≠ HOST_DEPLOYED ≠ AUTH_UI_PASS ≠ LIVE_E2E_PASS. Always record SHA, run ID, result, host receipt, unchanged mobile pointers.
- The last verified published host receipt from previous work is `1162cb2`, run `42282003279`. Later merges need fresh verification.
- **Do not claim all 14 partial entries closed** based on these point fixes. No evidence that all 21 checklist entries meet their original acceptance criteria.
- Previously reported site TODO status: **14 PARTIAL**, **7 OPEN**, **0 DONE by full E2E criteria**. Re-assess based on code and release receipts; never turn a number into an invented accomplishment.
- The **14 PARTIAL** entry IDs to finish in code: `SITE-01,02,03,04,05,06,07,08,10,11,15,16,17,18`.
- Seven further OPEN entries: `SITE-09,12,13,14,19,20,21`. Scope of user request prioritizes the 14 partial items in website code; implement related backend prerequisites where essential.
- E2E using real SIM, real WhatsApp, real Google voice call, real WHMCS or booking API and actual production login is not shown as done; the user personally handles those trials. Mark `CODE_COMPLETE / USER_E2E_PENDING` if coding is done but hands-on acceptance is outstanding.
- Historic phone version `0.3.26` and Android `versionCode=49` are *not* proof of current installed phone version; do not make Android-version assertions or touch application.

## Current technical targets and review cautions
- `saas/console/_core/VoiceToolGateway.php`: verified connector + active tenant/site, owner-approved tool policy, explicit confirmation, idempotency, upstream real reference ID; block destructive WHMCS actions, deny SSRF and bogus success.
- `saas/console/_core/VoiceAccess.php` (PR #71): verify backwards compatibility for every caller of `voice_visible_facts`; enforce fail-closed revocation, no stale privilege after role downgrade; check transactional ingest under nested transactions and correct unique key semantics before merge.
- `saas/console/_core/GoogleVoiceFailover.php`: SAME tenant/pool/SIM/model context, 900-second cooldown, no master key exposure, no callback fanout. Do not claim Google Live WebSocket magically resumes across keys; a fresh session is required.
- `saas/console/_core/VoiceKnowledgeWeb.php` and `VoiceConnectorCache.php`: source priority, review, TTL, tenant/scoped knowledge; never cache private invoices/booking states for the agent.
- `saas/console/_core/VoiceFlowRuntime.php`: immutable published agent snapshot, explicit approve-before-write, pending tool fail-closed; both text scenario and graphical editor are supported, but live tool execution E2E is still pending.
- `tools/test_voice_access.php`, `tools/test_voice_tool_gateway.php`, `tools/test_google_live_failover.php`, `tools/test_voice_flow_runtime.php`: review and expand; ensure workflow really executes relevant tests.
- Watch that UI, reseller registration, licensing, API authorization and subscription checks work consistently across PHP 8.3 + SQLite/MySQL; no token or API key in APK/JS/log.
- Production deployment must remain **site-only**, with `signed_mobile_unchanged=true`, `android_compat=true`, and unchanged `mobile/updates/latest.json` and `mobile/updates/native/latest.json`.

## Operational continuation sequence for Codex with Astra
1. Verify current PR #71 and workflows 42285720424 / 42286122533 and any newer jobs; inspect logs/receipts. Do not merge failing work or duplicate stalled runs.
2. Review PR #71 for full compatibility, address failing CI/regressions, merge if safe, let the existing **site-only** workflow deploy (no Android).
3. Read every one of the 14 PARTIAL TODO entries; implement remaining **website code** and exact regression/CI tests, preserving existing production data and paths. Batch logically, avoid marking tasks done from commit alone.
4. If needed, implement the 7 OPEN site's backend prerequisites afterward, never substitute mock provider success for live provider integration.
5. Release only after site-only CI + host preflight; confirm the exact commit/host receipt and unchanged mobile pointers. Report each entry as `CODE_COMPLETE`, `PARTIAL`, `OPEN`, plus `USER_E2E_PENDING` when applicable.
6. Update both the canonical Stable specification and this memory/TODO with concrete evidence. No invented times, versions, release success, external bookings, phone tests or credentials.

## Permissions & constraints
- Prior owner consent explicitly covers modifications, GitHub commits, PR merges and SaaS-only deploy on OnTrack Voice repo/site. It **excludes any Android/APK/OTA/device changes** during this task. User owns manual/live testing.
- GitHub connector permits repo file CRUD/branches/PRs and workflow/job/receipt inspection, *not unrestricted server SSH*. Deployment happens via existing GitHub Actions Windows self-hosted runner and constrained GitHub queue host worker. Inspect what is available in the current Codex environment; do not assume every connector/session has the same permissions.
- Never expose provider secrets or commit passwords/PINs. Never manipulate real customer financial/ticket/booking operations without explicit consent; test with authorized fixtures.
- If a CI/run fails, report precise failing step; don't silently change scope, bypass tests, or claim deployment.

**Prompt file for Astra/Codex:** `docs/plan/CODEX_ASTRA_CONTINUE_SITE_V1_20261106_AR.md`.
