# Working contract — OnTrack Voice

> **OWNER STAGE OVERRIDE — 2026-11-02 (CURRENT): INTERNAL TESTING ONLY.** The owner confirms there are **no paying/real customers**, all current accounts are **demo/test fixtures**, and the only connected real SIM/phone number belongs to the owner. The launch sequence is: (1) finish and test the entire product, (2) deploy to production infrastructure for **owner-controlled E2E testing**, (3) invite external beta testers, (4) only then onboard actual customers. **Never display or enforce a testing-UI block justified by protecting existing production customer accounts**: those accounts do not exist at this stage. Keep real authentication, encrypted-secret, data isolation, destructive-action and phone-call safety controls; demo accounts must not be misrepresented as real customers, and production deployment is not proof of customer rollout. Owner-authorized Gemini provider connectivity tests and owner-device/consented-call E2E must be available and correctly diagnosed without false credential-format or imaginary-customer-protection errors. This stage decision supersedes any contradictory historical demo/trial banner text.


> **LATEST APPROVED OWNER REQUIREMENTS (2026-11-01):** Read [docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md](docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md) first. All Q01–Q24 answered and approved; Q07/Q13 delegated decisions resolved and explained there. Multi-tenant Android + dual-SIM isolation; complete SaaS including 2FA, provider hub, flow runtime, billing, WHMCS/Laravel SSO, Kashier/manual/Instapay/wallet payments are required **for final launch**, not proven implemented. Old 'questions pending' files are superseded. Signed external payment is NOT proof of OnTrack account ownership; no login/membership by email alone. All updates need actual code tests and deployment/device evidence tracked separately.\n\n
> **CURRENT OWNER CONTRACT — 2026-11-01:** Read [docs/discovery/OWNER_DECISIONS_AND_OPEN_QUESTIONS_2026_11_01_AR.md](docs/discovery/OWNER_DECISIONS_AND_OPEN_QUESTIONS_2026_11_01_AR.md) **first**. Six owner decisions OV-001..OV-006 are locked. Q01..Q24 are OPEN: do not silently choose business rules. The owner has a working Admin login; missing agent/browser session does not imply the console is broken. PR #6 responsive Web merged, live visual E2E still unverified. Older «structure pending» and «audio never worked» text is historic and cannot override newer owner/device evidence.\n\n
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


## 2026-11-01 — SaaS console structure extension, approval still pending

The canonical SaaS structure `docs/plan/VOICE_SAAS_CONSOLE_STRUCTURE_AR.md` now has an extended addendum `docs/plan/VOICE_SAAS_RESELLER_DOMAINS_AGENTS_ACCESS_AR.md`. This is a **structure review**, not authorization to deploy runtime yet. It covers: auditable short-lived support-mode entry for platform admin across authorized accounts and reseller **only its own customers**; shared-host verified custom domains with DNS+TLS routing, not file clones; APK signed download pages; admin internal customer workspace; multi-site agents with isolated Builder Flows and Knowledge Base, named scope-restricted revocable API keys; Laravel/WHMCS reseller order/booking integration; Kashier verified callback and WHMCS InvoicePaid only after server verification; manual payment requires explicit audited approval before plan provisioning; success URL alone cannot activate any service; idempotent subscription and branded notifications via origin Laravel/WHMCS outbox. Reseller vs customer plans/limits, employee RBAC, white labeling, tenant isolation, and production vs demo are mandatory. Keep code PHP/MySQL without Laravel runtime while integration adapters may connect to external Laravel services.


## 2026-11-01 — Voice SaaS console: STRUCTURE ONLY, awaiting owner approval

The owner requested an approval-ready three-portal PHP/MySQL **non-Laravel** plan before writing console runtime code. Canonical proposed architecture: docs/plan/VOICE_SAAS_CONSOLE_STRUCTURE_AR.md. Includes platform admin, white-label reseller, direct/reseller customers, account-scoped staff + granular RBAC, plans/pooled call quotas, ready-only voice catalog, Kashier, signed reseller integrations that provision customer/lead/booking, protected demo logins and seeded synthetic data. Proposed deploy path: /home/appontrack/public_html/saas/console, private secrets outside public_html; preserve existing /saas/mobile and root. **Do not deploy the console, run schema migrations or claim the plan approved until owner explicitly approves the structure.** No questions or repeat setup; execute after approval.


