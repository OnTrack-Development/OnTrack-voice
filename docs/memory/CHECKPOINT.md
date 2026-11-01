## 2026-11-01 — Product vertical slice: mandatory admin TOTP 2FA (PR #23; NOT DEPLOYED)

- **Why this slice:** PR #19 dual-SIM/backend/Android CI is green at head `96f69799797d4ed2cb997dbd9e7f67c0fda2ad70` (SaaS `41342996817`, Android `41342996818`, native gate `41342996866`), but release is BLOCKED by missing approved physical Xiaomi two-account SIM pairing/callback E2E and independent credential review. PR #20 Flow Preview and PR #9 SSO also have passing CI but lack required staging/browser/external verification. No unapproved APK/phone call or real payment was initiated.
- **CODED on separate draft PR #23:** Full administrative login 2FA vertical slice: password → pending identity (no account access) → manual Authenticator TOTP enrollment or 6-digit/one-time recovery challenge → verified session. Applies to owner/admin/manager/support/finance roles; existing privileged sessions lacking verified 2FA are gated. Encrypted AES-256-GCM 160-bit TOTP secret, hashed one-time 96-bit recovery codes displayed once, 30-second replay guard, DB-backed five-failure/15-minute lockout, Arabic setup/challenge/recovery UI, additive SQLite/MySQL `schema.v7`. No real secrets stored in Git.
- **TESTS:** PHP RFC6238/vector/database/replay/session/lockout regression + real local PHP HTTP password/setup/CSRF/recovery/dashboard test. CI run `41344121705` SUCCESS on initial runtime; later stronger existing-session gate caused a regression in a CLI DNC fixture (run `41344215159` FAILED), corrected in `tools/dnc_action_fixture.php`; **final post-fix CI pending**, so do not claim final CI_PASS until checked.
- **RELEASE HOLD:** PR #23 remains draft. `HOST_DEPLOYED=NO`, `AUTH_UI_VERIFIED=NO`, `DEVICE_E2E_VERIFIED=NO`, `PAYMENT_E2E_VERIFIED=NO`. Last independently checked host receipt remains `saas-console-41317440838` for old merged PR #18 source `c8a51c2649ff`, not PR #19/#20/#23. Before merging auth code: independent security review, authenticated staging with existing owner login, recovery/rollback proof and SSO interaction review. Current native per-SIM agent voice session and published Flow runtime remain OPEN.

## 2026-11-01 — Product milestone: tenant device pairing control plane (PR #18)

- **Owner course correction:** prioritize building missing features end to end, not an endless series of CI/release-fix microtasks.
- **CODED / MERGED:** PR #18 https://github.com/OnTrack-Development/OnTrack-voice/pull/18, squash main `c8a51c2649ff6f9c46a615223f789cc859a4117d`. Additive schema v5 `device_pairings` and `device_bindings`, hashed one-time 10-minute codes, per-account/site/agent enrollment, distinct 256-bit token per Android installation SIM slot, UNIQUE active slot claim across tenants, immediate owner revoke, account/site archive guard, tenant-scoped Admin/Reseller/User management UI and HTTPS POST pair/heartbeat endpoint.
- **CI_PASS:** final PR SHA `7c802d61841e2f13dda0ac9cd9808797a3dce78e`, PHP 8.3/SQLite SaaS CI run `41317385936` SUCCESS. Post-merge PHP SaaS CI run `41317440863` SUCCESS. Tests `tools/test_tenant_device_pairing.php` cover dual SIM account isolation, no cross-tenant claim, pairing expiry/replay, revoke, archive and suspension.
- **HOST_DEPLOYED VERIFIED:** Publish workflow `41317440838` SUCCESS, matching host Cron receipt `ops/host-link/results/appontrack-server11/saas-console-41317440838.json` status=success, exit=0, finished 2026-11-01 14:42:04 UTC; `source=c8a51c2649ff`, PHP 8.3, 25 files, DB seed already_seeded (9 users), backup=true. **AUTH_UI and DEVICE_E2E remain OPEN.**
- **NOT COMPLETED:** Android app still uses the pilot bearer rather than new per-SIM profile; manual code entry in Android, QR scanning, device-specific agent execution and real two-direction SIM call remain OPEN. This feature's **SaaS control plane** is coded and CI tested, not smartphone E2E. No live calls or payments initiated.
- **Next product milestone (priority):** Android client enrollment and per-slot token/profile storage, authenticated session routing by bound tenant, then Xiaomi and dual-SIM E2E; not unrelated CI tooling.

## 2026-11-01 — Cycle #8 CLOSED: pilot DNC PHP deployed and independently verified (no APK)

- **MERGED:** PR #15 `72a600d47a3c` (publisher), PR #16 `ed2d75e4d720` (explicitly exclude tracked `auth.sha256`), PR #17 `cf2988c7052bddc90df017f7ac9773814f11400b` (fix verified flat host path and per-file rollback). Open PR #9 SSO untouched.
- **CI_PASS:** PR #17 final head workflow `41316597775` SUCCESS; post-merge `41316660497` SUCCESS. PHP 8.3 syntax, DNC/predial tests, packaging, checksum, malicious archive, partial rollback, OTA/auth preservation tests passed.
- **HOST_DEPLOYED VERIFIED:** host Cron `pilot-server-41316660497` receipt SUCCESS exit0 **2026-11-01 14:33:05 UTC**; pinned commit `cf2988c7052bddc90df017f7ac9773814f11400b`, SHA256 `fd5e1bca8febfd38e66b36c32ab98d752cf2ee7feabe3df71f84509ba6b5a8ed`, 20 PHP files installed at `/saas/mobile/`, private backup retained, auth verifier preserved, unrelated OTA files untouched.
- **HOST_READ_ONLY_VERIFIED:** independent Cron job `pilot-verify-20261101143317` SUCCESS exit0 **2026-11-01 14:34:04 UTC**: all 20 installed PHP files lint clean, DNC/predial/campaign guard present, `auth.sha256` and `updates/` preserved.
- **Failed attempts were diagnosed, not concealed:** main workflow `41316076748` failed packaging due tracked auth verifier; main workflow `41316251942` queued but host receipt `pilot-server-41316251942` FAILED (incorrect `/saas/mobile/server` target). Read-only host probe `pilot-path-probe-20261101142825` proved PHP deployed flat in `/saas/mobile/`. Both fixed in PR #16/#17 before successful host publish.
- **DEVICE_E2E: OPEN**; no actual SIM call placed. **TENANT_BOUND_ANDROID: OPEN**; pilot DNC ledger remains single-device, not SaaS tenant-bound. **UI OTA revision collision: OPEN**; PR #14 gate fix does not repair old collision. **SSO PR #9: OPEN**.
- **NEXT:** test no-dial/DNC and lease-to-dial race on real Android device; implement tenant-bound device+dual-SIM pairing and verified re-consent. Tracked auth verifier should be removed/rotated in a planned secure migration, not exposed in release artifact.
- Details: `docs/execution/2026-11-01_PILOT_PHP_HOST_PUBLISH_CYCLE_08_AR.md`.

## 2026-11-01 — Cycle #8 host evidence: PHP-only publisher built; flat host target correction PR #17

- **PR #15 MERGED:** `72a600d47a3c5742d9afe04ba925dac81d365a93`, CI `41316042137` SUCCESS; initial main publish `41316076748` FAILED packaging because tracked `saas/mobile/server/auth.sha256` exists. No host job issued.
- **PR #16 MERGED:** `ed2d75e4d720d81d8ecdd14f0f72c305279b776e`, CI `41316217344` SUCCESS; explicit PHP/.htaccess packer excludes auth. Main workflow `41316251942` SUCCESS = **QUEUED ONLY**. Host receipt `pilot-server-41316251942` **FAILED** exit1 `unsafe_target_directory`.
- **ROOT CAUSE CONFIRMED BY HOST READ-ONLY PROBE:** `pilot-path-probe-20261101142825` SUCCESS; PHP files and `auth.sha256` are in flat `/home/appontrack/public_html/saas/mobile/`, not nonexistent `/saas/mobile/server/`; `updates/` must be preserved.
- **PR #17 CODE CANDIDATE:** change host target to verified flat directory, stage and lint allowlisted PHP files, atomic per-file replace with private backup and rollback on mid-publish error; no APK, OTA, phone calls or payment.
- **HOST_DEPLOYED OPEN** until a matching successful Cron receipt for PR #17. **DEVICE_E2E OPEN**; PR #9 SSO still OPEN and untouched.
- **NEXT:** PR #17 CI, merge if green, verify host receipt; then device DNC E2E and tenant-bound SIM isolation.
- Details: `docs/execution/2026-11-01_PILOT_PHP_HOST_PUBLISH_CYCLE_08_AR.md`.

## 2026-11-01 — Cycle #8 candidate: pinned PHP-only pilot host publish (PR #15)

- **Gap:** PR #13 DNC pre-dial guard was CODED and CI_PASS but pilot PHP server was not published by SaaS console workflow. Native APK release is not proof of mobile PHP deployment.
- **CODE CANDIDATE:** PR #15 `https://github.com/OnTrack-Development/OnTrack-voice/pull/15` adds immutable SHA-pinned pilot PHP-only archive, strict path/member allowlist, private auth.sha256 preservation, host PHP lint, directory backup/rollback, serialized host Cron queue, and no-network regression. It never builds APK, UI OTA or places calls.
- **CI / HOST:** pending final PR CI and post-merge publish receipt. Do NOT claim host deployment merely because workflow queues a job.
- **UNTOUCHED:** PR #9 external SSO remains OPEN, Android DEVICE_E2E and multi-tenant SIM isolation OPEN. UI OTA revision collision remains separately unresolved.
- **Next:** verify CI, merge if green and safe, read host receipt `pilot-server-<run_id>`, update proof; then Android DNC no-dial E2E when device available.
- Details: `docs/execution/2026-11-01_PILOT_PHP_HOST_PUBLISH_CYCLE_08_AR.md`.

## 2026-11-01 — Cycle #7 CLOSED: native publish UI host ACK safety gate merged and CI verified

- **PR #14 MERGED:** `https://github.com/OnTrack-Development/OnTrack-voice/pull/14`, squash main `aed13a68bc3f8cde7c4a12dbde53b8d10ed5a8b4`.
- **CI_PASS:** PR `Native OTA host acknowledgement gate` run `41310627580` SUCCESS (Python syntax, synthetic receipt success/failure/replay/mismatch and native trigger isolation); post-merge main run `41310655875` SUCCESS. No Android build or APK publication was triggered by this release-tool-only merge.
- **ROOT CAUSE VERIFIED:** old Android run `41306044730` GitHub SUCCESS but host `mobile-ota-41306044730` FAILED (`Revision cannot be reused with different content`); `mobile-native-41306044730` SUCCESS published v22 at 12:29:08 UTC. The previous native publisher treated UI queue success as installation proof.
- **FIX:** native queue now waits for matching same-run, SHA-verified successful host UI receipt and exact installed revision + content hash; rejects stale, failed, mismatched or missing proof. New CI tests are isolated from Android APK workflow.
- **HOST_DEPLOYED: N/A** for PR #14 (GitHub release script/workflow only). **DEVICE_E2E: OPEN**; v22 published to host does not prove installed on owner device. **Pilot DNC PHP HOST_DEPLOYED: OPEN**; separate deploy channel needed.
- **NEXT:** resolve UI revision collision in a separately verified signed UI release; implement pinned pilot PHP deploy + receipt without APK build; then tenant-bound Android DNC and device E2E.

## 2026-11-01 — Cycle #7: Native publish must wait for verified UI host receipt (candidate, CI pending)

- **Observed release mismatch (verified):** GitHub Android run `41306044730` = SUCCESS including publish queue steps; actual host `mobile-ota-41306044730` = FAILED, exit 1 (`Revision cannot be reused with different content`), yet host `mobile-native-41306044730` = SUCCESS, published versionCode 22, SHA256 `ff60a4d49dd86294a8fb1c1c7254ae2ccc587f19ad21af504e1271d4e02b548b`, **2026-11-01 12:29:08 UTC**. This was an APK release artifact publication, NOT proof of installation on user's phone.
- **CODE CANDIDATE:** `tools/queue_mobile_native.py` now requires the same run's matching, hash-verified successful UI host receipt and exact revision/hash match before native queue. `tools/test_mobile_release_gate.py` covers failure, mismatch, stale, corrupt, successful receipts. Dedicated `mobile-release-gate.yml` CI checks this without APK build; `android-audio-proof.yml` no longer triggers on release queue tool edits. This change does not itself deploy mobile PHP or update a device.
- **PENDING:** PR CI and merge, no claim of verified release gate until success. Separate pilot PHP release/host receipt and real Android pre-dial DNC E2E remain OPEN. UI OTA revision collision needs an independent versioning fix.
- **Details:** `docs/execution/2026-11-01_NATIVE_UI_RELEASE_GATE_CYCLE_07_AR.md`.

## 2026-11-01 — Hourly cycle #6 PR #13 merged: pilot DNC pre-dial gate, release separation

- **MERGED / CODED:** PR #13 `https://github.com/OnTrack-Development/OnTrack-voice/pull/13`, main squash `c303880e94b74263a4f5afd3bc1a3a790fb6720a`. Durable pilot-only DNC private file, legacy opt-out migration, no unverified unban, fail-closed corrupt store, opt-out enforcement at campaign import/preview/arm/claim and lock-protected pre-dial verification. The existing Android dialer already calls server verify; APK unchanged.
- **CI_PASS:** final PR head `aadf09bc402c`: pilot PHP/ledger workflow `41306010120` = success; SaaS PHP console workflow `41306010195` = success. Post-merge pilot workflow `41306044753` = success. Tests synthetic only, no SIM calls.
- **HOST_DEPLOYED NOT VERIFIED:** no automatic pilot server PHP deployment workflow or host receipt for PR #13. The SaaS console release receipt `saas-console-41301462639` is **older**, not proof of this mobile PHP release. Device E2E and tenant-bound DNC still OPEN.
- **RELEASE SAFETY FINDING:** old `android-audio-proof.yml` triggered full signed Android APK build/OTA publication on **any** `saas/mobile/server/**` change. Main push triggered Android run `41306044730` (in progress at last check), although this PR changes PHP only. Fixed future trigger in main commit `eef9327049101c2a6798ba985a127fc9156d480e` by removing server-only path from native APK workflow; `voice-p1-p2-smoke.yml` remains server CI. The already-started run is **not cancelled** by that commit; verify outcome and any host queue receipts before claiming native release or safety.
- **NEXT:** verify Android run `41306044730` did not cause unintended release; implement safe pinned PHP pilot server host deployment and receipt, then tenant-bound Android DNC + real device E2E and verified re-consent. Keep PR #9 SSO open until independent staging security review.

