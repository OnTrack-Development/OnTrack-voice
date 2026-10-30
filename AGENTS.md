# Working contract — OnTrack Voice

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
- Android/Shizuku is a candidate, not a verified solution on the owner's Redmi Note 12 Pro 5G. Do not declare it compatible or commercially ready without real device evidence.
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