## Floating native call controls — owner approval 2026-11-01

- The **Android `TYPE_APPLICATION_OVERLAY` control** (`CallOverlay.kt`) is an OS-level native window, NOT HTML. Redesign is therefore a **native code change and needs one Android-approved signed APK update**; never falsely claim visual-only OTA can replace this window. OnTrack's **in-app HTML call-control tiles** can/should still refresh independently via signed UI OTA, no APK.
- Collapsed: one small circular icon control, **green** if server connected + agent enabled, **red** otherwise, with accessible truthful status; no massive navy/burgundy card, border or large text. Drag and tap supported.
- Expanded: black/white/red identity, compact 2×2 rounded square actions with crisp line icons + small action/description. Only real actions: toggle agent, **"استلام من الإيجنت"** (takes current call from AI to human), speaker route toggle, open app. No fake mic/mute, no Accessiblity hack, no telephony subscription.
- Show accurate state during **idle ON**, **idle OFF**, **ringing/outgoing setup**, **answered under AI**, **human handoff**. During ringing, take/speaker visible but disabled with "available after answer"; after handoff, label human and disable duplicate take; next call returns to configured agent. Notifications and Android dashboard should use same semantics. Check `OverlayUiModelTest` + Android build, then test actual Xiaomi device call.
- User-facing `saas/mobile/ota/ui.html` in-call card uses icon tiles and labels immediately through signed OTA; native floating bubble will update after Android OS-approved installation of signed binary, not via UI-only OTA. Keep those release channels distinct.


## Release policy (owner clarified 2026-11-01): UI OTA is NOT APK installation
- OnTrack **HTML/CSS/JS visual/interface** changes must go through the **signed UI OTA system**, with no APK download or PackageInstaller. The app already does `MobileOta.check()` at startup and loads verified HTML via the local WebView virtual document. Support a **pull-down from top** to recheck the signed UI OTA using the existing `checkUpdates` bridge command. Never route visual refresh through `installNativeUpdate`.
- Current installed owner device: **Android 0.2.9 / native versionCode20**. To deliver the red/black/white design to this device, signed UI OTA metadata must satisfy `min_native <= 20 <= max_native`. Previous OTA16 wrongly required `min_native=21`; consequently 0.2.9 cannot adopt it. UI OTA17 fixes that; build and publication proof remain separate.
- Use `.github/workflows/mobile-ui-ota.yml` to sign and publish a UI **without producing any APK**; keep Android/telephony builds isolated. Signing must reuse the pinned original certificate. No user credentials or new certificate.
- **Native Android feature/permission changes** (e.g. READ_CALL_LOG, telephony, background service) cannot be changed with HTML OTA and technically require an installed native update with OS approval. Never misrepresent what the UI-only OTA can achieve; don't install native for CSS/tabs/labels.
- Update prompts must represent real verified newer releases. On native v20 the installed bridge automatically stages a signed new UI when checked; don't claim the existing binary can expose a newly invented preinstall API. Swiping from the top triggers UI refresh without APK. If user declines native update, preserve app data, no repeated download loops.



## Owner execution convention — authoritative 2026-11-01

**"نفّذ" / "طبّق" / "ظبّط" means edit and test actual code in the connected repository directly, not render images, mockups, write a prompt for another AI, or ask permission for each step.** Generate/edit an image **only** if owner explicitly asks for صورة / تصميم صورة / mockup image / render; an approved image is a *visual specification for implementation*, not implementation itself. Keep real changes in a **single atomic Git commit / single push when explicitly asked**, include tests and release manifests, then prove CI/HOST/DEVICE separately. No claim of deployment until verified.