## 2026-11-01 — Hourly cycle #6: pilot Android pre-dial DNC fail-closed (PR #13)

- **Issue:** single-device pilot `pilot_workspace.php` previously allowed `pilot_dnc(id,false)` to lift opt-outs without fresh verified consent, and the opt-out existed only on a mutable contact. `pilot_campaign_verify_claim` did not hold the opt-out lock. This pilot state is distinct from the SaaS multi-tenant ledger in PR #12.
- **CODE CANDIDATE:** PR #13 `https://github.com/OnTrack-Development/OnTrack-voice/pull/13` adds durable private pilot `dnc.json`, backfills legacy flags, rejects corrupt store, protects import/preview/arm/claim, locks pre-dial verify, expires old leases after 30s, and rejects unsafe opt-out reversal in API/UI. Regression tests include leased opt-out, contact deletion/reimport, campaign arm rejection and corrupt DNC. Android `CampaignDialer.kt` already calls `claim` and `verify` before `placeCall`; no APK modification in this PR.
- **CI EVIDENCE (initial code head):** `Voice P1 P2 backend regression` run `41305909639` = success; `OnTrack Voice SaaS PHP Console` run `41305909534` = success on head `316bdddcdbec`. Subsequent documentation changes require checking the final PR HEAD workflows before merge.
- **HOST_DEPLOYED: NOT VERIFIED** for pilot files. The existing SaaS Console publish workflow does **not** publish `saas/mobile/server`. No phone/server release claimed. Last confirmed SaaS Console host receipt remains `saas-console-41301462639` (PR #12). Device E2E and actual pre-dial blocking on the installed phone remain OPEN.
- **BLOCKERS:** tenant-bound Android pairing/dual SIM isolation, verified re-consent removal, device E2E and a safe pinned host deployment route for pilot PHP. The post-verify to `placeCall` race still exists; do not claim zero-race guarantee. PR #9 signed SSO is open and untouched.
- **Details:** `docs/execution/2026-11-01_PILOT_DNC_PREDIAL_CYCLE_06_AR.md`. **Next:** confirm final CI, merge only after pass; deploy pilot server only via authorized verified host release with receipt; then test no-dial against a consented synthetic number on actual device.

## 2026-11-01 — Hourly cycle #5: durable DNC ledger merged and deployed (PR #12)

- **Issue:** PR #11 ingestion guard depended on `contacts.dnc=1`; direct deletion/import cleanup could erase the only opt-out evidence. Account-scoped DNC was not indexed or durable.
- **FIX MERGED:** PR #12 `https://github.com/OnTrack-Development/OnTrack-voice/pull/12` squash main `d6a9a883153fe73993ec7f3be144be08d8149a38`. Added `saas/console/_core/DncLedger.php` with SQLite/MySQL unique `(account_id,phone_key)`, idempotent 250-row legacy backfill, and `Seed.php` migration during existing-host deploy. `DncGuard.php` reads durable ledger first, heals legacy contacts, persists explicit opt-outs; signed booking `booking_no_consent` saved inside event transaction after pre-transaction schema init. No SSO PR #9 runtime files modified.
- **CI_PASS:** PR HEAD `1e981d4208b2bf7e8c26bc7aff02136c21e6f6df` workflow `41301426937` = success. PHP 8.3 / SQLite smoke includes migration, phone normalization, replay/idempotency, account isolation, contact deletion survival, legacy self-heal, invalid phone, seeder re-entry, HTTP API and signed booking. Post-merge main CI `41301462574` = success.
- **HOST_DEPLOYED VERIFIED:** publish workflow `41301462639` success; host Cron receipt `ops/host-link/results/appontrack-server11/saas-console-41301462639.json` = success, exit 0, finished **2026-11-01 11:21:04 UTC**, source `d6a9a883153f`, **23 files**, PHP 8.3, `seed=already_seeded` (9 users), backup=true. Seeder ran the DNC migration on the existing DB without error. This is deployment proof, not device/SSO/browser proof.
- **OPEN:** Android/campaign pre-dial DNC and consent gate, verified opt-in reversal and retention policy, real authenticated browser, MySQL live E2E, device call E2E. PR #9 SSO still open; no forced merge.
- **Details:** `docs/execution/2026-11-01_DNC_LEDGER_CYCLE_05_AR.md`. **Next:** wire a fail-closed pre-dial DNC check into real Android/campaign dispatch, test tenant isolation and opt-out during a queued campaign without making real calls.

## 2026-11-01 — Hourly cycle #4: DNC opt-out protection merged (PR #11)

- **Issue:** a contact marked `dnc=1` could be reintroduced as `dnc=0` through consented API lead, signed booking, or manual contact form; edit/delete could silently erase the only opt-out record. This violates owner Q13's immediate "do not call again" safety requirement.
- **FIX MERGED**: PR #11 `https://github.com/OnTrack-Development/OnTrack-voice/pull/11`, main commit `49ecf6b9c212c9a7aaac8f926d8dc07466ebe677`. Added `saas/console/_core/DncGuard.php`, changed API, signed hooks, Actions, contacts UI. A DNC in any site of one account applies to new contacts in all that account's sites, including Egyptian +20/00/01 formatting. Other tenants' DNC never leaks. Routine edit/phone change/delete cannot silently clear DNC until a verified opt-in/retention path is built.
- **CI_PASS**: PR head `879228a21435275af2ab66b5bb4e738b45454010`, PHP 8.3 / SQLite + real HTTP API + signed webhook + authenticated-form-handler CLI subprocess regressions: workflow `41297247472` = **success**. Synthetic data only; no calls/payments.
- **HOST_DEPLOYED VERIFIED**: post-merge main CI `41297275347` **success**; publish workflow `41297275398` **success**; host Cron receipt `ops/host-link/results/appontrack-server11/saas-console-41297275398.json` = **success**, exit_code **0**, finished **2026-11-01 10:24:04 UTC**, deployed source `49ecf6b9c212`, **22 PHP/UI files**, PHP 8.3, seed `already_seeded` (9 users), backup=true. This proves pinned runtime deployed, NOT authenticated UI/Android pre-dial E2E.
- **OPEN**: no durable normalized suppression ledger, explicit consent-based DNC removal and privacy/retention flow, Android/campaign pre-dial gate, real authenticated browser, device E2E. This is an ingestion guard only. Existing PR #9 signed SSO remains open and untouched in runtime; do not auto-merge it without staging security evidence.
- **Details**: `docs/execution/2026-11-01_DNC_INGESTION_CYCLE_04_AR.md`. **Next**: verify host receipt for PR #11; implement durable tenant DNC suppression + opt-in reversal/retention and pre-dial enforcement, or continue independent verified registration/payment task after PR #9 staging review.

## 2026-11-01 — Hourly cycle #1 PR #8 merged, CI and host deployment verified

- **Issue fixed in code:** previously `add_employee` could immediately attach an existing user to another tenant solely from a typed email, or create an active unverified user and membership. It now creates a 7-day pending invitation without granting membership. Only the authenticated email-matching existing user can explicitly accept it; CSRF, expiry, replay, cross-identity, support-session and tenant checks apply. Owners/admins can cancel scoped pending invitations. SQLite/MySQL additive `schema.v4` migration and staff/invitation UI are included.
- **PR #8 merged**: `https://github.com/OnTrack-Development/OnTrack-voice/pull/8`, main commit `a35c636a2bf42ceb97cf696a48d1cf12e0b2a7e0`. PR CI `41284341013` = **success**, post-merge PHP console `41284375129` = **success**, publish workflow `41284374964` = **success** (bundle queued to host worker).
- **HOST_DEPLOYED confirmed for #8**: Cron receipt `ops/host-link/results/appontrack-server11/saas-console-41284374964.json` = `success`, exit_code 0, finished **2026-11-01 07:21:03 UTC**, source `a35c636a2bf4`, **21 files**, PHP 8.3, seed `already_seeded` / 9 users, backup=true. This proves the new runtime tree installed; does not prove authenticated invite acceptance in a real browser or live SSO.
- **Known limits:** unregistered email invitations stay pending; verified signup/email delivery and external SSO/account claim are still OPEN. No authenticated browser UI or Android/device/financial E2E was run. Do not claim complete identity solution.
- **Next:** verify Cron receipt and authenticated invitation UI, then proceed to verified registration/SSO subject binding as a separate testable slice.

## 2026-11-01 — Hourly cycle #1: verified staff invitations, consent gate (candidate pending CI)

- **Previous release proof now verified:** PR #7 main `ecbe44fb590a63f344bc869b01038373c1ef6e22`; CI `41280246046` PASS; publish workflow `41280245756` PASS; host Cron receipt `ops/host-link/results/appontrack-server11/saas-console-41280245756.json` = **success**, exit_code 0, finished **2026-11-01 06:19:04 UTC**, source `ecbe44fb590a`, 20 files, PHP 8.3, DB `already_seeded` 9 users, backup true. This is `HOST_DEPLOYED` for that release only, not payment/SSO/Android E2E.
- **Security bug identified:** `Actions.php::add_employee` previously attached an existing user to a tenant by typed email immediately, or created an active user with temporary password and immediate membership, without the addressee's consent. That violates approved Q02 and enables unwanted tenant access.
- **CODE CANDIDATE in `fix/verified-staff-invites-20261101`:** additive schema.v4 `staff_invitations` for SQLite/MySQL, pending invitation with 7-day expiry and capacity checks, owner/admin cancellation, authenticated invitee-only acceptance through CSRF-protected `/invitations` route, user-facing pending banner and staff table. No user/membership is created on invite; no support impersonation acceptance/invite. Regression tests cover email collision, cross-identity, replay, expiry, cancel, tenant isolation and no user creation.
- **UNVERIFIED until PR CI/merge:** no claim of live invitation UI or schema.v4 on host. Invites for unregistered addresses remain pending until independently verified registration/email delivery exists. Full WHMCS/Laravel signed SSO, account claim, gateway settlement, Android multi-tenant and 2FA remain OPEN.
- **Next gate:** PR CI PHP 8.3/SQLite, review code and merge only if success and safe; verify host receipt and authenticated UI separately; next independent slice: verified registration + SSO external_subject identity mapping.

## 2026-11-01 — Signed owner decisions PR #7: CI passed, publish queued, hourly automation enabled

- Owner requirements Q01–Q24 accepted and written in `docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md`. Earlier six decisions unchanged. `AGENTS.md`, `CLAUDE.md`, plan and hourly runbook updated in main by merged PR **#7**, commit `ecbe44fb590a63f344bc869b01038373c1ef6e22`.
- **CI_PASS** confirmed: PR PHP/SQLite regression workflow `41280217523` = success; post-merge PHP console workflow `41280246046` = success.
- **PUBLISH_WORKFLOW_PASS / HOST_DEPLOYED NOT YET VERIFIED**: deployment workflow `41280245756` success, including 'Queue safe deployment on existing host Cron worker'; receipt `ops/host-link/results/appontrack-server11/saas-console-41280245756.json` not found when checked. No proof yet the host applied commit; do not call that deployed.
- **FIX CODED:** `OrderClaim.php` and signed webhook now mark paid order `paid_pending_claim` without authenticating or joining an existing OnTrack login by email alone. Console smoke regression asserts no unintended membership, no subscription activation, one outbox event, idempotent same reference and conflict for different reference.
- **BLOCKED FOR COMPLETE CUSTOMER PURCHASE:** signed SSO/account claim, official gateway settlement verification, manual/Instapay/wallet review workflow, safe activation and external WHMCS/Laravel acknowledgments remain OPEN. The paid order is intentionally not attached to user; support team must not claim it is automatically accessible.
- **HOURLY_AUTOMATION_ENABLED**: scheduled recurring hourly task `تطوير OnTrack Voice تلقائيًا` created successfully; each run must follow `docs/execution/AUTONOMOUS_HOURLY_RUNBOOK_AR.md`, open small code PRs, report tests, and avoid unsafe production financial/telephony side effects.
- **Next:** verify host Cron receipt for release and build customer claim/SSO verified identity pipeline + sandbox payment order E2E.

## 2026-11-01 — Owner Q01–Q24 approved, billing identity boundary candidate, hourly execution runbook

- **SPEC_APPROVED**: All Q01–Q24 answered; Q07 broad Android goal with staged validation and later iOS, Q13 custom per-campaign outcomes with DNC guard delegated and resolved. Earlier 6 owner decisions preserved without change. Master: `docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md`. Owner requires complete launch including registration/invites, 2FA, per-company support/trash policy, dual SIM accounts, real agent flow, Google Drive KB, configurable multi-currency/monthly/yearly/trial plans, Kashier plus manual/Instapay/wallet review, reseller-collected vs OnTrack-collected payment, WHMCS/Laravel customer purchase and secure SSO with no preexisting OnTrack account.
- **CODE CANDIDATE (NOT YET VERIFIED)**: `saas/console/_core/OrderClaim.php` records a signed externally-asserted payment as `paid_pending_claim` and adds one pending outbox event. `saas/console/hooks/index.php` no longer auto-links memberships to existing users based on email from signed payment hooks; `tools/test_voice_saas_console.php` checks email collision, replay/idempotency, conflict and absence of unauthorized access/subscriptions.
- **Critical incomplete gate**: a verified payment still needs independently verified gateway settlement for Kashier, signed WHMCS/Laravel identity claim/SSO, then a separate idempotent service activation. No success on these paths is claimed; until claim E2E exists, such orders stay paid but unclaimed. Production use must plan for this change.
- **AUTOMATION CONTRACT PREPARED**: `docs/execution/AUTONOMOUS_HOURLY_RUNBOOK_AR.md`; active scheduler not confirmed until `Automations.create` returns a Task ID.
- **Next immediate step**: PR CI on branch `feat/owner-decisions-billing-contract-20261101`, review CI, merge only after success, obtain deploy receipt if any. Distinguish `CI_PASS` from `HOST_DEPLOYED`; `AUTH_UI_VERIFIED`, `PAYMENT_E2E_VERIFIED` and `DEVICE_E2E_VERIFIED` remain OPEN.

## 2026-11-01 — SaaS production rework landed (this OVERRIDES earlier "structure pending" notes)

- Owner rejected separated phone pilot, text-only Agent Flow Builder, forced read-only support, unrecoverable integration keys, tiny Google voice list and missing edit/archive management.
- PR #4 squash main `ed93e858dd26f3526543e7ec9ab4d63376543b62`, staging CI `41273266211` PASS, production publish `41273309602` PASS; Cron `saas-console-41273309602` success **2026-11-01 04:31:04 UTC**, 19 files and existing DB preserved. Verified HTTPS login=200, CSS=200, FlowGraph JS=200 and private PHP=403, SQLite schema.v2/vault columns.
- PR #5 squash main `4f262741632a5f270a9e24c45f2295f935a55b40`, CI `41273623128` PASS, publishing run `41273654041` PASS; Cron `saas-console-41273654041` success **2026-11-01 04:36:04 UTC**, existing accounts untouched, protected backup retained.
- Source now has node/edge draggable canvas builder in `saas/console/assets/js/flow-graph.js` with branching labels/inspector, server-validated graph persistence; account edit/suspend, sites/agents/campaigns/staff/contact CRUD with audited archiving, basic admin plan editing, Google Gemini Live 30-voice catalog with optional live Voices API, availability/grant controls.
- Configurable support session: `support_policies` global/per-reseller `full` vs `read_only`; owners can navigate account with short context and return; actual human actor audited. Full mode still disallows re-viewing stored credentials or direct pilot write from support context.
- New integration/API credentials encrypted at rest with AES-256-GCM private key; verified owner password required to reveal later, rotate revokes old key. Older hash-only tokens **cannot be recovered**, must rotate. Disabled records hidden from active list but retained for audit.
- Platform admin console `devices` can now inspect real existing single-device phone pilot heartbeat and edit/publish its private policy `/home/appontrack/.ontrack-mobile/settings.json` with revision bump (no fake state); customers/resellers **are NOT linked to that shared bearer** until tenant-bound Android pairing is implemented. This was intentionally not misrepresented as finished multi-tenant mobile integration.
- Signed Laravel/WHMCS/generic webhooks allow scoped customer booking lead creation (with consent/DNC and event dedup), while plans still require authenticated external `payment.confirmed` and money workflow. Manual approvals now require a proof reference. Real independent Kashier settlement verification, actual external WHMCS/Laravel notifications/outbox worker, reseller-domain TLS, hardware telephone E2E, and graph execution in live AI voice are **OPEN**.
- Earlier sentence "structure awaiting approval" in old sections is obsolete; owner approved and production `/saas/console/` exists. No password or API credentials should ever be written to AGENTS/CHECKPOINT/GitHub. New admin password still should be verified by user; previous generated demo values are not valid.
- User requires **code execution** when asking "نفّذ"; images only when expressly requested. Preserve brand red/black/white Light/Dark. Do not call the integrated system production-complete until hardware + payment + tenant pairing E2E are proven.


## 2026-11-01 — SaaS console HTTPS publication proof; demo credential setup outstanding

- PHP SaaS console was merged to `main` PR #2; UI/role schema/seeds/signed hooks and tests are in `saas/console`. Release pipeline `.github/workflows/voice-saas-publish.yml` and fixed worker installer `tools/deploy_saas_console.py`.
- GitHub Actions release run **41271032956** succeeded. Host Cron receipt `saas-console-41271032956`: **success**, 16 files, PHP 8.3, demo DB already seeded (9 users/7 accounts/5 sites from prior receipt), and preserved backup. Deployment path `/home/appontrack/public_html/saas/console/`.
- Initial 404 fixed: host worker umask 077 created nested directories 0700; patched installer to chmod public directories 0755, applied host repair `saas-fix-dir-modes-20261101035423` SUCCESS.
- Independently verified through HTTPS from host with receipt `saas-final-http-20261101035619`: `https://app.ontrackegy.com/saas/console/login` HTTP **200** containing login form; `assets/css/app.css` HTTP **200**; `assets/js/app.js` HTTP **200**; direct access to `_core/Bootstrap.php` and `database/Seed.php` returns **403**. Public URL confirmed as reachable from host. Browser/web tool from external environment did not directly fetch URL.
- **Outstanding important issue:** seed uses randomized passwords not written to GitHub; there is no confirmed known admin password for login. Attempts to perform password reset through remote job were blocked by tool safety checks; do **not** claim a valid admin credential or test authenticated portal until an authorized secure reset/setup is completed.
- Real reseller DNS+TLS provisioning, payments, voice agent telephony integration and E2E cross-tenant HTTP tests are not yet proven. Treat delivered portal as isolated demo preview, not production-complete SaaS.

## 2026-11-01 — SaaS PHP console staging source built, host deployment NOT verified

- Owner authorized full console implementation and host launch. Source is in `voice-saas-console-staging` / PR #2, initially commit `594fbd86977ce105d0d8212f17fbad4caa6afeac`, plus CI workflow commit `1f5d92711292de6c0f89496d12be42576d0da0a1`.
- 18 new files: `saas/console` PHP entry, Bootstrap/Auth/Actions, Views, signed inbound WHMCS/Laravel hook, scoped API v1, SQLite demo/MySQL schema, DemoSeeder, red/black/white responsive theme, agent flow editor, Apache deny/routing, tests.
- Staging GitHub Actions run `41270145833`: **success** (PHP syntax, JS syntax, DB seeds, login hashes and tenant boundary smoke tests).
- Current **code readiness is staged preview**; production `main` and live `/saas/console` NOT updated. Admin demo credentials are seeded for preview source, not a live account until runtime deployment. Do not claim live login, external payment verification, WHMCS notifications, custom-domain TLS or actual SIM operation. Complete production review and deployment only through permitted path; never bypass a blocked repository publishing operation.
- Existing Android SIM pilot and OTA were not changed in this task. Real external integrations, DNS/TLS activation, outbox delivery, independent Kashier verification and tenant-device binding remain separate E2E gates.

## 2026-11-01 — SaaS structure extended, still awaiting approval

Added addendum `docs/plan/VOICE_SAAS_RESELLER_DOMAINS_AGENTS_ACCESS_AR.md` to previously proposed `docs/plan/VOICE_SAAS_CONSOLE_STRUCTURE_AR.md`. Requested: Admin support access across authorized platform customer accounts; Reseller support access only to its own customers (no password access); verified custom reseller domain DNS+TLS to same central PHP files without clone; Android APK download links; Admin internal user workspace; plan-bounded multiple AI agents on distinct sites with independent Agent Builder Flows and Knowledge Bases; named creatable/revocable site-scoped API keys; Laravel and WHMCS registration/booking/plan/order integrations and Kashier; **online subscription provision only after authoritative signed payment notification/verified WHMCS InvoicePaid (never browser success redirect)**; manual payments stay pending until authorized Approval; subscription provisioning idempotent, outbound notices through origin Laravel Notifications or WHMCS template via signed Webhooks. Includes full proposed directory tree, scenarios and acceptance tests. Status `STRUCTURE_PROPOSED / APPROVAL_PENDING`. No console runtime files or production deployment in this step.

## 2026-11-01 — Voice SaaS Admin/Reseller/User dashboard structure PROPOSED, not approved

User requested ONLY structure first for approval, not code/deployment yet. Detailed structure is at docs/plan/VOICE_SAAS_CONSOLE_STRUCTURE_AR.md: PHP 8.3 + MySQL no Laravel runtime, /saas/console separate from existing pilot and older Laravel reference; three dashboards; platform/reseller/user staff RBAC + tenant security, branding inherited by reseller customer, own admin reseller/user plans and monthly call allowances, select permitted/ready voices, login + protected synthetic demo, integration API/Webhooks for reseller website registrations/bookings automatically creating pending account/customer/lead, Kashier sandbox, role/tenant E2E gates. After explicit approval: implement code + host deployment. Current status STRUCTURE_PROPOSED; NO NEW CONSOLE CODE or HOST DEPLOY performed for this request.

## 2026-11-01 — Native floating control v0.3.1 + UI OTA18 RELEASE VERIFIED ON HOST

- User request: remove oversized navy/burgundy floating card; use small round **green for connected+enabled** / **red otherwise** overlay, tap expands 2x2 genuine icon buttons and small explanatory text; ON, OFF, ringing, connected AI and human-handoff states must all be correct. Button label explicitly **استلام من الإيجنت**; speaker control active only after answer, no speculative mute control.
- CODE shipped in single atomic main commit **`b6c26a4f83423b54efc3858feb2657b221179707`**: `CallOverlay.kt` replaced, `OverlayUiModel.kt` with unit tests, service notification state transitions and takeover wording aligned, in-app HTML mini-tile actions aligned, updated docs.
- CI GitHub Actions **41259385082**: `build`, `publish_ota`, `publish_native` all **success**, including overlay state JUnit tests, emulator WebView, original cert signing tests.
- UI-only **OTA revision18**: independent no-APK workflow **41259385027** SUCCESS, on-host receipt `mobile-ota-41259385027` success at 2026-11-01 01:03:03Z. HTML sha256 `2def03557e8aba14c14cdc1ff6be1e7a4eb7fbef49fb3a9c871c1ad07c137a2a`; signed manifest covers native versionCode20 through22. This can update UI call-action tiles in existing Android 0.2.9 without APK.
- Native signed Android **0.3.1, versionCode22**: host receipt `mobile-native-41259385082` success at 2026-11-01 01:13:08Z, APK sha256 `ff60a4d49dd86294a8fb1c1c7254ae2ccc587f19ad21af504e1271d4e02b548b`, size 52,534,849 bytes; OS approval required on phone to replace **native** Android overlay. **UI-only OTA cannot change `WindowManager.TYPE_APPLICATION_OVERLAY`**.
- Independent read-only host check `verify-native22-ui18-20261101011413` **success exit0** at **2026-11-01 01:15:03Z**: verified on-host version22 APK file size and exact sha256, signed UI revision18 file sha256, public-read file permissions; read no secrets. This is host integrity verification, **NOT an actual GET from public internet nor Xiaomi on-device visual E2E**.
- DEVICE SIGNOFF OPEN: installation on Xiaomi, circle toggling on both statuses, tap/drag, ringing/answered/real SIM handoff and speaker behavior in real call, Android notification icon states, light/dark on phone. Do not declare production UI signoff before those tests.

## 2026-11-01 — floating native call-control redesign (CODE candidate; E2E OPEN)

- User rejected the oversized blue/navy outlined native `CallOverlay`: requested compact round red/green closed button, expanded small square icon actions with small captions; both agent ON/OFF; correct state-aware buttons from ringing through answered AI and human takeover; explicit "استلام من الإيجنت" labeling, matching OnTrack red/black/white in light/dark.
- New `CallOverlay.kt` and `OverlayUiModel.kt` implement a red/green state orb and four actual actions: enable/disable agent, take current call from AI, speaker route, open app. Action availability: idle/early ring disables pickup+speaker; answered AI enables both; human takeover disables duplicate pickup while speaker stays available. Notification and dashboard labels aligned; Kotlin `OverlayUiModelTest` covers all states.
- **Important:** Android system overlay is native `WindowManager.TYPE_APPLICATION_OVERLAY`; it cannot be changed by HTML-only OTA. The in-app button cards can publish via UI OTA **18** compatible with installed native v20–v22; the floating native orb requires an OS-approved update to **Android 0.3.1, versionCode22**. No silent APK installation. Both CI/host/device must be proven separately.
- Status: new code being released, not Xiaomi-tested at this checkpoint. Do not declare actual call handoff or device rendering PASS without E2E.

## 2026-11-01 — SIGNED UI-ONLY OTA17 PUBLISHED (no APK)

- Confirmed root cause of "0.2.9 still shows old UI": signed OTA16 set `min_native=21`; installed owner's Android `0.2.9` has `versionCode20`. Signed UI OTA rejected it as incompatible. Android code20 already supports `MobileOta.check()` on startup and `checkUpdates` bridge.
- Fixed in commit `4fbca56393fd6e3dee64424754f80d10339ebcc3`: `saas/mobile/ota/release.json` now signed UI revision **17**, `min_native=20,max_native=21`; red/black/white HTML UI plus swipe-down refresh (`checkUpdates`, no `installNativeUpdate`), and safe v20 theme persistence via same-origin cookie, v21 via native prefs.
- NEW independent UI-only GitHub Actions workflow `.github/workflows/mobile-ui-ota.yml` uses pinned existing signing key, signs HTML, verifies JS and exact native20 compatibility and queues current host worker; UI changes no longer depend on Gradle/APK build. Successful GitHub workflow run **41257907163**; host result `mobile-ota-41257907163` **success**, `exit_code=0`, `2026-11-01T00:43:03.810209Z`, output revision17, HTML SHA256 `594a0e8ed9b10e203e5ed3b1c41d74f212a8d2af743f40684a141d1226204a61`, `root_api_unchanged=true`. This confirms **host publication**, not proof of display on user's device.
- Action on existing 0.2.9: opening app checks signed UI automatically; once OTA17 installed in WebView, top pull-down triggers another signed UI refresh. No reinstall required for design. If app was left open during publication, close/reopen to run startup check.
- Outstanding separate native requirement: original call-log matching permission logic is native-only and cannot be delivered with HTML OTA. Do not present that as an excuse to demand APK for **visual UI**. Android device/UI experience still needs on-phone visual confirmation.
- A generic `web` open of public OTA manifest was inaccessible due web tool; local container DNS was also unavailable. Do not claim independent public HTTPS GET verification; existing privileged host deployment receipt and signed publisher/CI success are the verified proofs.

## 2026-11-01 — UI OTA-only delivery correction, not an APK

- User clarified **no APK download/installation for UI identity/visual changes**. Old app native v0.2.9 `versionCode20` already supports signed HTML OTA and automatic startup check. The red/black/white UI earlier in OTA16 was incorrectly gated behind `min_native=21`, so couldn't load on native20; this explains user's report that v0.2.9 still showed the previous appearance. No proof of OTA16 live deployment was available at the moment of diagnosis.
- Fix queued in repository: **OTA revision17 compatible with nativeCode20..21**, pull-down at top sends existing `checkUpdates` and applies signed new HTML automatically, theme stored first-party in signed local UI for code20. No native install needed for visual refresh. Separate UI-only signer/publisher workflow under `.github/workflows/mobile-ui-ota.yml`. CI + host report + phone appearance must be verified separately.
- **Native-only** contact identity capability, if Android READ_CALL_LOG is required for fresh caller identity, cannot be fixed by OTA alone; keep its acceptance gate open. No claim of instant permission grant/true name matching.
- Don't ask owner to reinstall APK for UI design. This is an existing implementation path, not a proposed new platform.

## 2026-11-01 — VERIFIED latest checkpoint (supersedes older P1/P2 release status)

## 2026-11-01 — User-requested red/black/white code implementation, single push (NOT automatically E2E closed)

- Execution rule: implement in **code** whenever owner says «نفّذ»؛ create images only when explicitly asked for an image. Single atomic push requested for this batch; do not split main commits.
- Android user UI: unified OTA/new-bundled HTML with OnTrack red/black/white, icon-only persisted light/dark selection, bottom-tab navigation, Arabic minutes/seconds and contact-name permission path. Admin and reseller layouts postponed until tenant-scoped SaaS RBAC.
- Identity integration approach: new optional READ_CALL_LOG + READ_CONTACTS permission, late unique Android CallLog match with phone lookup; backend accepts authenticated `identified` enrichment after terminal call without reopening or changing recorded duration. Never infer caller without unique log match or claim device permission has been granted.
- Release planned v0.3.0 versionCode21 / OTA16. CI, host publication and actual device names/durations/visual checks must be verified individually after push. Real Xiaomi test still open.
- Previous release v0.2.9/OTA15 built and both publication jobs succeeded on run 41253328620; separate from this new batch.


- User requested complete group memory persisted in GitHub. Canonical handoff with product scope, exclusions, infrastructure, release and safety is at the top of `AGENTS.md` (commit `e3165c74`). Read that and the current repository before asking for anything.
- HOST INCIDENT ROOT CAUSE confirmed by owner cPanel screenshots: Cron `* * * * * /bin/python3 /home/appontrack/ontrack-voice-link/worker.py` **was running**. `STOP` absent; GitHub public HTTP 200; `last-run.log` said `Host Link: ValueError`; local state active None/completed 71. A **queued job expired** before worker validation, and installed worker kept raising `ValueError` each minute. Earlier assertion that Cron had stopped was incorrect.
- RECOVERY verified: safely replaced expired unexecuted queue with probe `recover-cron-20261101000753`, report **success** at `2026-11-01T00:08:03Z` on `ontrack-host-link`. A source-only change `ops/host-link/worker.py` (commit `8dd3677`) makes expired unstarted jobs emit receipt; **not yet confirmed deployed to installed worker**.
- RELEASE now verified at CI/host transport level: GitHub Actions run `41253328620` attempt 2 had `build`, `publish_ota`, `publish_native` all **success**; host report `mobile-native-41253328620` **success** at `2026-11-01T00:11:08Z`. Android **v0.2.9 nativeCode20 / OTA15** with unified modern tabbed UI and without legacy automatic rollback. No owner-device install proof or real SIM E2E yet. Publishing an APK is not installing it.
- UNRESOLVED: P2 `p2-selected-target-api-web-20261031` has no receipt and expired before recovery; whether code was applied on the host must be verified by exact live hashes before retry. P1/P2 device tests, contact matching and actual SIM dial E2E unverified. P3/P4/P5/P6 remain according to actual evidence gates, not code-only claims.
- NEXT: verify live UI/APK serving and host PHP file hashes via a safe new host-link job now that Cron is healthy; then finish device-side verification and feature gap checklist. Do not request redundant manual terminal or re-creating already working Cron. No credentials in repo.

## 2026-10-31 P1/P2 batch — implementation progress; NOT E2E signed off

- OWNER SCOPE: Call History + Contacts CSV/XLSX/TXT/DNC + automatic SIM campaigns in Android and web. Weak-network audio resilience remains explicitly deferred to phase 2.
- CODE: signed Android **0.2.8 candidate** (versionCode 19, OTA14) adds user-approved CALL_PHONE permission and opt-in/out, serialized SIM lease dialer with no automatic retry of ambiguous calls, just-in-time DNC/campaign verification, per-campaign selected contacts, campaign scenario variables, native confirmation dialogs, stored contacts DNC UI, call history filters. Incoming caller identity remains unverified/unknown; no fabricated names, durations or disposition.
- BACKEND: `pilot_workspace.php` arms approved campaigns, restricts to schedule + selected consented contacts, exclusivity via leased attempts, 60-second spacing, terminal idempotency, cancel/pause/resume, DNC recheck and per-number attempt reports. `campaigns_api.php` supports arm/claim/verify/finish. Web `ops.php` supports selected contact targets, arm/pause/resume/cancel and attempt report.
- CI: backend regression run `41237242100` succeeded. Latest Android build `41237650852` remained in_progress at observation; **NO v19 signed build/publish proof yet**. Do not treat old 0.2.7 run as evidence for new code.
- HOST: deployed `pilot_workspace.php` target-selection revision in job `p2-selected-targets-workspace-20261031` success+lint. Attempted `campaigns_api.php` and `ops.php` update queued as `p2-selected-target-api-web-20261031`, with **NO report observed** after queue; do not replace/duplicate the job or assume deploy. Previous host API versions remain conservative. Host worker might be offline; verify through exact queue/result before retries.
- RELEASE STATUS: previous Android v18/OTA13 verified; **v19/OTA14 not yet published**. Real outbound SIM E2E on authorized Xiaomi and a consented test line, Android in-app update, per-number answer/disposition accuracy and UX tests remain NOT DONE. Never mark VC-P1/P2 DONE from CI alone.
- Safety: Automatic dialing disabled by default; requires owner explicit toggle + CALL_PHONE runtime grant + arm + current Cairo schedule + eligible contact + DNC verification. Unknown attempt outcome blocks automatic redial.
- Next safe continuation: verify CI run 41237650852, inspect host worker result `p2-selected-target-api-web-20261031`, complete pending deploy only if safe, then publish verified signed v19/OTA14 (never reuse v18 immutable version), check HTTPS and phone E2E. Do not expose credentials or real client phone data.

## 31 أكتوبر 2026 — Android 0.2.7 / OTA13 + campaigns and file import (verified publish)

- CODE: Android native `MobileLinkActivity.kt` now exposes authenticated `listCampaigns`, `createCampaign` (draft-only, explicit confirmation), `contactsFilePick`, and `contactsFileUpload` via bounded native bridge. Signed app UI OTA13 adds campaign scheduling/list and CSV/TXT/XLSX file picker. Uses numeric `nativeCode>=18` to gate native-only operations; preserves old OTA compatibility.
- SERVER: `campaigns_api.php`, `contacts_file_api.php`, `pilot_workspace.php`, `pilot_import_file.php`, `ops.php`, `call_ledger.php`, `calls_feed.php`, `call_event.php` installed by host jobs. Existing divergent `calls.php` preserved and PHP lint passed. All 6 routes returned expected unauthenticated HTTP 401/403 in `mobile-api-auth-final-20261031`.
- CI: signed Android 0.2.7 versionCode18/OTA13 build PASS including Android WebView instrumentation in run `41222513565` (artifact `13050443933`). Publication separated due initial runner missing Actions read permission for fallback artifact lookup; release workflow run `41223480502` publish_ota=success, publish_native=success. Failed earlier host tasks diagnosed and repaired (literal newline bootstrap, stale APK member filename). No overwrite of unknown queued job.
- HOST: OTA13 `mobile-ota-41223480502` success SHA256 `03df15b612f9ed7a5b67988d3c7b1e2c7c332b34e1d8858f2170b59bc4ceca19`; Native18 `mobile-native-41223480502` success SHA256 `2df544f427dc7268056e3d2a367ff7b54e2603fddff35e3895f7273a49285ff3` (52,477,505 bytes).
- LIVE HTTP: `mobile-027-live-verify-20261031` success: signed `updates/latest.json` and OTA HTML HTTP200 revision13 34,400 bytes, signed `native/latest.json` and APK HTTP200 versionCode18 52,477,505 bytes and exact hashes.
- OPEN DEVICE/E2E: On-phone install and use of Android 0.2.7, real calls/feed, native imported contacts/file, campaign create/list, all still require real device tests; campaign SIM executor not implemented; automated call reporting/quality, full AYO TTS runtime, Kashier TEST and plan entitlement E2E remain OPEN. This work does not close P1/P2/P4/P5/P6 signoff. PHASE-2-NETWORK-STABILITY stays deferred.

## 31 أكتوبر 2026 — OTA12 / Android 0.2.6 RELEASE VERIFIED (host only)

- Root issue: `publish_ota` failed due to 16,384-byte host job limit. Fixed by XZ signed-bundle packing and compressed Python deploy source; emitted queue length 15,265 bytes. Backward-compatible zlib decoder maintains previous tests.
- Android 0.2.6 signed artifact from previously **fully successful CI build** run `41217573945` / artifact `13048863982` (including emulator WebView). Independently republished via release workflow run `41219916371` (publish_ota=SUCCESS, publish_native=SUCCESS), without claiming later flaky WebView rebuild failures as passes.
- HOST worker: `mobile-ota-41219916371` SUCCESS/exit0 revision12 sha256 `2960e56f9789aec0fa4243f5b3c475229fc6ff2d1b642815aa9817063863eb52`; `mobile-native-41219916371` SUCCESS/exit0 versionCode17 sha256 `3c88e1e89038930a4138669f19de34f9ce746acfdf4be2aab10f6fde0a2a53de`.
- HTTPS verified from the host in job `mobile-release-http-verify-41219916371`: both `saas/mobile/updates/latest.json` and `saas/mobile/updates/native/latest.json` return HTTP200 with matching revision/version and hashes; no credentials printed.
- Still OPEN: installation of 0.2.6 on actual Xiaomi; complete P1/P2/P5/P6 E2E; later Android emulator WebView test flakiness seen in runs `41218421220` and `41219173959` (test failure with emulator input ANR). Do not label these runs successful. 

## 31 أكتوبر 2026 — الحالة التنفيذية للدفعة الموحدة P1/P2/P5/P6

- CODE: ee839f6e plans/Kashier/Premium catalog؛ fb8bb837 Android UI 0.2.6/OTA12 + import + campaign drafts؛ dd5138a1 in-app call history؛ 02c6dd89 server-side plan entitlements؛ 45a0a0b4 payment-order-bound refunds.
- AYO: Safe local inspection of all 3 files via torch.load(weights_only=True) PASS؛ Resemble AI Chatterbox Multilingual Conditionals field structure proven. Source adapter saas/voice/premium_tts_cli.py commit 71a664bb، voice catalog ready=false. PT binary sources NOT uploaded to GitHub, no synthesized audio or TTS deployment.
- CI: 41172270951 cancelled by newer native push; 41172480358 is the final 0.2.6 OTA12 workflow. Check actual job states and host reports before claiming Android publish or phone success. First PHP CI checks had succeeded.
- HOST: New pilot PHP routes ops.php, pilot_workspace.php, pilot_import_file.php, contacts_import.php, calls_feed.php not yet verified deployed on app.ontrackegy.com. Do not claim accessible/live.
- E2E OPEN: phone contact import, real call history log with name/number, outbound SIM campaign executor and schedule, Kashier TEST payment, Laravel payment/entitlement rollout, real Chatterbox Arabic TTS generation.
- PHASE-2-NETWORK-STABILITY remains deferred only for weak 4G/5G voice resilience; P1/P2/P5/P6 remain required in current batch.

## 31 أكتوبر 2026 — دفعة واحدة: المكالمات والاتصالات والباقات وPremium/Kashier

- أحدث قرار: أنجز مهام P1-P2-P5-P6 كلها كدفعة تطوير واحدة، مع UI للتطبيق أقرب إلى OnTrack الحقيقي، دون تقطيع المتطلبات إلى ردود منفصلة.
- مدخلات صوتية جديدة: AYO-calm.pt (SHA256 10b536280f44431af3a42b2b3ae68ab0e60a44ca116c417b9a9ba9a12bbb8105)، AYO-excited.pt (77b67c9d95dbf2ea8b50fa88778c1d83eb917a0e9d8eeb3be14f055f9b9e38cf)، AYO-sleepy.pt (fef549851711566631866708f135cf3d512b55c3b98766431bd54742250aeb1e). PyTorch presets وليس WAV/MP3؛ كتالوج نصي فقط في الريبو والبائنري الأصلي موجود في مرفقات المحادثة/حزمة تسليم، **لم يُرفَع البائنري إلى GitHub** بعد.
- تم رفع commit ee839f6e573e7b8021d4198e97df868fce124380: Laravel scaffold لسعر باقة قابل للإعداد، entitlement check، Kashier sessions/Webhook HMAC، وإدارة خيارات UI. يحتاج Laravel dependency/runtime/migrations/payment sandbox E2E وsecurity audit؛ لا دفع حقيقي.
- تم رفع commit fb8bb837ebbb09352279969402a6e63ae66fbdd7: Android UI 0.2.6 وOTA12، اختيار جهة اتصال أو استعراض الأرقام بإذن صريح ثم معاينة وتأكيد قبل رفعها، خدمة API محمية للتواصل، إدارة Contacts/DNC وحملات مسودات بتوقيت القاهرة، استيراد لصق/CSV/TXT/XLSX، وتحسين فلاتر Call History. **لا Outbound campaign executor فعلي** إلى الآن؛ الاتصال التلقائي محفوظ كمتطلب OPEN.
- CI run 41172270951 بدأت بعد commit fb8bb8؛ لا تدعِ نجاحها أو نشر النسخة حتى تقرير وظائف Actions والعامل. Host server PHP الجديد لم يُثبت نشره. أبقِ PHASE-2-NETWORK-STABILITY مؤجلة حتى الانتهاء من الديمو.


## 31 أكتوبر 2026 — توجيه جديد: تأجيل فحص ثبات بيانات الهاتف للمرحلة الثانية

- **قرار المالك مُلزِم:** لا تستهلك وقت الديمو حاليًا في اختبارات/إصلاح التقطيع والصمت عند ضعف 4G/5G. وجود المشكلة مثبت سابقًا ولم يجر إغلاقها، لكنها تحولت إلى `PHASE-2-NETWORK-STABILITY` (DEFERRED / NOT DONE). لا تخلط هذه الحالة مع نجاح تثبيت v0.2.4 → v0.2.5 الذي ثبت على هاتف Xiaomi.
- **التذكير:** عند انتهاء وظائف الديمو وبدء مراجعة الإقفال `VC-P5-05`، اعرض تلقائيًا ضمن المحادثة مراجعة `NET-02-01..05` واطلب تقرير اختبار/قرار المرحلة الثانية قبل SaaS أو أي ادعاء بإنتاج جاهز. المرجع: `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`.
- **المهمة التالية الآن:** `VC-P1-01` ثم `VC-P1-02` — التأكد من وصول أحداث أول مكالمة من Android، تسجيل `call_id`/حالة/مدة بدون ازدواج، وإكمال سجل المكالمات في التطبيق والموقع. ثم استيراد جهات الاتصال `VC-P1-03/04`، وبعدها جدول الحملات `VC-P2`.
- متطلبات سلامة المكالمة الأساسية/أمن ADB/حماية البيانات لا تؤجّل؛ المؤجل هو **تقييم جودة الشبكة الضعيفة وتعافيها** فقط. لا تعد فتح الاختبار في كل رسالة قبل اكتمال الديمو.

## 31 أكتوبر 2026 — نجاح تثبيت v0.2.4 ثم OTA v0.2.5 على Xiaomi (تقرير المستخدم)

- المالك حمّل `v0.2.4` من الرابط الرسمي المباشر، وثبّته بنجاح. بعده استخدم **التحديث داخل التطبيق** للانتقال إلى `v0.2.5` ونجح التثبيت دون `There was a problem parsing the package`. هذه شهادة DEVICE فعلية لمسار الترقية (ليست قراءة عن بُعد لإصدار الجهاز).
- GitHub Actions run `41170090334` final SUCCESS لكل jobs: `build`, `publish_ota`, `publish_native`. تقارير host-worker: `mobile-ota-41170090334` success revision11؛ `mobile-native-41170090334` success native16 SHA256 `24508b9ac60e7edca7a1c87bd4748078867f0c7d3e0e4fe544fbc89163ccb9c9`.
- تحديث `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`: تأشير ✅ على خطوات التثبيت المثبتة وخطوة CI والنشر؛ ترك P0-01 الإجمالي مفتوحًا لحالات edge/إلغاء/أثناء المكالمة وقراءة الإصدار، وP0-03/05 OPEN لحين اختبار الصوت فعليًا على شبكة ضعيفة.
- **الاختبار التالي:** مكالمة قصيرة برقم تجريبي بموافقة الطرف الآخر، Wi-Fi مغلق وبيانات الهاتف فعالة، اختبار صوت الطرفين ثم تدهور الشبكة وعودتها والتأكد من استمرار كلام الإيجنت وعدم الرجوع للتحية أو تجمده، ومن ثم فحص calls.php للتأكد من وجود سجل وحيد. سجل نتيجة المستخدم قبل أي تعديل صوت إضافي.

## 31 أكتوبر 2026 — تقرير عطل CI 0.2.5 وتعديل فحص Android

- GitHub Actions run `41169445578` فشل **فقط** في `:app:connectedDebugAndroidTest` خلال `MobileUiInstrumentedTest.localMainDocumentRendersAndNativeBridgeInitializes`؛ XML: `java.lang.AssertionError: WebView JS callback` عند `latch.await(5,TimeUnit.SECONDS)` line28 والاختبار line46. Logcat يبين `Choreographer: Skipped 369 frames!` أثناء cold boot، على محاكي API34. Kotlin/unit وبناء APK قبله نجح. لذلك `publish_ota` و`publish_native` skipped، ولم يُنشر v16.
- عدل اختبار WebView في commit `3c0d73d0da10`: مهلة JS 15 ثانية والفحص الكلي 45 ثانية، دون تعديل assertions المخصصة لتحميل الواجهة/الـnative bridge، CI rerun `41170090334`؛ لا ادعاء نجاح حتى نتيجة نهائية ونشر موثق.

## 31 أكتوبر 2026 — رد اختبار المستخدم: فشل Parsing مستمر وصوت 4G/5G غير مستقر

- فشل Native Update على Xiaomi برسالة `There was a problem parsing the package` **مستمر**. التحميل السابق لم يعد يتكرر؛ هذا جزء فقط من OTA ثبت نجاحه على الجهاز.
- الإيجنت على بيانات الهاتف يعمل ويرد إذا كانت الشبكة قوية، وعند ضعفها يحدث تقطيع أو صمت. **VOICE_STABILITY_OPEN** وليس إثباتًا لقبول P0-03.
- تقرير العامل `mobile-024-live-security-20261031` مؤكد `success/exit 0`: manifest UI/native HTTP 200؛ APK v15 SHA256 `2046fb2e46dffbcb01108c882f1ec6e9486001d0c69bec1ce084d573dd07f8c7` وطول 52424257 وMIME `application/vnd.android.package-archive`. لا يثبت قابلية APK للتركيب على جهاز المستخدم.
- إصلاح برمجي جديد تحت commit `e595c8818a8e59f0e9b57736e2157bdce2ef6635` Android 0.2.5/code16 + UI revision11: تضييق backlog للإرسال، إسقاط صوت استقبال قديم بدل إغلاق الجلسة، sessionResumption + retry محدود، وإظهار `PackageInstaller.EXTRA_STATUS_MESSAGE` عند الفشل. **لم يثبت نجاح الجهاز أو التحديث بعد**؛ CI run `41169445578` بدأت بعد هذا commit، يلزم قراءتها ثم تقرير نشر host قبل إعلانها.
- الحل العملي لتشخيص تحديث APK على الجهاز القديم: تجربة تثبيت APK العام الموقع أعلى النسخة الحالية من مدير ملفات Android لا من زر التحديث، دون حذف التطبيق/مسح بيانات الربط. إذا فشل مدير الملفات أيضًا، احصل على رسالة Android الدقيقة (أو adb install -r للمعمل) لتحديد ERROR_CODE، لا افتراض فساد الملف.

## 31 أكتوبر 2026 — OnTrack Voice 0.2.4 / OTA10 / اتصال بدون Wi-Fi تجريبي / سجل مكالمات أولي
- أصل طلب المستخدم: لماذا سجل المكالمات/الحملات/رفع جهات الاتصال/التقارير/التسجيل/مراقبة الصوت/سيناريوهات AI/Agent Builder لم تظهر في التطبيق والموقع؟ هي **ليست مكتملة**: Laravel `saas/` يحوي CRM/VoiceCampaign workflow/Flow Builder محليًا، لكن ليس ربط SIM telephony/outbound/report/recording ميدانيًا. التفاصيل في `docs/mobile/VOICE_CALL_FEATURE_GAP_AUDIT_2026_10_31_AR.md`.
- حُددت مشكلة تكرار تحميل APK: النسخة القديمة تكرر طلب PackageInstaller بدل انتظار نتيجة الجلسة. أُضيف `NativeUpdates.installPending()` مع SharedPreferences، reuse cached APK، فحص إذن التثبيت قبل التنزيل، status callbacks وتمييز UI. Android 0.2.4 code15 و UI OTA revision10.
- مصدر JemRec pinned يدعم `StartWithoutWifi` عبر ADB 5555 بعد إعداد أولي على Wi-Fi؛ أُضيف opt-in واضح مع تحذير خطر منفذ شبكة عام وقياس ready + disabled في النسخ القديمة. **لم يُثبت على Xiaomi بعد**، وقد يتعطل بعد reboot أو سياسات OEM. لا يُفعّل تلقائيًا.
- في المصدر أضيف `CallEventOutbox.kt` و`call_event.php`, `call_ledger.php`, `calls.php` لتسجيل رنين/رد/نهاية/مدة داخل تخزين خاص، مع حماية device/admin وidempotency؛ لا اسم/رقم/ملخص/صوت حتى الآن. ملفات PHP ونقطة رابط الأدمن نُشرت على `app.ontrackegy.com/saas/mobile` عبر job `mobile-call-ledger-pilot-20261031` (success + PHP lint PASS).
- GitHub Actions run `41167848892`: build=success، Android WebView=success، publish_ota=success، publish_native=success. استضافة OnTrack: UI10 job `mobile-ota-41167848892` success SHA256 `6f28fb74e9e40dc651f9dc09ce01aeabe4cae7b4b1ef132611f6f1f393959f76`؛ native15 `mobile-native-41167848892` success APK SHA256 `2046fb2e46dffbcb01108c882f1ec6e9486001d0c69bec1ce084d573dd07f8c7`, bytes 52424257.
- فحص HTTPS العام والأمان المُجدول `mobile-024-live-security-20261031`: لا تعتبره PASS قبل قراءة تقرير host.
- **مهم:** current installed phone version غير مؤكد، ولا يمكن نقل إصلاح native Kotlin القديم من مجرد HTML OTA. لإصلاح تثبيت Android على الجهاز مرة واحدة يحتاج APK أحدث مع شهادة التوقيع ذاتها؛ قد يطلب Android موافقة/إذن تثبيت من المصدر. لا تمسح التطبيق أو مفتاح الربط. الصوت `mix` لا يُعدل.
- الاختبار المتبقي: Xiaomi update 0.2.4 وتحقق local install result، مكالمة عبر بيانات 4G/5G دون Wi-Fi بعد opt-in أمنية، أول حدث مكالمة يصل للسجل، والاتصال بعد reboot. لا تدّع نجاحها قبل handset evidence.

## OnTrack Voice — تنفيذ جزئي مثبت للديمو، 31 أكتوبر 2026

- القائمة التنفيذية الملزمة: `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md` (30 بندًا غير مغلقة حتى اختبار فعلي). مرتبطة بـ`AGENTS.md` والخطة.
- إنشاء أول **CODE slice** لسجل مكالمات التطبيق في commit `95340642849c7b37c4320741000bab02bf609044`: `CallEventOutbox.kt` ينشئ call_id وأحداث ringing/active/ended/missed محفوظة محليًا مع إعادة إرسال وتجنب تكرارها، `call_event.php` + `call_ledger.php` مخزن خاص، `calls.php` شاشة سجل للإدارة، ورابطها في index. الرقم والاسم والتقرير غير متاحين بعد ولا يجوز ادعاؤهما.
- CI run `41167848892`: خطوة **Validate mobile PHP endpoints** نجحت، وتشمل `php tools/test_mobile_call_ledger.php` وlint ملفات server. بقية بناء Android/اختبارات الجهاز ونشر OTA Native كانت جارية عند تسجيل هذه النقطة؛ لا نعتبرها PASS.
- **HOST:** محاولة إرسال نشر ملفات Call Ledger عبر قناة الإدارة رُفضت قبل التنفيذ بسبب ضوابط أمان أداة التنفيذ. **لا يوجد أي دليل لنشر هذا المسار على app.ontrackegy.com**، والـqueue التشغيلي لم يُستبدل؛ يلزم مسار نشر مصرح به، بلا تحايل على القيود.
- P0 بيانات الهاتف 4G/5G/ADB 5555 وNative OTA Xiaomi لم تُختبر على الجهاز في هذه الدفعة؛ `VC-P0-01..05` لا تزال OPEN. منع تعطيل Play Protect أو فقد بيانات الاقتران.
- المرحلة التالية: تثبيت نتيجة CI والتوقيع؛ تحقق host backend المسموح؛ تجربة Android على Xiaomi لـ4G/5G وتحديث APK، ثم Call History E2E قبل إغلاق أي Checkbox.

## 31 أكتوبر 2026 — خطة إقفال ديمو Android والويب

تم اعتماد وحفظ `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md` في GitHub، بمجموع 30 بندًا قابلة للتعليم. الأولوية P0: تحديث Native/OTA وآلية 4G/5G مع تقييم أمن ADB 5555، ثم Call ID/Call History، استيراد جهات الاتصال والحملات، AI Scenario/Agent Builder، المراقبة/التسجيل/الجودة، UI/E2E. أي ميزة غير مثبتة على الجهاز تظل `[ ]` حتى مع وجود الكود. لا تغيير live/اقتران أو بدء SaaS.

## OnTrack Voice 0.2.3 — تثبيت Android الجديد وSplash OTA9 (31 أكتوبر 2026)

- المستخدم شارك صورة من جهاز Xiaomi تظهر `There was a problem parsing the package` بعد تحديث 0.2.2 عبر النسخة المثبتة 0.2.1. المستخدم يريد تحديث View بصمت دون إعادة تثبيت وتحديث Native من داخل التطبيق قدر الإمكان، ويريد شاشة OnTrack متحركة فاخرة، مع عدم فصل Cloud عن Android.
- نفذنا `NativeUpdates.kt` بـ Android `PackageInstaller.Session` والتحقق من توقيع manifest + SHA/size/package/version/cert، بدل `ACTION_VIEW` الذي قد يفشل على OEM؛ `NativeInstallResultReceiver.kt` يسجل سبب خطأ التثبيت ويعالج `STATUS_PENDING_USER_ACTION`. `MobileLinkActivity.kt` يُظهر سبب التثبيت عند العودة ويدير شاشة إقلاع Android أصلية `OnTrackLaunchScreen.kt`. `saas/mobile/ota/ui.html` واجهة UI9 تتحرك عند بداية التحميل، تدعم Reduced Motion، وتُحدّث WebView بعد المكالمة لا أثناءها. `saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html` محدثة أيضًا.
- CI النهائي GitHub Actions run `41161367116`: build=success، Android WebView instrumentation=success، publish_ota=success، publish_native=success. أصدر `0.2.3-ota` code14 مع نفس شهادة التوقيع السابقة.
- استضافة app.ontrackegy.com: host report `mobile-ota-41161367116` success revision9؛ `mobile-native-41161367116` success، APK bytes 52407873، SHA256 `0eedd40db8d2c69b6e5fab2dcccccc2883f20880e52733237e784ed5b3501fbe`. فحص مستقل `mobile-023-public-verify-20261031` success: `latest.json` HTTP200 وHTML HTTP200 13185 bytes، `native/latest.json` HTTP200 وAPK HEAD HTTP200 بالحجم المطابق؛ content type application/vnd.android.package-archive.
- رابط APK المصحح: `https://app.ontrackegy.com/saas/mobile/updates/native/ontrack-voice-v14-0eedd40db8d2c69b6e5fab2dcccccc2883f20880e52733237e784ed5b3501fbe.apk`.
- **قيود لا يجوز تجاهلها:** UI OTA9 متوافق مع native 12–14 ويفعل الـSplash بالـWebView من غير APK. أما إصلاح Native installer فلا ينتقل إلى 0.2.1 كـHTML OTA؛ يلزم تثبيت 0.2.3 مرة فوق التطبيق من Android Installer. جهاز Android غير مدار قد يطلب تثبيت من هذا المصدر وموافقة المستخدم. لا تدّعي أن 0.2.3 حل parse على Xiaomi إلا بعد real-device test؛ الاختبارات الآلية لا تتحقق من ROM الخاص بالمستخدم. لا Uninstall ولا Clear Data. لم يتم تنفيذ السجلات التجارية/الحملات/Agent Builder/التسجيل الحي في هذه الدفعة؛ Laravel repo لديه أساس CRM/Campaigns/Flow وليس تشغيل SIM end-to-end مثبت.
- توثيق مفصل: `docs/mobile/ONTRACK_V023_INSTALLER_SPLASH_FIX_AR.md`.

## إصدار OnTrack Voice 0.2.1 — مركز تحديثات Android مكتمل النشر (2026-10-31)

نجاح بناء Android وإختبارات JVM وWebView في GitHub Actions run `41157138497`؛ Artifact `13025238406`، `versionCode=12`، `versionName=0.2.1-ota`، SHA256 APK `d78e110aa8bdfab4264f7904af7e0e8cf16af0a9ed847c16161b70f560cefc00`، شهادة APK الأصلية `d25378b9...` نفسها. نُشر UI OTA revision7 عبر مهمة `mobile-ota-41157138497` نجاح. ونُشر Native manifest الموقّع وAPK عبر `mobile-native-41157138497` نجاح. فحص HTTPS نهائي `mobile-native-public-permission-fix-20261031` نجاح: `native/latest.json` HTTP200، APK HEAD HTTP200، حجم 52407873 ونوع application/vnd.android.package-archive. خطأ 404 المؤقت سببُه أن المجلد اتعمل mode0700 بسبب umask العامل، اتصلح إلى 0755، وأُضيف إصلاح ناشر المستقبل في `tools/queue_mobile_native.py` commit `de326fb`. الرابط المباشر الموثق:
`https://app.ontrackegy.com/saas/mobile/updates/native/ontrack-voice-v12-d78e110aa8bdfab4264f7904af7e0e8cf16af0a9ed847c16161b70f560cefc00.apk`.
التطبيق 0.2.1 فيه Native Update Center يتحقق من signed manifest + APK hash/size/package/cert ويفتح موافقة Android بدلاً من تثبيت صامت، بالإضافة إلى UI OTA وإعدادات السيرفر الموجودة. **الانتقال 0.2.0→0.2.1 يحتاج تثبيت APK مرة واحدة فوق النسخة المثبتة؛ لا حذف بيانات.** اختبارات التطوير والبناء والنشر ناجحة؛ لكن تثبيت النسخة الجديدة على هاتف المستخدم، أول تنزيل Native Update مستقبلي، تسليم المكالمة والانتقال بين Wi-Fi لم يُختبَروا ميدانيًا بعد. ملف حقائق التسليم `docs/mobile/ANDROID_UPDATE_0_2_1_DELIVERY.json`، التصميم والاختبارات `docs/mobile/ANDROID_IN_APP_OTA_0_2_1_AR.md`.

## 2026-10-31 • OnTrack Voice 0.2.0 / Server Sync & Auto Reconnect
- طلب المالك: التفعيل/الإيقاف موحّد بين الهاتف والسيرفر، استلام بشري للمكالمة الحالية فقط، فقاعة منظمة بلا Accessibility، إعداد Android بخطوتين، استئناف بعد تبديل شبكة Wi-Fi، هوية OnTrack، ودخول لوحة الإدارة من التطبيق بدون إعادة كتابة Secret Key.
- تم نشر `control.php` و`admin_ticket.php` و`admin_enter.php` و`status_feed.php` على الاستضافة عبر `mobile-020-auth-deploy-20261031` (success + PHP lint PASS). تم نشر تعديل `index.php` عبر `mobile-020-admin-sync-20261031` (success + backup). فحص HTTP `mobile-020-live-security-verify` نجح: صفحة الإدارة 200، وواجهات التحكم والـSSO والحالة غير الموثقة 401. تعديل SameSite Lax للأمان وتوافق SSO على المتصفح عبر `mobile-020-sso-cookie-20261031` (success).
- Android Kotlin: `MobileLinkService` يحتفظ بالربط كـForeground Service حتى لو AI OFF، يسحب desired server state ويدفع تغيير الهاتف للسيرفر، ويرفع device-ack؛ `ConnectivityManager.registerDefaultNetworkCallback` وretry 2–30 ثانية مع محاولة إحياء مساعد الشريحة، دون تجاوز صلاحيات Android. `CallOverlay` بداخلها استلام حالي، سماعة، تشغيل/إيقاف؛ `ProofActivity` معالج إعداد مخفف إلى خطوتين؛ UI OTA6، تطبيق `0.2.0-ota` وversionCode11 وبصمة التوقيع القديمة إلزامية؛ علامة OnTrack الجديدة.
- **CI أحدث حالة عند كتابة السجل:** Run `41151549729` مستمر. Run `41151330827` فشل بسبب Kotlin `fillViewport` في ProofActivity؛ أصلح في commit `a1ad4fc7` إلى `isFillViewport`. لا تدّع نجاح APK قبل إكمال CI والحصول على Artifact موقّع، ولا تدّع نجاح تبديل Wi-Fi/تسليم بشر/SSO في الهاتف قبل Real Device E2E.
- شرح شامل وقائمة الاختبار: `docs/mobile/VOICE_020_RELEASE_AR.md`. آخر نجاح مثبت للمكالمة الصوتية ثنائية الاتجاه كان بالنسخة 0.1.9 وقناة `mix` على هاتف المستخدم؛ لا تغير هذا المسار.

## Pilot Voice 0.1.9 + Admin/OTA5 — تحقّق 31 أكتوبر 2026

المالك أكد مكالمات SIM حقيقية صوتًا في الاتجاهين بعد `input_channel=mix`. بناء `OnTrack-SIM-Audio-Test-0.1.9.apk` أنهى CI run `41145709185` بنجاح: Kotlin/daemon tests، Android WebView instrumentation، توقيع APK/OTA. شهادة APK `d25378b9...` نفسها القديمة، `versionCode=10`، `SHA256=7bef6a3cc9f4db41096598e0b66d34681ca8e33b601ad2fb9e1ebaf9ae7463e5`. Artifact `13020867281` (موجود للتنزيل). OTA5 نشرته مهمة `mobile-ota-41145709185` على الاستضافة ورجع `success/exit0/revision5`. لوحة التحكم الحالية نُشرت عبر `mobile-admin-backend-20261031a` و`mobile-admin-panel-publish-20261031c`، فحص PHP `PASS`، فحص HTTP أظهر `admin=200` و`status_feed=401` دون جلسة، ومفاتيح الربط لم تُقرأ أو تُطبع. الكود يدعم نافذة عائمة اختيارية دون Accessibility، استلام بشري، إيقاف الوكيل، رد بعد 5 ثوانٍ، زر سماعة تجريبي، وقواعد ردود قصيرة بالمصري مع مقاطعة Gemini. **لم تُختبر هذه الإجراءات الجديدة على هاتف المالك بعد**؛ نجاح CI لا يثبت handoff أو speaker أو latency. الحالة المفصلة: `docs/mobile/VOICE_PILOT_RELEASE_0_1_9.json` وخطة التست `docs/mobile/VOICE_PILOT_0_1_9_CALL_CONTROL_AR.md`. لا تمسح بيانات التطبيق أو الاقتران؛ اختبر فوق النسخة الحالية.

## قرار معتمد — Phone Control Center / سياسة رد بعد 5 ثوانٍ (2026-10-31)

اعتمد المالك مواصفات تجريبية على مستوى الخط/الشركة: `auto_answer=ON`، `answer_mode=after_delay`، `delay_seconds=5`، `agent=sales`، **دون تفعيل إنتاجي حتى الاختبار**. تفاصيل الـstate machine والأمان والقيود الرسمية على Android والـAPI/RBAC واختبارات E2E في [PHONE_CONTROL_CENTER_SPEC_AR.md](../architecture/PHONE_CONTROL_CENTER_SPEC_AR.md) (commit `8ccdd2d74279c4b72b72dd62f90d685fa343b9ac`). المصدر الحالي يرد فورًا عند RINGING، لا يضم مؤقتًا ولا Agent ID متعدد الشركات؛ لا يتم تغيير الربط الصوتي العامل أو قناة `mix` حتى اختبار السياسة الجديدة.

## تحقق ميداني — OTA4 وHeartbeat من هاتف المالك (31 أكتوبر 2026)

صورة الهاتف تؤكد فتح Android 0.1.7-ota وتشغيل UI OTA4 برسالة «الواجهة جاهزة • OTA 4» مع حالة «متصل — جاهز لمكالمة واردة أو اتصال من الهاتف». اختبار هوست حي `mobile-device-heartbeat-20261030T224309` نجح `exit_code=0` الساعة 22:44:02 UTC؛ أحدث heartbeat كان 22:44:01 UTC (العمر 1 ثانية)، `settings_enabled=true`، `auto_answer_enabled=true`، `settings_revision=4`. مصدر التقرير: `ops/host-link/results/appontrack-server11/mobile-device-heartbeat-20261030T224309.json` في الفرع `ontrack-host-link`. هذه أدلة تشغيل UI وطلبات الإعدادات من الهاتف، ولا تثبت اتصال AI/SIM صوتي ثنائي الاتجاه بعد. الخطوة التالية: مكالمة واردة فعلية من رقم ثانٍ وقياس الإجابة والتقاط الصوت والرد.

## أحدث عطل — WebView منع الصفحة المحلية، إصلاحNative0.1.7

صورةالمالكبعد0.1.6تظهرتحميلOTAثمWebpage not available علىdata:text/htmlمعHTTP_RESPONSE_CODE_FAILURE. الكودكانيعيد403لكلطلب،بلامساريسمحويخدمHTMLالمحلي؛هذاعيبفيالنسخةالمسلّمةولايتعلقبمفتاحالمكالمات401.0.1.7يستبدلالتحميلبصفحةHTTPSافتراضيةمحددةلكلrender،وتُخدم200منالـHTMLالموقّعفقطللـmainGET؛كلالمواردالأخرىتبقىمحجوبة. أزرارNativeللتشخيصوالاسترجاعتبقىمتاحةإنفشلWebView. نضيفاختبارinstrumentationلـAndroidWebViewAPI34قبلالتسليم؛ البناءوالهاتفغيرمثبتينبعد.

تقريرmobile-ota-41131334617وصلsuccess/exit0عند21:38:30UTCوثبّتنشرrevision2؛ إذًاالعطلالحاليليسغيابملفاتOTA. الإصلاحNativeويحتاجتحديثAPK0.1.7بنفسالشهادةوبلامسحبيانات. راجع docs/mobile/OTA_WEBVIEW_LOAD_FIX_2026_10_30.json.

## أحدث تسليم — APK0.1.6 وOTA موقّعان، نشرالواجهةمنتظرالعامل

run41131334617نجح(build124322733614,publish124324094537).Artifact13013975015نُزّلوتحققناZIPوAPKوبصماتoverlayوالواجهةالمضمنةوشهادةنفسالنسخالأخيرة. اختباراتKotlinلتوقيعRSAالمعدل/المفتاحالمختلفوحدودruntime/path/sizeنجحت،والحارسالنهائيلـmanifestنجح؛لاNotificationListenerأوSMSأوAccessibility. محاولاتالبناءالسابقةمحفوظة؛خطأBuildConfigأُصلحباستخدامPackageInfo. فحصJSsyntaxنجح؛فحصPlaywrightلم يُنفّذ لغيابChromiumالمحلي،ولانصفهناجحًا.

الـOTAهوواجهةمركزالاتصالHTML/CSS/JSضمنbridgeثابتوتوقيعشهادةAPK،نسخةمضمنةواحتفاظبالسابقةومهلةready10ثوانٍومنعreplayوnonceلكلrender. NativeالصوتوالصلاحياتتحتاجAPK. يلزمتثبيت0.1.6تأسيسيًامرةبنفسهويةالتطبيقوبلامسحالاقتران.

النشرالآليأرسلmobile-ota-41131334617(7067بايت،تنفيذ25ثانية،صلاحيةحتى31أكتوبر21:32UTC)عبرالعاملالموجود. آخرالفحص:لاتقريرmatchingوlatest.jsonيرجع404؛إذًاالتحديثغيرمنشوربعد. لا تستبدلأوتكرّرالمهمة. يحتاجالعاملدورةتنفيذ،ثمفحصتوقيعmanifestوبصمةHTMLمنالموقع،ثمفتحالهاتفلإثباتUIOTA2. إعداداتربطالمكالماتالسابقةمنشورة،لكنphone401مازالتغيرمحلولةوAI/SIMduplexغيرمثبت.

الأدلة docs/mobile/MOBILE_OTA_BUILD_0_1_6.json وdocs/mobile/OTA_HOST_PUBLICATION_2026_10_30.json وdocs/mobile/OTA_UPDATES_AR.md.

## أحدث طلب — تنفيذ OTA داخل التطبيق

المالكطلبتحديثًامثلThndr. نُفِّذتطبقةواجهةمركزالاتصالHTML/CSS/JSقابلةللتحديثموقعةبهُويةAPK،وcacheمحليوتراجععندالفشلوتأجيلأثناءالمكالمة،ضمنbridgeمحددبلاshellعامأوملفاتأوشبكةمنWebView. أولنسخة0.1.6تحتاجتثبيتًاتأسيسيًامرة؛تغييراتNativeالصوتوالصلاحياتتحتاجAPKدائمًا. الكودقيدالبناءولانشرOTAمثبتبعد. الهاتف401قضيةمستقلةمفتوحة. راجع docs/mobile/OTA_UPDATES_AR.md.

## أحدث رصد — التطبيق مفتوح، الهاتف401 والمفتاح الصحيح يمر200

صورةالمالك تعرضشاشةالربط تعمل معHTTP401؛ رقمAPKغيرظاهر. اختبرناالمفتاحالمعطى علىdevice.phpفعليًا بطلبين، أحدهماUser-AgentلـOkHttp4.12: كلاهما200/ok/enabledtrue. لااستنتاج بأنالمالكأدخلقيمةخاطئة؛ الحقلمخفيولايمكنفحصه. الخطوةالمباشرة: إيقافالربط، استبدالالحقلبنسخةالمفتاحالمنسوخةبالضبط، إعادةالتشغيل. إن استمر401نضيفتشخيصًامنقحًا، دونمسحاقترانأوتعطيلحماية. لااتصالAI/SIMمثبت. الدليل docs/mobile/MOBILE_AUTH_RECHECK_2026_10_30.json.

## أحدث تسليم — APK0.1.5 جاهز، تجربة التثبيت على الهاتف مطلوبة

حُذفت خدمةقراءةإشعاراتالتطبيقاتالأخرى وتنفيذها ومساراتمنحها وواجهتها منOnTrack. بُنيAPK0.1.5فيrun41127903051، artifact13012375680. تحققنا منالملف المنزّل وبصماتoverlay ومنmanifestالمستخرج منAPKالنهائي: لاNotificationListener/SMS/Accessibility. التوقيع مطابق0.1.1و0.1.4؛ نفسpackage، بلاطلبمسحبياناتالاقتران. SHA256: fbffb166062918ef70715ec43b9b6fb4d78040545c33bf0509a76e7217bf5c04. هذه إزالةصلاحيةغيرلازمة، وليستتعطيلًا للحماية أوتحايلًا بتغييرهويةالحزمة. قبولPlayProtect والتثبيت الفعلي والمكالمة الكاملة غيرمثبتين بعد.

الهوست منشوربتقريرsuccess/exit0. الصفحة200، deviceبدونمفتاح401، بالمفتاح200، sessionالمحمي200وبهتوكنمؤقت، ملفhashمرفوض403. المصدر docs/mobile/MOBILE_BACKEND_HTTP_2026_10_30.json. لا تكرارلمهمةالنشرالمنفذة، ولاانتظارCronكشرطلبدءالمكالمة.

## أحدث عطل تثبيت — Play Protect، إصلاح0.1.5

صور المالك تظهر حظر0.1.4 برسالة sensitive data / identity theft / financial fraud. وجدنا NotificationListenerService موروثًا من JemRec لميزات إخفاء إشعارdebugging وأسماء متصلي تطبيقات أخرى؛ الجسر الحالي لا يحتاجه. رسالة Google الرسمية تطابق هذا النوع من الحظر؛ السبب الداخلي الدقيق غير متاح. حذفنا الخدمة وتنفيذ قراءة الإشعارات ومسارات منح صلاحيتها وإعداداتها؛ لا تعطيلPlayProtect، ولا تغييرهويةالحزمة للتحايل، ولا مسحبياناتالإعدادالعامل.0.1.5 قيدالبناء والتحقق، والقبول على الهاتف غير مثبت.

تقريرالنشرmobile-link-deploy-1793390868859 وصلsuccess/exit0 بتاريخ20:46:25UTC، وفحصPHPنجح ونسخةاحتياطيةخاصةحُفظت. هذا يحل انتظارالتنفيذ السابق؛ لا يثبت مكالمةAI. الدليل docs/mobile/PLAY_PROTECT_INSTALL_2026_10_30.json وتقريرالهوست.

# نقطة الاستكمال — تطبيق اختبار الصوت جاهز

## نقطة التسليم الحالية — APK0.1.4 جاهز، العامل مطلوب للنشر

نُزل artifact13009691302 من run41122903309. SHA256للـAPK هو41d32c09d4b8714dfce9d38614ccb322c15553f44a8749ad8a37964762b524cb؛ الحجم52327052بايت. التوقيع مطابق0.1.1، وبصماتoverlayمطابقة، وفُحصDEXلوجود عنوانRPCالمصحح وغيابالخاطئ. GeminiقبلsetupCompleteفعليًا، ونجحlivecheck41122903296.

النشر لم يُثبت بعد: mobile-link-deploy-1793390868859 ما زالت المهمة الحية، وآخرAPIجديد404، ولا تقريرتنفيذ. أمرworkerالذي نفذه المالك سابقًا أكمل الفحصوانتهى؛ طُلبloopقصير للعامل الموجود لمعالجة المهمة التالية، دونتوكنجديدأوقراءةconfigأوإعادةتثبيت. لا تسلّم مفتاحالربطعلىGitHub أو تقاريرالهوست؛ قيمةمفتاحالتجربة الجديد مخزنةمحليًافقط، وhashفقطداخلالكودالخاص.

التطبيق المصحح يمكنتثبيتهكتحديثفوق0.1.1 بنفسالتوقيع؛ لا نطلبمسحبياناتالتشغيلالعامل. لا يزالاختبارAI/SIMالفعليغيرمُنفذ، ولا تُعلَنصفحةالإعداداتمنشورةقبلوصولتقريرالنشر وفحصHTTP.


## أحدث تصحيح — جلسة Gemini قُبلت، APK0.1.4 يُبنى

404كانت بسبب خطأ في العنوان الذي كتبناه: استخدمنا / بين GenerativeService وBidiGenerateContentConstrained بدل النقطة في SDKGoogleالمثبت2.25. لم يكن يلزم تبديل مزود/مفتاح/موديل أو إعدادات الهاتف. صححنا Native والاختبار. فحص فعلي من البيئة أعاد websocket_open=true وsetup_ack=true، وفحصGitHubالمصحح نجح بخطوةالتحقق؛ لم يرسل صوتًا ولم يثبتSIMduplex.

لا تسلّمAPK0.1.3 القديم لمهمة الربط: التوقيع والبناء صحيحان لكنه يحملمسارWSSالخاطئ. الإصدارالمصحح0.1.4 مصدرf163c6bb7dc83f06ba5f65d11e9ee0aa1f31aa77، build41122903309، livecheck41122903296. يظل النشر منتظر مهمةmobile-link-deploy-1793390868859؛ لا تقريرولامدخلموبايلحي بعد عندآخرفحص.


## أحدث نتيجة — APK0.1.3 متحقق منه، النشر منتظر

النسخة النهائية مصدر03e014727ca34d4bbe9459c9a477d39501b02415، run41120377309، artifact13008627580. اختبارات وبناء وتوقيعAPK وفحصPHP اكتملت بنجاح؛ الحزمة المنزلة تطابقSHAوبصماتoverlay، وDEXيحتويالجسر، والتوقيع يطابق0.1.1. لا يستنتج ذلك محادثة SIM.

تشغيل المالك للعامل أعاد تقرير mobile-bridge-inspect-1793389599248 ناجحًاUID2379/exit0. أرسلت بعده mobile-link-deploy-1793390868859 بحزمةSHAمحددة، لكن التقرير ما زال منتظرًا والـAPIالجديد404 عند آخر فحص. طلبنا loopمحدود3دقائق للعامل القائم؛ لم نقرأ configأوPATولم نعد تثبيته. لا تستبدل المهمة المعلقة أو تعلن النشر قبل تقريرها.

APIالجلسة الحالي أعادHTTP200وتوكنمؤقت؛ محاولاتWSSالمحلية أظهرتفتحًا مرة و404مرتين قبلsetup، لذلك شُغّل تشخيص مستقل علىGitHubActions run41121648614 دون إرسال صوت أو طباعةتوكن. لا يثبت إصدارالتوكن قبولالجلسة. نحتاجنتيجته ومكالمةالمالكالمباشرة. OFFHOOKليس دليلإجابةالطرفالبعيد؛ هذه بوابة منفصلة قبل حملاتالصادر.


## أحدث توجيه — الربط المباشر بالموقع

المالك طلب الانتقال فورًا لربط تطبيق الهاتف بالسكربت الموجود على app.ontrackegy.com، وإضافة إعدادات الاتصال، وإعادة التقييم عند ظهور عطل بدل إطالة اختبارات منفصلة. هذا يفتح تنفيذ الربط الآن؛ التسجيل ليس بوابة تمنع العمل، ولا يعني أن duplex قد ثبت.

HTTP مباشر أكد OnTrack Live وassets/app.js?v=063 وGemini Live/current ephemeral-token builder. لا يظهر Fish في JS الحالي؛ لا ندّعي أن ملف الصوت المستورد صار صوت كل رد. كود0.1.3 يضيف إعدادات ربط محمية بمفتاح تجريبي، API داخل saas/mobile، foreground service، عميل Gemini WebSocket بالتوكن المؤقت، وجسر PCM محلي authenticated shell. الإصدار0.1.2 نجح بناؤه في run41117754860؛ لا نطلب اختباره منفردًا قبل الربط.

قناة الهوست: أُرسلت مهمة قراءة فقط mobile-bridge-inspect-1793389599248 إلى الفرع الحي؛ لم يصل تقريرها بعد. لا نستنتج أن صلاحية الدخول متاحة حاليًا لمجرد HTTP200، ولا نقرأ config.json أو نعيد تثبيت العامل. النشر الحي غير منفذ حتى يصل مسار التحكم؛ جهز المصدر أولًا.


## أحدث اختبار وإصلاح — 30 أكتوبر2026

المالك أكد أن الطرف البعيد سمع ملف الكلام كاملًا بوضوح، لكن قائمة التسجيلات فارغة والتقرير المحفوظ يظهر فارغًا عند رفعه. سماعه الشخصي للطرف البعيد لا يثبت التقاط التطبيق. وجدنا أن Main.stopRecording في المصدر المثبت يحذف تسجيلات outcome.test عمدًا؛ فات تعديل ذلك في نسخة OnTrack الأولى.

تحديث0.1.2 يحتفظ بالتسجيل كاملًا، ويسلّمه لمسار الحفظ المعتاد، ويضيف زر جمع التسجيل بعد نهاية المكالمة مع عدد الملفات ونتائج الحفظ. تصدير JSON يأخذ لقطة قبل فتح منتقي الملفات، ولا يتجاهل callback بسبب busy، ويقرأ الملف بعد الكتابة للتحقق من البايتات. السبب الدقيق لملف المالك الفارغ غير مثبت، ولا نفترض أنه نفس هذه الاحتمالات. بناء CI واختبار الهاتف مطلوبان. لا تمسح بيانات الإعداد العامل.


## أحدث نتيجة فعلية — 30 أكتوبر 2026

قال المالك: «سمعت صفاره والصوت وصل هناك». سُجل وصول الصوت للطرف البعيد حسب تجربة المالك، وليس مجرد نجاح اقتران أو بناء. هذه نتيجة جزئية إيجابية على Redmi Note12 Pro5G؛ لم يصل تسجيل/تقرير خام، ولم تُحدد نسخة APK أو اتجاه المكالمة أو مسار الصوت. يلزم تأكيد سماع ملف الكلام نفسه بعد الصفارة واستبعاد التسريب الصوتي، ثم مراجعة تسجيل عبارة مميزة يقولها الطرف البعيد.

الحالة الحالية **FAR_END_AUDIO_OWNER_REPORTED_CAPTURE_PENDING**؛ الوصف الأقدم «لم تبدأ أي تجربة صوت» أصبح تاريخيًا. لا نفترض إصلاح صلاحية WRITE_SECURE_SETTINGS أو نجاح0.1.1 دون تقرير. نحافظ على الإعداد العامل ولا نطلب مسح بيانات أو إعادة اقتران. حوار AI مستمر والوارد/الصادر والتعافي ما زالت اختبارات لاحقة. الدليل: [رصد التجربة](../mobile/DEVICE_OBSERVATION_2026_10_30.json).


## أحدث قرار بحثي — 30 أكتوبر 2026

Shizuku وAccessibility وأي شراء هاردوير للعميل وأي عقد/اشتراك اتصالات جديد مستبعدة صراحة. الشريحة والباقة والصوت الموجودون هم الأساس. المرشح الجديد **embedded ADB داخل APK مع مساعد shell**؛ اقتراح مشروط لا يثبت صوت هاتفنا، ولا يزيل قيود المصنع أو الإعداد الأول. بحث المصدر وجد مسار تحية في JemRec، لكنه لا يثبت حوار AI مستمرًا أو استعادة قبل أول unlock.

راجع [التقرير الموسع](../research/2026-10-30/SOFTWARE_SIM_VOICE_RESEARCH_AR.md) و[خطة إثبات الصوت](../research/2026-10-30/AUDIO_PROOF_PLAN_AR.md). الحالة PENDING_DEVICE_AUDIO_PROOF؛ لا تنفيذ تطبيق/هوست أو مكالمات في جلسة البحث.

**الحالة: PREPARATION_COMPLETE_AWAITING_OWNER_START. التاريخ: 29 أكتوبر 2026، والعرض بتوقيت Africa/Cairo.**

## آخر تعليمات المالك

اترك الرئيسية كما هي، وخصص مجلد saas للمشروع. الميموري والسجلات والخطة والبرومبت **على GitHub فقط**، خارج مجلد التطبيق. المطلوب الإبلاغ بانتهاء التجهيز قبل بدء تنفيذ التطبيق.

## ما أُنجز وثبت

- فحص الهدف أكد عدم وجود مجلد saas سابقًا.
- أُنشئ `/home/appontrack/public_html/saas` فعليًا، وبقي **فارغًا**. لم يُحفظ توثيق أو ميموري أو سكريبت داخله.
- نتيجة العملية success/exit0، وزمنها 62ms. الصلاحية المرصودة 0700 في مرحلة الحجز؛ لا توجد واجهة ويب منشورة بعد.
- مقارنة 29 ملفًا محددًا من مصدر الموقع قبل وبعد الإنشاء: **لم يتغير أي منها**.
- لا تغييرات DB أو migrations أو dependencies أو Laravel bootstrap، ولا Cron جديد للمشروع.
- أُعد على GitHub برومبت شامل وميموري وقضايا وسجلات أفعال ومحاولات واختبارات وقوالب استكمال، خارج `saas/`.
- أضيف متطلب الرد على السوشيال وTelegram بواجهات رسمية وقدرات منفصلة لكل قناة؛ لم يُربط حساب خارجي ضمن التجهيز.

## مسارات البدء

| الغرض | المسار |
|---|---|
| البرومبت الشامل | `docs/execution/MASTER_EXECUTION_PROMPT_AR.md` |
| الميموري | `docs/memory/PROJECT_MEMORY_AR.md` |
| القضايا والمحاولات | `docs/memory/ISSUE_REGISTER.json` و`docs/memory/logs/` |
| مصدر التطبيق المقبل في GitHub | `saas/` |
| هدف التطبيق على الهوست | `/home/appontrack/public_html/saas` |
| تعليمات استلام الجلسة | `docs/memory/SESSION_HANDOFF_AR.md` |

## الأدلة

- [فحص الهدف](../../ops/host-link/results/appontrack-server11/saas-inspect-1793232909572.json): success، والهدف غير موجود، مع رصد أدوات PATH ومسارات PHP المحتملة.
- [إنشاء المجلد وحماية المصدر](../../ops/host-link/results/appontrack-server11/saas-prepare-1793233067549.json): success، وentries=[]، وmemory_location=GITHUB_ONLY، وoriginal_runtime_unchanged=true.
- [ملخص آلي](../evidence/saas-workspace-preparation.json) و[checkpoint.json](checkpoint.json).

## أين توقفنا؟

قبل تنفيذ التطبيق. لا Laravel application جديدة ولا DB مهيأة في saas. الديمو الحالي v0.6.3 باقٍ كما هو. رصد ملفات PHP83/84 لا يثبت الإصدار أو الامتدادات أو جاهزية Composer/Node؛ نراجع ذلك عند التنفيذ.

صوت الشريحة ما زال PENDING_DEVICE_AUDIO_PROOF. Telegram Bot API موثق كقدرة تقنية، لكن لا بوت متصل. شروط Meta تتطلب مراجعة صلاحيات الحسابات؛ الصفحات الرسمية أعادت 429 في المراجعة الأخيرة، ولم نتجاوز ذلك بادعاء دعم مطلق.

## الخطوة التالية

إبلاغ المالك بالانتهاء ومسار البرومبت. عند أمر البدء اللاحق: اقرأ هذا الملف والبرومبت، وحدّث phase/session، ثم ابدأ فحص البيئة الفعلية داخل saas وتحديد PHP/Composer/build وMySQL مستقلة، مع الحفاظ على الرئيسية. سجّل كل إجراء ومشكلة ومحاولة واختبار في GitHub.

لا تعِد إعداد host-link أو تطلب PAT جديدًا والقناة تعمل. اقرأ المهمة والتقرير الحاليين من ontrack-host-link قبل أي مهمة جديدة؛ نسخة main ليست بالضرورة الطابور الحي.

## آخر توضيح — 30 أكتوبر2026

التطبيق يستقبل مهمة وقائمة/دفعة من الداشبورد ويدير دورة المكالمات محليًا، دون AccessibilityService. تفاصيل العقد في docs/architecture/MOBILE_EXECUTOR_CONTRACT_AR.md. التنفيذ لم يبدأ، وصوت الشريحة ما زال غير مثبت. الخطوة التالية المتعلقة بالصوت إثبات مسار دون Accessibility على الجهاز؛ لا يغلق هذا العائق بمجرد default dialer أو نجاح الطلب/الإنهاء.

## استكمال البحث الحالي

اكتمل البحث ومراجعة الكود وحساب الافتراضات وتحديث القيود؛ التطبيق لم يبدأ. الخطوة الفنية التالية اختبار صوت فعلي على الجهاز وفق AUDIO_PROOF_PLAN_AR، قبل أي ادعاء نجاح أو حملات. لا توجد صلاحية تحكم هاتف متاحة هنا، ولا نتيجته. مسار الاستعادة قبل أول unlock قضية مفتوحة VOICE-003. لا تكرر إجراء Shizuku القديم.

## بدء تنفيذ تطبيق الاختبار — 30 أكتوبر 2026

المالك قال: «هات التطبيق ... الريبو ... الصلاحيات ... اتعامل». هذا أمر بدء لتطبيق إثبات صوت Android وAPK، ولا يُطلب تأكيد مكرر. المصدر في saas/mobile/audio-proof/؛ بناء معزول عن هوست PHP. نعيد استخدام JemRec عند commit مثبت مع overlay خاص بـOnTrack، ونحتفظ بالرخصة والإشعارات. لا Laravel أو AI/campaign integration ضمن أول APK. تظل PENDING_DEVICE_AUDIO_PROOF حتى اختبار الهاتف الحقيقي.


## النتيجة الحالية — APK جاهز للاختبار على الهاتف

بُني OnTrack SIM Audio Test v0.1.0 على GitHub بالفعل، ونجحت اختبارات استيراد الصوت ومنطق المساعد بما فيها إلغاء الاختبار، وتجميع APK وفحص التوقيع. نُزلت الحزمة وطابقت SHA-256 ومصدر overlay وmanifest. **لم يحدث تثبيت أو اختبار مكالمة على شاومي بعد**؛ الحالة PENDING_DEVICE_AUDIO_PROOF.

- المصدر: saas/mobile/audio-proof/، commit 9b3120ec1d73ddb8f9d2f835624f3a93fbc54e2c.
- الدليل: docs/mobile/BUILD_STATUS.json، ودليل الاستخدام docs/mobile/AUDIO_PROOF_APP_AR.md.
- البناء: https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/41082229515
- APK داخل artifact OnTrack-SIM-Audio-Test-0.1.0. النسخة debug تجريبية، لا AI/campaign/server binding.
- الخطوة التالية: تثبيت النسخة، إعداد الاقتران المحلي مرة واحدة، استيراد ملف الصوت، ثم وارد وصادر تجريبيان وتصدير النتيجة. أول unlock بعد reboot ما زال قيدًا.
- لم يتغير الهوست أو موقع PHP أو قناته الحية. لا شراء أجهزة أو خدمات ولا Shizuku/Accessibility.

## أول محاولة إعداد على الهاتف — 30 أكتوبر2026

المالك أرسل شاشة Already paired / Finish connecting، وأكد إغلاق التطبيق وفتحه. آخر log مفصل يذكر رفض صلاحية تغيير إعدادات debugging ويطلب USB debugging (Security settings). بطاقة الحالة الحالية تقول إن الاتصال بالهاتف لم يكتمل؛ لا نجزم أن سببها الحالي هو نفس الرفض السابق. حالة المفتاح نفسه غير مرصودة.

القضية MOBILE-001 مفتوحة. الخطوة التالية تفعيل/التحقق من المفتاح المحدد في Developer options مع Wi-Fi وWireless debugging، ثم Try again مرة واحدة وقراءة أحدث رسالة. لا إعادة اقتران أو تثبيت أو تغيير كود بلا دليل جديد. لم يبدأ اختبار صوت SIM.

## تحديث صور خيارات المطور — 30 أكتوبر2026

USB debugging وUSB debugging (Security settings) كلاهما ON بحسب الصورة الجديدة. لا نكرر طلب تفعيله. شاشة التطبيق ما زالت Finish connecting والزر busy والسجل القديم17:55 لم يتغير. المصدر يعيد الاكتشاف6مرات بمهلة6ثوان لكل مرة، إضافة لوقت الاتصال؛ لا نجزم بتعليق دائم من صورة واحدة. الخطوة التالية صورة صفحة Wireless debugging الداخلية (IP/port والأجهزة المقترنة)، دون حذف الاقتران أو إعادة التثبيت. لا دليل صوت بعد.

## جديد: قائمة الاقتران في Android فارغة — 30 أكتوبر2026

صورة Wireless debugging تؤكد ON ومنفذ اتصال ظاهر، لكن لا عناصر تحت Paired devices، بينما التطبيق عرض Already paired. لا نعيد طلب تفعيل المفاتيح؛ الدليل الجديد يبرر إعادة الاقتران مرة واحدة. الكود يعتمد hasPaired محفوظًا وقد يعيد NEEDS_CONNECTION رغم غياب قبول Android للمفتاح. السبب الدقيق لفقد المفتاح غير مثبت.

شاشة الإعداد الحالية لا تعرض زر إعادة الاقتران، وStart fresh موجود بعد READY فقط. الإجراء المتاح دون إعادة تثبيت: مسح بيانات تطبيق الاختبار وحده (يمسح إعداداته وملف الصوت المستورد محليًا)، ثم إعداده واقترانه بكود جديد في الإشعار، والتحقق من ظهور جهاز في قائمة Android. هذه إرشادات لم تنفذ على الهاتف بعد. ينبغي إضافة زر إعادة الاقتران إلى شاشة التعثر في نسخة لاحقة. لا صوت SIM مثبت.

## أحدث الصور: متصل لكن رفض صلاحية جديد — 30 أكتوبر2026

ظهر Currently connected في قائمة Android، مع USB debugging ومفتاحSecurity settings مفعّلين ورفض جديد18:12:24. الاقتران ليس العائق الحالي؛ لا مسح بيانات/إعادة اقتران متكرر. نجهز0.1.1 لإظهار وحفظ رد pm grant الحقيقي وقراءة الصلاحية بدل النص العام، مع تشخيص صريح للمستخدم الحالي وزر re-pair يحفظ الملفات عند الحاجة. لا نستنتج سبب رفضAndroid حتى يرد التقرير. توقيع0.1.0 كان مؤقتًا؛0.1.1 يبدأ حفظ هوية توقيع debug في private Actions cache للتحديثات اللاحقة، ولا تُرفع private keys إلىGit. لا اختبار صوت بعد.

## نسخة تشخيص0.1.1 مبنية ومتحقق منها

البناء 41088969144 نجح: اختبارات PCM وأوامر التشخيص وإلغاء الاختبار، وتجميعAPK وفحص التوقيع. نزّلت الحزمة وطابقت SHA ومصدرoverlay ووجود كلاس التشخيص. الدليل docs/mobile/DIAGNOSTIC_BUILD_0_1_1.json. الرد الخام من الهاتف لم يصل بعد؛ المشكلة لا تزال OPEN.

النسخة تعرض وتحفظ grant/read-back الحقيقيين، وتجرب منحة التطبيق للمستخدم الحالي صراحة، وتوفر إعادة الاقتران مع حفظ الصوت. لا إعادة لمسح بيانات باعتبارها علاجًا للصلاحية؛ إزالة0.1.0 مطلوبة لتثبيت0.1.1 فقط بسبب اختلاف debug signer. مفتاح0.1.1 محفوظ في private Actions cache (مؤكد منlog) لكنه ليس backup دائمًا ولا مفتاحproduction. الخطوة: تقريرالزر الجديد من الهاتف، دون تغيير مفاتيح أمان إضافية. لا صوت SIM مثبت.

## 2026-11-01 — Hourly cycle #3: archived-resource API access hardening, PR #10

- **Problem reproduced from code:** API bearer keys could enumerate archived sites/agents, retrieve archived agent knowledge, and write new leads to archived sites despite UI archive restrictions. Scope: API lifecycle/tenant boundary only; full Trash/Restore remains OPEN.
- **CODED + MERGED:** PR #10 https://github.com/OnTrack-Development/OnTrack-voice/pull/10, squash main `04e5851ed960a82fff7f6b3f7655cccfed7c33f6`. Changes: `saas/console/api/v1/index.php`, `tools/test_voice_api_http.php`, smoke wiring and `docs/execution/2026-11-01_API_ARCHIVE_CYCLE_03_AR.md`.
- **CI_PASS:** PR run `41292501176` success (latest PR head), post-merge `41292532680` success. Real API HTTP regression runs against local seeded SQLite, tests archived sites/agents/knowledge, active read/write, revoked keys, cross-tenant isolation, no write on archived site.
- **HOST_DEPLOYED VERIFIED:** publish workflow `41292532708` success and Cron receipt `ops/host-link/results/appontrack-server11/saas-console-41292532708.json` success, exit_code 0, finished **2026-11-01 09:19:04 UTC**, source `04e5851ed960`, **21 files**, PHP 8.3, seed `already_seeded` (9 users), backup=true. No authenticated UI/device/payment E2E in this run.
- **No collision:** open SSO PR #9 (head `e67257ad7827b1b66823af665670e260f6205bc3`) was left untouched and unmerged; it requires staging/security checks before production.
- **Independent HTTPS probe limitation:** attempted unauthenticated `curl` from this execution container to the public console API; DNS resolution for `app.ontrackegy.com` failed (`curl: (6) Could not resolve host`). This is an execution-environment limitation, not evidence the site is down; authenticated/browser API E2E is still OPEN.
- **Next:** verify release workflow + host receipt, then continue verified registration/SSO (PR #9) or independent 2FA/Trash work after checking current PR state.