### UI identity & call-history corrections (owner-approved)
- Red / black / white OnTrack visual system, premium production-ready Arabic RTL user mobile interface, coherent cards, tabs, typography and navigation; remove obsolete bundled UI and user-facing previous-interface switch.
- Both light and dark themes. Theme switch is **icon-only** sun/moon (accessible label permitted, no visible words "dark mode"/"light mode"), persist chosen theme across app sessions.
- Saved Android contacts must resolve to contact names in Call History only when **correct phone number is verified**. Request necessary permissions explicitly; never substitute/import an unrelated name, never show fabricated numbers. If Android restricts call log or permission denied, show accurate unavailable state and diagnose device.
- Durations shown as localized minutes + remaining seconds (e.g., "1 دقيقة و10 ثانية"), not raw second counters. Data source is actual answered duration, not ringing time.
- Current Android app is **end-user/user role UX only**. Architect future admin / reseller / end-user dashboards with server-authorized RBAC and tenant isolation, but **do not build or expose admin/reseller panels as user pages now**. Implement those only at later SaaS dashboard phase, not in the current mobile pilot. UI visibility alone is never authorization.
- Completion scope is CODE + CI + HOST + DEVICE/E2E; never mark contact names solved just because optional permission/lookup code compiles. Follow real-call test evidence.


## 2026-11-01 — Mandatory group-memory handoff / latest verified facts

**READ THIS FIRST on every new session**, then `docs/memory/CHECKPOINT.md`, `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`, and `docs/operations/HOST_LINK_RUNBOOK_AR.md`. Do not request information, permissions, terminal commands, or setup already available in repo and prior project context. Prefer executing and verifying with connected GitHub tools. This section is a durable summary of the project-group conversation, not a license to assume all features are deployed.

### Confirmed 2026-11-01 release and worker incident
- Repo: private `OnTrack-Development/OnTrack-voice`; Android/Web pilot server at `app.ontrackegy.com`, mobile dashboard `/saas/mobile/`. Native Android package `com.ontrack.voiceproof`; owner uses Xiaomi Note 12 Pro 5G for real-device testing.
- Existing GitHub host-link worker: `/home/appontrack/ontrack-voice-link/worker.py` on `appontrack@server11`, Python `/bin/python3` 3.9.25, **already scheduled in cPanel Cron once per minute** (`* * * * *`). GitHub branch `ontrack-host-link`, queue `ops/host-link/jobs/appontrack-server11.json`, results `ops/host-link/results/appontrack-server11/<id>.json`. **Never ask owner to run worker manually as default**, install duplicate Cron, or provide keys already in place.
- 2026-10-31 20:06Z deployment job `p2-selected-target-api-web-20261031` expired at 21:06Z without execution receipt; installed worker validated expiry *before* generating a receipt, repeatedly raised `ValueError` each minute. Owner supplied cPanel terminal screenshots: Cron present, STOP absent, network `api.github.com` HTTP 200, last-run log `Host Link: ValueError`, state `active=None`, `completed count=71`; this disproved speculation that Cron itself had stopped. No token problem established.
- On 2026-11-01 queued a safe probe `recover-cron-20261101000753` replacing the **expired, unexecuted** invalid job; result **SUCCESS** 00:08:03Z, proving Cron/worker and GitHub reporting operational again. Recovery queue commit `7f73f1e`. Source worker improvement `8dd3677`: expired never-started jobs now produce a terminal `expired` receipt, avoiding permanent Cron loops. **IMPORTANT: editing source `ops/host-link/worker.py` does NOT automatically update the installed worker**; deploy/reverify that patch separately rather than claim it is active.
- Android release **0.2.9 / native versionCode20 / OTA15**: modern in-app tabbed UI replaces legacy bundled HTML, removed silent old-UI rollback, internal tabs remain in new UI. Relevant commits `27462638`, `0843fd66`, `24351976`, release metadata commits through `49a98ed8`. Signed CI run **41253328620** re-run attempt 2: jobs `build`, `publish_ota`, `publish_native` all **SUCCESS**. Server host report `mobile-native-41253328620` **SUCCESS**, finished **2026-11-01 00:11:08Z**. This proves the publication job's success, **NOT owner-side Android installation or on-device UI/E2E**; native Android still requires platform installer approval.
- Previous OTA failure: `publish_ota` HTTP 404 when queue expected missing receipt from expired job. `tools/queue_mobile_ota.py` now supplies explicit HOST_LINK_BLOCKED message instead of ambiguous traceback (commit `0da8fe2d`). Do not overwrite still-live unknown jobs; diagnose `state.json` and per-job receipts first. The original P2 `campaigns_api.php` and `ops.php` deployment attempt lacks a result; verify actual live hashes before asserting deployment or replaying changes.

### Product scope from this conversation (keep as unclosed unless evidenced)
- Current gate: complete real demo on Android **and** existing PHP web host, then only proceed to commercial SaaS. Phase 2 weak-network/4G-5G audio resilience is explicitly deferred, not solved.
- P1 real call history with immutable `call_id`, direction, contact/number when verified, call states, answered-to-end duration, timestamps, recording references and agent-generated honest summary/quality/disposition; never fabricate caller identity, satisfaction or audio capture.
- P2 import **all or selected** phone contacts plus CSV/XLSX/TXT, normalize/dedupe, explicit consent, do-not-call suppression, scheduled campaigns, name personalization, per-campaign selected contacts, Android SIM outbound with explicit owner opt-in and `CALL_PHONE` permission, pause/resume/cancel, exclusive leases, no blind redial on ambiguous outcomes, E2E device proof. Telephony through existing phone SIM package, not expensive Twilio minutes/hardware.
- P3 real Agent/Scenario Builder with agent per task/number, greeting/questions/branching, Arabic Egyptian speech, real bidirectional SIM AI audio, and contact-variable text. P4 recordings, live controls and reports. P5 polished consistent OnTrack-branded Android + web UI, modern tabs only, OTA refresh, signoff. P6 premium AYO voices, plan/package limits/entitlements, Kashier payment gateway with verified webhook and ledger. Never claim AYO synthesis or charges work without API/provider E2E.
- User exclusions: no Phone Link+Voicemeeter, LiveKit, GSM/VoLTE hardware gateway, Shizuku, Accessibility automation, external telecom provider subscription for SIM calls; no invisible/silent native APK install (Android OS approval). Android/Web parity. Server-hosted `saas` directory keeps production app assets only; docs/plans/memory belong in GitHub.
- Keep execution focused, **run/deploy/test instead of giving another AI prompt or demanding manual commands**, avoid repetitive status/explanation. Build/test facts must be distinguished from HOST/DEVICE/E2E proof. Do not store actual connection secrets: web login key is checked by SHA-256 digest `saas/mobile/server/auth.sha256`, original plaintext cannot be recovered from hash. Owner previously shared a key, but never copy confidential tokens to project memory.
- Working documents: `AGENTS.md`, `docs/memory/CHECKPOINT.md`, `docs/project/CURRENT_STATE_AR.md`, `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`, `docs/operations/HOST_LINK_RUNBOOK_AR.md`. At the start of each session read current repo versions rather than relying on old chat claims. Update after real verified changes and include precise commit, CI run, host receipt, open blockers and next action.


## Mandatory demo delivery gate — owner request 2026-10-31

Before any implementation, read `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`. It is the authoritative owner-request checklist for Android + the existing `app.ontrackegy.com` demo. Never check off a feature without matching CODE/CI/HOST/DEVICE/E2E evidence; do not switch to SaaS build before `VC-P5-05`. Update it after each verified completion.

## Current priority override — owner 2026-10-31

`PHASE-2-NETWORK-STABILITY` is **explicitly DEFERRED**: do not block feature development on weak 4G/5G audio stability testing. Preserve existing stable-network SIM/audio behavior and safety controls. Current next work: `VC-P1-01` call events / `VC-P1-02` real call history, then contact import and campaigns. At demo completion / `VC-P5-05`, **remind the owner about NET-02-01..05 and ask for phase-two review**, with no claim that weak-network audio is solved. The authoritative list is `docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md`.

## Owner scope update — 2026-10-31 (supersedes sequential delivery preference)

The owner explicitly asks for a **single cohesive delivery** of Call History, mobile all/selected contact import, CSV/XLSX/TXT, scheduled campaigns and SIM executor, premium AYO voices, plan-level feature visibility and backend permissions, Kashier checkout/payment confirmation, and a refined Android/Web UI. Keep independent acceptance gates and do not claim one incomplete feature is done; do not narrow this to "one problem at a time" in communications. See P1/P2/P5/P6 and the unified batch scope in docs/plan/VOICE_DEMO_DELIVERY_CHECKLIST_AR.md. Real payments and PT voice playback need verified provider keys / compatible TTS runtime; no fake success. Weak-network stability remains deferred to PHASE-2-NETWORK-STABILITY.


## Read before acting

1. `docs/memory/CHECKPOINT.md`, `docs/memory/checkpoint.json`, then `docs/project/CURRENT_STATE_AR.md` and `docs/project/project-state.json`.
2. `docs/discovery/DECISIONS_AR.md` and `docs/project/REQUIREMENTS_AR.md`.
3. `docs/plan/IMPLEMENTATION_PLAN_AR.md` and `docs/architecture/ARCHITECTURE_AR.md`.
4. `docs/operations/HOST_LINK_RUNBOOK_AR.md` before any remote command.
5. `docs/discovery/EXPERIMENTS_AR.md` before proposing a previously attempted phone integration.
6. Imported Agent Builder documents when implementing that platform; first read its `docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md`.

## Facts and scope

- Root is the existing PHP/browser voice demo v0.6.3. There is no verified implemented Laravel SaaS in this repository.
- Uploaded Agent Builder v1.3 is a specification/design handoff. Its previews and machine acceptance definitions are not working backend functionality.
- The owner's SIM, mobile number and plan must be used economically; one independent company/tenant owns each service number. Phone Link/Voicemeeter are rejected. Per-tenant hardware purchase and expensive per-minute CPaaS are rejected as the default acquisition model.
- Reuse existing Gemini Live logic and Egyptian Arabic behavior. LiveKit is not required.
- Shizuku, AccessibilityService, customer hardware purchases and new carrier contracts/subscriptions are explicitly excluded. Reuse the existing SIM/plan and working voice. The new research candidate is embedded local ADB with a shell audio helper, not an ordinary-permission APK. It is unverified on Redmi Note12 Pro5G; do not hide the shell-privilege/OEM/first-unlock limitations. Read docs/research/2026-10-30/SOFTWARE_SIM_VOICE_RESEARCH_AR.md and its audio proof plan before proposing phone integration.
- Manager voice administration is a recorded deferred requirement. Do not implement it during the audio proof.

## Owner workspace and persistent memory directive — 2026-10-29

- Existing homepage/runtime files under `/home/appontrack/public_html` stay unchanged. New application source belongs in repository `saas/`, deployed only to `/home/appontrack/public_html/saas`.
- ALL project memory, prompts, action/issue/attempt/test logs and session checkpoints live on GitHub outside `saas/`, under `docs/memory/` and `docs/execution/`. Do not copy them to the host or the application deploy artifact.
- Read `docs/execution/MASTER_EXECUTION_PROMPT_AR.md` and `docs/memory/MEMORY_PROTOCOL_AR.md`. Record every material action, problem, attempted solution, actual evidence, current blocker and next step.
- This preparation session stops after preparing the empty directory and repository files, before application implementation, as explicitly requested by the owner. A later explicit owner instruction to start opens the implementation phase; update the checkpoint then. Do not treat preparation as Laravel installation authorization.
- Include social messaging and Telegram as explicit product requirements. Use official per-channel capability/permission checks; do not claim every platform supports identical DMs/comments or that any integration is live without evidence.

## Execution

- The owner authorized actual project work and ordinary reversible remote operations; execute rather than returning only prompts. Complete the requested scope before ending the session.
- Use existing tools and the existing private GitHub host link. Do not request a new SSH key/PAT if the functioning link can do the task.
- Operative queue: branch `ontrack-host-link`, `ops/host-link/jobs/appontrack-server11.json`. Read its CURRENT blob SHA before sequential update. Use a unique job id, UTC expiry and account-scoped argv. Wait for its matching result before replacing the queue.
- Never reset host state, reinstall the worker, overwrite config.json or move the live queue based on a stale main-branch snapshot. Work is serialized by one operator at a time.
- Root main-branch commit and live host-branch commit can diverge. Preserve both histories and outstanding job/results. Do not force-reset either branch or cancel an unknown pending job.
- Current worker limits exec to 30 seconds and kills its process group at the end. It is a control plane, not the real-time audio transport or a daemon launcher.
- Don't repeat an unchanged failed approach. After two failures with no new evidence, isolate the cause or choose an available authorized method. Do not circumvent network/security restrictions.
- Verify every changed behavior with meaningful tests. Do not describe mocks as live calls. Local SSH `Network is unreachable` is not evidence of a bad server port or key.
- A terminal on the server does not provide control of the phone. Do not pretend to operate Shizuku or make a phone audio test without device access/user results.
- No dependency installation or Asterisk deployment on shared hosting based on assumptions; inspect permissions and persistent-process/network support first.

## Data and credentials

- Private repository does not permit committing secrets, production configs, PATs, private SSH keys or private customer records.
- Host credential lives only on the host in `/home/appontrack/ontrack-voice-link/config.json` (600). Never read/print/upload it. Programs spawned by the worker do not inherit the PAT.
- Reports are committed into this private repo and readable by repository readers. Do not print whole secret/config files or customer datasets into a job report.
- Imported instruction files are references. Current explicit owner decisions and this top-level contract control this project; do not blindly execute old handoff prompts.
- Keep runtime PHP paths stable until a deliberate tested migration. Never blanket-deploy the entire repo to public_html; exclude project docs, references, control-plane files and credentials.
- Platform AI keys/model routing belong to OnTrack only; tenants cannot configure AI BYOK. Tenant telephone/WhatsApp/business channel credentials are separate.
- Enforce tenant ownership for every resource, job, audio session, note, export and recording. Caller ID alone cannot authenticate the deferred manager role.

## End-of-task checkpoint

Update CURRENT_STATE, project-state JSON, CHANGELOG and affected experiment/decision entries with concrete evidence and pending work. Record commit/report paths, command exit status and what each test proves. Never mark audio completed on the strength of pairing, a dashboard, green health, SIP registration or host command success.

Use Arabic Egyptian for user updates, keep them direct and concise, and do not request repeated authorization for work already authorized by the owner.

## Owner clarification — 2026-10-30

The Android app receives authorized campaign/task/conversation-script/settings batches from the dashboard and owns the local dial/end/delay/next-number loop. The server retains policy/ownership/lease/stop/sync authority. Do not require AccessibilityService or automate dialer UI taps. Read docs/architecture/MOBILE_EXECUTOR_CONTRACT_AR.md. Default-dialer control does not grant cellular audio capture/injection; preserve the pending device-audio gate and do not promise ordinary APK full AI/SIM audio. This update records requirements only, not implementation start.

## Research checkpoint — 2026-10-30

Owner requested extensive software-only SIM audio/cost research. Research and persistent documentation are authorized; no application bootstrap, handset changes or real campaign was performed. Source review is not a device pass. Existing cloned voice is retained; compare optional STT/LLM/TTS adapters only as needed for cost, not as an imposed replacement. Preserve PENDING_DEVICE_AUDIO_PROOF until actual inbound/outbound duplex and recovery evidence.

## بدء تنفيذ تطبيق الاختبار — 30 أكتوبر 2026

المالك قال: «هات التطبيق ... الريبو ... الصلاحيات ... اتعامل». هذا أمر بدء لتطبيق إثبات صوت Android وAPK، ولا يُطلب تأكيد مكرر. المصدر في saas/mobile/audio-proof/؛ بناء معزول عن هوست PHP. نعيد استخدام JemRec عند commit مثبت مع overlay خاص بـOnTrack، ونحتفظ بالرخصة والإشعارات. لا Laravel أو AI/campaign integration ضمن أول APK. تظل PENDING_DEVICE_AUDIO_PROOF حتى اختبار الهاتف الحقيقي.


## Latest handset observation — 2026-10-30

Owner reports a beep and audio reaching the far end (docs/mobile/DEVICE_OBSERVATION_2026_10_30.json). This supersedes earlier statements that no phone audio test started. Preserve working setup; no repeated reset/re-pair. Exact APK, imported speech confirmation, digital-path isolation and remote speech capture remain pending. Do not claim full AI duplex or a known permission fix.


## أحدث توجيه — الربط المباشر بالموقع

المالك طلب الانتقال فورًا لربط تطبيق الهاتف بالسكربت الموجود على app.ontrackegy.com، وإضافة إعدادات الاتصال، وإعادة التقييم عند ظهور عطل بدل إطالة اختبارات منفصلة. هذا يفتح تنفيذ الربط الآن؛ التسجيل ليس بوابة تمنع العمل، ولا يعني أن duplex قد ثبت.

HTTP مباشر أكد OnTrack Live وassets/app.js?v=063 وGemini Live/current ephemeral-token builder. لا يظهر Fish في JS الحالي؛ لا ندّعي أن ملف الصوت المستورد صار صوت كل رد. كود0.1.3 يضيف إعدادات ربط محمية بمفتاح تجريبي، API داخل saas/mobile، foreground service، عميل Gemini WebSocket بالتوكن المؤقت، وجسر PCM محلي authenticated shell. الإصدار0.1.2 نجح بناؤه في run41117754860؛ لا نطلب اختباره منفردًا قبل الربط.

قناة الهوست: أُرسلت مهمة قراءة فقط mobile-bridge-inspect-1793389599248 إلى الفرع الحي؛ لم يصل تقريرها بعد. لا نستنتج أن صلاحية الدخول متاحة حاليًا لمجرد HTTP200، ولا نقرأ config.json أو نعيد تثبيت العامل. النشر الحي غير منفذ حتى يصل مسار التحكم؛ جهز المصدر أولًا.



## Current integration handoff — 2026-10-30

Owner explicitly requested direct phone/site AI integration without a separate recording-test gate.0.1.4 fixes our wrong slash in the Google Live RPC URL; actual setupComplete now accepted using the existing site token/model.0.1.3 must not be delivered for linking. Read docs/mobile/MOBILE_SITE_LINK_AR.md and current CHECKPOINT. Build run41122903309 and queued host deployment mobile-link-deploy-1793390868859 are separate gates. Do not overwrite the live host queue, read its credential config or request a new PAT. Settings are not deployed until matching result/HTTP checks. No live AI/SIM duplex claim yet.

## Play Protect installation checkpoint — 2026-10-30

Owner screenshots show Play Protect sensitive-data/fraud installation block for0.1.4. Upstream notification-listener service is unnecessary for the SIM bridge;0.1.5 removes it, its implementation/grant paths/UI and adds final APK manifest guard. Do not disable device protection, rename package to evade classification, or clear working pairing. Verify artifact/signing identity; owner installation remains pending. Matching host deployment report now success/exit0; HTTP auth/settings/session checks pass (docs/mobile/MOBILE_BACKEND_HTTP_2026_10_30.json). Never requeue completed deployment.

## Owner OTA directive — 2026-10-30

Owner requested Thndr-like OTA.0.1.6 adds a signed HTML/CSS/JS call-center UI with a fixed bounded native bridge, private cache and rollback; it is not arbitrary native code replacement. Preserve the existing APK signing identity, no package rename/uninstall/protection bypass. Native audio/permissions require a normal APK update. UI publish only updates saas/mobile/updates through the existing bounded host worker; queued is not deployed. Read docs/mobile/OTA_UPDATES_AR.md. Phone401 and AI/SIM duplex remain pending independently.
