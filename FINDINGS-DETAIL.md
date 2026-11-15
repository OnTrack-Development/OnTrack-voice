# تفاصيل الثغرات المؤكدة (Medium / High / Critical)

## 1. Release gate pins a fixed historical run/branch and asserts a version lineage that contradicts the repo's committed OTA metadata

- Fingerprint: release-verified-mobile.yml:await_signed_build:pinned-run-sha:stale-version-assert
- الشدة: HIGH | الثقة: high
- السبب الجذري: Release authorization is split across three divergent pinned refs (5f3d682a in release-verified-mobile.yml, 59007a4e in native-call-reports-release.yml, f011062a upstream in android-audio-proof.yml) and the gate's hardcoded version assertions (r28/vc31/0.3.10-ota) are not derived from the committed release.json/native_release.json (r29/vc32/0.3.9). No workflow reads those JSON files to set the expected version at publish time.

### مسار المصدر (Trace)
- [entrypoint] .github/workflows/release-verified-mobile.yml:26 - publish gate - await_signed_build hardcodes source_run=41392198086 / source_sha=5f3d682a instead of the current main HEAD.
- [propagation] .github/workflows/release-verified-mobile.yml:37 - publish gate - Asserts run head_branch=='fix/outbound-callee-first-v039-20261102', a non-main feature branch, so the gate validates an old historical build.
- [sink] .github/workflows/release-verified-mobile.yml:78 - publish assertion - Asserts apk version_code==31 and version_name=='0.3.10-ota', contradicting native_release.json (vc32/0.3.9) and prepare.py (vc32/0.3.9).
- [propagation] saas/mobile/ota/release.json:1 - authoritative metadata - Committed OTA metadata is revision 29 / max_native 32, not the revision 28 the gate asserts.

### الادلة
- .github/workflows/release-verified-mobile.yml:77 - assert ui['revision']==28 and ui['max_native']>=31 conflicts with saas/mobile/ota/release.json revision 29 / max_native 32.
- saas/mobile/ota/native_release.json:1 - version_code 32 / version_name 0.3.9 conflicts with the gate's apk version_code==31 / version_name '0.3.10-ota' assertion.
- saas/mobile/audio-proof/prepare.py:52 - versionCode = 32 hardcoded in the build overlay, matching native_release.json and not the gate's expected 31.

### الاصلاح المقترح
- Derive expected revision/version_code/version_name from checked-out saas/mobile/ota/release.json and native_release.json; replace the hardcoded run-SHA/branch check with a comparison of the source run's head_sha to current main HEAD (or to the checkout ref). Consolidate the parallel release pipelines onto one pinned ref.

## 2. Static maintenance token committed in repo gates live config rewrite and outbound diagnostic proxy

- Fingerprint: gemini.live_setup_demo.static_maintenance_token
- الشدة: HIGH | الثقة: high
- السبب الجذري: Shared secret is a compile-time constant (api/setup_demo.php:6, api/diag.php:6) and its plaintext is committed in UPLOAD_TO_ROOT.txt:8; no rotation, no per-deployment secret, no proof-of-possession bound to the live deployment.

### مسار المصدر (Trace)
- [entrypoint] api/setup_demo.php:14 - web - Reads token from HTTP_X_MAINTENANCE_TOKEN / POST / GET
- [propagation] api/setup_demo.php:15 - web - hash_equals(TOKEN_HASH, sha256(token)) with constant at line 6
- [sink] api/setup_demo.php:43 - web - On pass: writes api/demo_config.local.php with attacker-controlled gemini_api_key, chmod 0600
- [sink] api/live_session.php:135 - web - live_session.php consumes the overwritten gemini_api_key to mint tokens
- [entrypoint] api/diag.php:37 - web - Same token gate enables server-side outbound fetches
- [sink] api/diag.php:63 - web - Outbound network fetch to Google/HuggingFace with response reflection

### الادلة
- api/setup_demo.php:6 - const TOKEN_HASH hardcoded
- api/diag.php:6 - const TOKEN_HASH identical in diag
- api/setup_demo.php:14 - token accepted from header/POST/GET
- api/setup_demo.php:43 - writes demo_config.local.php in webroot with attacker-supplied key
- UPLOAD_TO_ROOT.txt:8 - committed plaintext maintenance token (value not printed per secrets discipline)
- .htaccess:2 - FilesMatch denies web read of demo_config.local.php but only on Apache honoring .htaccess

### الاصلاح المقترح
- Remove the plaintext token from UPLOAD_TO_ROOT.txt and rotate it immediately. Replace the static TOKEN_HASH with a deployment-scoped secret injected from outside the repo (env/secret store), never committed. Move demo_config.local.php out of the webroot or confirm web-server deny rules for all server types. Delete setup_demo.php/diag.php from production or require a separate, non-repo admin credential with audit logging.

## 3. Plaintext maintenance token committed at web root and reused as the static gate for config-write and outbound diagnostic endpoints

- Fingerprint: UPLOAD_TO_ROOT.txt:maintenance-token:TOKEN_HASH:api/setup_demo.php:api/diag.php:root-htaccess-denylist-gap
- الشدة: HIGH | الثقة: high
- السبب الجذري: A static, repo-committed maintenance secret is reused verbatim as the authorization gate for two privileged public endpoints instead of a rotated, host-only credential, and the web-root deny-list is an explicit small filename allowlist that does not cover the file holding that secret.

### مسار المصدر (Trace)
- [entrypoint] UPLOAD_TO_ROOT.txt:8 - repository/deploy artifact - Plaintext maintenance token is committed in the repo and, given the repo-root deploy model, is web-servable as /UPLOAD_TO_ROOT.txt because .htaccess:2-4 denies only config.php|demo_config.php|demo_config.local.php|knowledge.json|README_AR.md|version.json.
- [propagation] api/setup_demo.php:15 - request auth - hash_equals(TOKEN_HASH, sha256(token)) accepts the committed token as the accepted credential; no other auth, no rate limit.
- [sink] api/setup_demo.php:44 - filesystem write under web root - file_put_contents writes api/demo_config.local.php (var_export of an attacker-influenced gemini_api_key and model) under the web root; the file is then loaded by api/demo_config.php:9-13 and used by live_session.
- [propagation] api/diag.php:38 - request auth - Same TOKEN_HASH gate accepts the committed token for the diagnostic endpoint.
- [sink] api/diag.php:63 - outbound network with server key - curl_req performs POSTs to generativelanguage.googleapis.com with the server's x-goog-api-key and GETs to huggingface.co/HF-Space endpoints, returning body previews (lines 96-133) — host egress proxied through a repo-known secret.

### الادلة
- UPLOAD_TO_ROOT.txt:8 - Literal maintenance token committed in plaintext.
- api/diag.php:6 - TOKEN_HASH constant whose value equals sha256 of the committed token (verified out-of-band: sha256 of the UPLOAD_TO_ROOT token = 60a11e1f...fbb67f7, byte-identical to this constant).
- api/setup_demo.php:6 - Same TOKEN_HASH constant, reused as the only gate before writing demo_config.local.php.
- api/setup_demo.php:44 - Attacker-controllable gemini_api_key written to demo_config.local.php under the web root.
- .htaccess:2 - Deny-list FilesMatch covers only six exact names; UPLOAD_TO_ROOT.txt (and *.txt generally) is not denied at the root.

### الاصلاح المقترح
- Remove the token from UPLOAD_TO_ROOT.txt and from the repository; rotate the maintenance secret and replace the hardcoded TOKEN_HASH with a value derived from a host-only credential (private file outside the web root, or env var) that is never committed; purge the token from git history; require the diagnostics/setup endpoints to be disabled outside explicit maintenance windows; add explicit deny for *.txt/secret filenames (or move UPLOAD_TO_ROOT.txt out of the deploy tree) in the root .htaccess.

## 4. Support-mode session elevates the low-privilege 'support' role to 'owner' on any account (including the platform itself) and permits minting live API/webhook credentials, payment approval and account creation during support

- Fingerprint: saas.console.support-mode-owner-escalation
- الشدة: HIGH | الثقة: high
- السبب الجذري: The support-session role derivation in mine() maps the target using only the support_policies mode (default 'full' → 'owner') and ignores the actor's own RBAC role; support_enter allows role 'support' and permits target==origin (platform supporting itself); and the action dispatcher guards owner actions inconsistently, omitting active_support() on all credential-minting, payment, account and plan actions while including it on secret-reveal/rotation and operational actions.

### مسار المصدر (Trace)
- [entrypoint] saas/console/index.php:87 - POST /saas/console/ -> act() after login+CSRF - Authenticated POST with action=support_enter reaches act(); require_login+require_csrf at Actions.php:27 pass for the staffer's own session.
- [propagation] saas/console/_core/Actions.php:44 - support_enter authorization - Role allowlist is ['owner','admin','support'] so a 'support'-role member may open support; support_access(target) at Bootstrap.php:260-263 returns true for kind=platform regardless of target, including the platform's own account id.
- [propagation] saas/console/_core/Bootstrap.php:183 - mine() support-resolution - With $_SESSION['support'] set, mine() returns account($target) with role = support_mode(originId)==='read_only'?'support_viewer':'owner'; support_mode() defaults to 'full' (Bootstrap.php:108-112) when no support_policies row exists, so the role becomes 'owner'.
- [propagation] saas/console/_core/Actions.php:30 - act() blanket write-block - Only role 'support_viewer' is blocked from all actions; role 'owner' (the escalated role) passes, so the subsequent per-action owner/admin checks are satisfied.
- [sink] saas/console/_core/Actions.php:146 - create_key / new_integration / manual_approve during support - create_key (146-157) mints and flashes a live otv_ token; new_integration (162-168) mints and flashes a live otint_ HMAC secret usable against hooks/index.php; manual_approve (225-240) approves pending_manual_review orders with only a regex-valid proof string and provisions subscriptions/outbox; create_account (55-77) creates accounts and flashes temp owner passwords. None check active_support().
- [sink] saas/console/_core/Actions.php:155 - persistent credential - The otv_ token is stored hashed+sealed and remains active after support_leave, giving the staffer persistent API/webhook access to the platform account.

### الادلة
- saas/console/_core/Actions.php:44 - support_enter role allowlist includes 'support'.
- saas/console/_core/Bootstrap.php:260 - support_access(): kind platform returns true for any target account id.
- saas/console/_core/Actions.php:49 - $_SESSION['support'] set with account_id=$target, origin_id=$id, expires=time()+1800 (30 min) while support_grants row uses time()+900 (15 min, line 47) — record expiry is never re-checked by active_support()/mine().
- saas/console/_core/Bootstrap.php:191 - mine() support branch: origin kind platform (or reseller parent) + unexpired session => role 'owner' under default 'full' policy.
- saas/console/_core/Bootstrap.php:108 - support_mode() defaults to 'full' when no policy row exists (account id and global 0).
- saas/console/_core/Actions.php:30 - Blanket read-only block tests role==='support_viewer' only; escalated 'owner' passes.
- saas/console/_core/Actions.php:147 - create_key guard is allowed('integrations.manage')||platform — satisfied by role 'owner' during support.
- saas/console/_core/Actions.php:163 - new_integration guard is owner/admin role only — satisfied by role 'owner' during support, no active_support() check.
- saas/console/_core/Actions.php:226 - manual_approve guard is platform/reseller kind + owner/admin/finance role — satisfied during support; order lookup is status-only, scoped_customer() passes for platform.
- saas/console/_core/Actions.php:256 - Contrast: reveal_secret adds `|| active_support()` — the intended guard that the unflagged actions omit.
- saas/console/database/Seed.php:41 - Seed creates a platform 'support' role member (support@ontrack.test, membership role 'support' on platform account 1), making the exploit actor real in any seeded deployment.

### الاصلاح المقترح
- Derive the support-session role from the actor's own ROLE, not only the origin policy: a 'support'-role actor must resolve to 'support_viewer' regardless of policy (only owner/admin may act as 'owner' in full mode). Reject support_enter when target==origin or when the target is the platform account for non-owner/admin actors. Add active_support() guards to create_key, new_integration, revoke_key, revoke_integration, manual_approve, create_account, assign_plan, create/edit/archive_plan, set_account_status, edit_account, voice/plan/policy and account/site/agent/campaign/contact actions; or enforce one blanket rule that any support session is read-only unless the actor's membership role is owner/admin AND the origin policy is 'full'. Enforce support_grants.expires_at (900s) in active_support()/mine() instead of the 1800s session value.

## 5. Static maintenance token committed in the repository authenticates a write-capable endpoint (overwrites live Gemini key)

- Fingerprint: root.authz.static-maintenance-token.setup_demo-write
- الشدة: HIGH | الثقة: high
- السبب الجذري: The sole authentication factor for a state-changing maintenance endpoint is a secret that is both hard-coded in source and committed in plaintext in UPLOAD_TO_ROOT.txt; there is no per-deployment secret, no rotation, and no rate limit. The hash_equals gate is cryptographically correct but provides zero assurance once the token is public.

### مسار المصدر (Trace)
- [entrypoint] UPLOAD_TO_ROOT.txt:8 - root-credential - Plaintext maintenance token committed to repo; sha256 verified == TOKEN_HASH constant
- [entrypoint] api/setup_demo.php:6 - auth - const TOKEN_HASH = '60a11e1f...' hard-coded
- [entrypoint] api/setup_demo.php:14 - auth - $token from HTTP_X_MAINTENANCE_TOKEN ?? POST['token'] ?? GET['token']
- [propagation] api/setup_demo.php:15 - auth - hash_equals(TOKEN_HASH, hash('sha256', $token)) - the only gate; passes for the committed token
- [propagation] api/setup_demo.php:19 - input - Requires attacker-supplied key length >= 20, no other validation
- [sink] api/setup_demo.php:40 - filesystem-write - Writes api/demo_config.local.php via var_export with gemini_api_key = supplied key, chmod 0600
- [propagation] api/demo_config.php:11 - config-merge - demo_config.local.php overrides defaults['gemini_api_key'] via array_replace
- [propagation] api/diag.php:6 - auth - Same TOKEN_HASH constant gates diag.php
- [sink] api/diag.php:53 - outbound-request - With token, reflects gemini_key_present and performs outbound curl to Google + HuggingFace endpoints
- [propagation] api/.demo_setup_done:1 - evidence - Run marker 2026-10-01T19:42:39Z shows the setup endpoint has been executed

### الادلة
- UPLOAD_TO_ROOT.txt:8 - Computed sha256 of the plaintext token in UPLOAD_TO_ROOT.txt:8 equals the TOKEN_HASH constant; the credential is fully public in the repo
- api/setup_demo.php:6 - Verification: sha256('GF6_1W21Hc4khNPB53jz6n6UfX0ZUo94s0Diy_SDIEk') === '60a11e1fa3f8f0ddc9b4711cff115079450e4672b8a0f3c2c1435361f4bb67f7' === TOKEN_HASH in both files (computed locally, no target execution)
- api/setup_demo.php:40 - Write path: attacker-supplied key (len>=20) is written into demo_config.local.php which overrides the live gemini_api_key; no second secret is checked
- api/.demo_setup_done:1 - Run marker shows the setup endpoint has actually been executed, so the file is operational rather than dead code

### الاصلاح المقترح
- Remove UPLOAD_TO_ROOT.txt and rotate the maintenance token; replace the static TOKEN_HASH with a deployment-time secret read from outside webroot (env or 0600 file) that is never committed. Stop accepting the token via $_GET (log leak). Add per-IP/per-token rate limiting and audit logging. Confirm setup_demo.php/diag.php are removed from the live host or gated behind a server-level allowlist.

## 6. Committed plaintext maintenance token equals static auth for diag/setup_demo

- Fingerprint: upload_to_root.plaintext.maintenance.token
- الشدة: HIGH | الثقة: high
- السبب الجذري: A static shared secret is hardcoded in source and additionally committed in plaintext in UPLOAD_TO_ROOT.txt, so the authentication control is meaningless against anyone with repository access.

### مسار المصدر (Trace)
- [entrypoint] UPLOAD_TO_ROOT.txt:8 - repo root - Plaintext maintenance token committed at repo root; captured as the shared secret for two server endpoints
- [propagation] api/diag.php:37 - api/diag.php - Token accepted via X-Maintenance-Token header or token query param, then hash_equals versus hardcoded hash
- [propagation] api/setup_demo.php:14 - api/setup_demo.php - Token accepted via header/query/post, then hash_equals versus the same hardcoded hash
- [sink] api/setup_demo.php:40 - api/setup_demo.php - Authorized branch reached with a secret that is public in the repository (UPLOAD_TO_ROOT.txt:8)

### الادلة
- UPLOAD_TO_ROOT.txt:8 - UPLOAD_TO_ROOT.txt:8 commits the plaintext maintenance token used as the shared secret
- api/diag.php:6 - TOKEN_HASH constant hardcoded in diagnostics endpoint (SHA-256 of the committed token)
- api/setup_demo.php:6 - Same TOKEN_HASH constant hardcoded in config-write endpoint
- api/diag.php:37 - Gate: token read from header or query and compared with hash_equals
- api/setup_demo.php:14 - Gate: token read from header/post/query and compared with hash_equals

### الاصلاح المقترح
- Purge the token from UPLOAD_TO_ROOT.txt and repo history; replace TOKEN_HASH with a value sourced from an out-of-repo secret store; rotate the token; add a deploy-time check that the diagnostic endpoints require a non-committed token.

## 7. Static-token-gated endpoint overwrites server Gemini key and model config

- Fingerprint: api.setup_demo.static.token.writes.gemini.key
- الشدة: HIGH | الثقة: high
- السبب الجذري: The same static committed secret gates a write to server config containing the Gemini API key; the endpoint is reachable over HTTP POST with the token in header/body/query.

### مسار المصدر (Trace)
- [entrypoint] api/setup_demo.php:14 - api/setup_demo.php - POST/GET token accepted and compared against static TOKEN_HASH
- [propagation] api/setup_demo.php:19 - api/setup_demo.php - gemini_api_key taken from request and merged into config array
- [propagation] api/setup_demo.php:40 - api/setup_demo.php - Config serialized with var_export
- [sink] api/setup_demo.php:43 - api/setup_demo.php - file_put_contents writes demo_config.local.php in the webroot; later loaded by api/demo_config.php:9-13

### الادلة
- api/setup_demo.php:14 - Token gate via static TOKEN_HASH
- api/setup_demo.php:19 - Attacker-supplied key read and length-validated
- api/setup_demo.php:24 - Config array built with gemini_api_key and model/tts fields
- api/setup_demo.php:43 - Config written to demo_config.local.php inside webroot, chmod 0600

### الاصلاح المقترح
- Remove setup_demo.php from the deployable surface or require a distinct, un-committed admin secret; prefer secret-store/env injection for gemini_api_key; add server-side IP/allowlist and audit logging.

## 8. Host-link exec action is arbitrary command execution as appontrack

- Fingerprint: host_link.worker.exec.argv.control.plane
- الشدة: HIGH | الثقة: high
- السبب الجذري: Intentional exec channel whose only runtime gates are the allow_exec flag and PAT secrecy; the cwd constraint is not a sandbox.

### مسار المصدر (Trace)
- [entrypoint] ops/host-link/worker.py:209 - ops/host-link/worker.py - Job JSON fetched from the GitHub queue branch
- [propagation] ops/host-link/worker.py:97 - ops/host-link/worker.py - validate_job checks schema/id/target/expiry/argv/cwd/timeout
- [propagation] ops/host-link/worker.py:117 - ops/host-link/worker.py - allow_exec gate decided from private config
- [sink] ops/host-link/worker.py:135 - ops/host-link/worker.py - subprocess.Popen executes the queued argv as appontrack

### الادلة
- ops/host-link/worker.py:116 - exec action validated only against allow_exec config flag
- ops/host-link/worker.py:119 - argv constrained to strings and cwd to account home, but execution itself is free-form
- ops/host-link/worker.py:135 - subprocess.Popen runs the job argv as appontrack
- ops/host-link/worker.py:261 - config.json mode 600 enforced at startup

### الاصلاح المقترح
- Keep allow_exec=false unless required; scope the exec allowlist; rotate the PAT; monitor queue-branch writes; confirm config.json is 0600 and non-symlink (already enforced by worker).

## 9. Self-hosted owner runner performs SSH host deploy and writes a repo proof file with contents:write GITHUB_TOKEN

- Fingerprint: voice-lebanese-feedback-live-deploy.yml:self-hosted-ssh-deploy:contents-write-proof
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: Privileged automation (host SSH deploy key plus repo contents:write token) is concentrated on a self-hosted, owner-controlled runner that also runs other dispatchable workflows such as voice-local-ai-inventory.yml.

### مسار المصدر (Trace)
- [entrypoint] .github/workflows/voice-lebanese-feedback-live-deploy.yml:16 - workflow job - Job runs on the self-hosted runner label set [self-hosted, Windows, X64, ontrack-signing-recovery].
- [propagation] .github/workflows/voice-lebanese-feedback-live-deploy.yml:41 - deploy step - Uses deploy key $env:USERPROFILE/.ssh/ontrack_publish_ed25519 to SSH to appontrack@65.21.208.232:7195.
- [sink] .github/workflows/voice-lebanese-feedback-live-deploy.yml:82 - proof write - Writes docs/ops/LIVE_LEBANESE_FEEDBACK_PROOF_20261107.json to the default branch via Contents API with the workflow GITHUB_TOKEN (contents: write).

### الادلة
- .github/workflows/voice-lebanese-feedback-live-deploy.yml:9 - permissions: contents: write combined with a self-hosted runner that also holds a host deploy key.
- .github/workflows/voice-local-ai-inventory.yml:18 - A second dispatchable workflow targets a self-hosted Windows runner, showing the runner is reused across privileged and non-privileged automations.

### الاصلاح المقترح
- Scope the proof-file write to a dedicated deployment branch or a fine-grained token limited to that path; keep the SSH deploy key off any runner that also runs external or dispatchable workflows; document runner isolation.

## 10. Unauthenticated, unthrottled Gemini ephemeral-token mint enables AI cost/quota abuse

- Fingerprint: gemini.live_session.unauthenticated_no_ratelimit
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: api/live_session.php:7-9 only checks REQUEST_METHOD; api/live_session.php:11-19 validates body shape and gemini_configured, then mints the token (api/live_session.php:116-138). No authentication or rate-limiting layer exists in the file or in api/bootstrap.php.

### مسار المصدر (Trace)
- [entrypoint] assets/app.js:534 - browser - fetch('api/live_session.php', POST) with no auth header (Call Desk)
- [entrypoint] api/live_session.php:7 - web - POST-only check; no authentication gate
- [propagation] api/live_session.php:11 - web - parse_live_request validates body shape/mode only
- [sink] api/live_session.php:126 - web - curl to Google auth_tokens with server key mints token
- [sink] api/live_session.php:169 - web - returns token + system_instruction to caller

### الادلة
- api/live_session.php:7 - Only REQUEST_METHOD check; no auth gate
- api/live_session.php:126 - server-key token mint to Google auth_tokens
- api/bootstrap.php:1 - no throttle/rate-limit layer present
- api/live_session.php:120 - tokenBody uses=1, expireTime +30min, newSession +2min

### الاصلاح المقترح
- Add an access-control gate (authenticated session or signed, scoped client token) and a per-IP/per-token rate limiter with a hard daily quota before minting. At minimum, a lightweight sliding-window throttle keyed by client IP in front of api/live_session.php. Consider moving the mint behind the console auth boundary.

## 11. omnira_api_tester.php is a committed third-party API-key pass-through proxy intended for deletion

- Fingerprint: omnira.tester.public_key_proxy_deployment
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: The file is committed to the repo and self-documents that it must be removed from public hosting (omnira_api_tester.php:5), but nothing in the source prevents deployment; the destination is fixed (no SSRF), yet the key is attacker-supplied and the page is not access-controlled beyond a custom header (X-Requested-With: OmniraTester).

### مسار المصدر (Trace)
- [entrypoint] omnira_api_tester.php:49 - web - POST accepted; gate is only header X-Requested-With: OmniraTester
- [propagation] omnira_api_tester.php:52 - web - reads user-supplied api_key (12-500 chars)
- [propagation] omnira_api_tester.php:63 - web - url = fixed OMNIRA_ORIGIN + path
- [sink] omnira_api_tester.php:80 - web - curl to third party with Authorization: Bearer user key; response reflected/redacted

### الادلة
- omnira_api_tester.php:5 - self-comment: DELETE THIS FILE from public hosting once finished
- omnira_api_tester.php:9 - OMNIRA_ORIGIN fixed destination
- omnira_api_tester.php:51 - header-only gate (trivially set by any client)
- omnira_api_tester.php:65 - user key passed through as bearer

### الاصلاح المقترح
- Exclude omnira_api_tester.php from the deploy artifact (add to deploy ignore / remove from release). If a diagnostic is needed, run it on a non-public, authenticated, IP-allowlisted endpoint and delete afterward.

## 12. rotate_secret mints otv_ API keys with 64 hex chars that the api/v1 format gate (60 hex) rejects — rotated keys are dead on arrival, breaking the revocation/rotation lifecycle

- Fingerprint: gap-hunt-2-01-rotated-otv-format-mismatch
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: Length inconsistency between the two token-generation paths: create_key uses random_bytes(30) (60 hex, matching the otv_ gate) while rotate_secret reuses the integration-token length random_bytes(32) (64 hex) for otv_ keys whose API gate mandates exactly 60 hex.

### مسار المصدر (Trace)
- [entrypoint] saas/console/_core/Actions.php:275 - owner/admin console action, CSRF+RBAC-gated - rotate_secret builds $token=('otv_'|'otint_').bin2hex(random_bytes(32)) → otv_ + 64 hex; only owner/admin role with active_support()==false may invoke (Actions.php:269)
- [propagation] saas/console/_core/Actions.php:278 - api_keys row - UPDATE api_keys SET token_hash=sha256(new64hex), prefix, secret_enc WHERE id=? AND account_id=? — status stays 'active'; old hash is overwritten so the old bearer cannot replay (no grace window)
- [sink] saas/console/api/v1/index.php:15 - otv_ Bearer auth gate - preg_match('/^otv_[0-9a-f]{60}$/D',$key) rejects the 64-hex rotated token with 401 key_format before any DB lookup — the new key is unusable

### الادلة
- saas/console/_core/Actions.php:275 - rotate_secret uses random_bytes(32) → 64 hex chars after 'otv_' prefix
- saas/console/api/v1/index.php:15 - format gate is exactly /^otv_[0-9a-f]{60}$/D — 60 hex required
- saas/console/_core/Actions.php:154 - create_key uses random_bytes(30) → 60 hex, consistent with the gate — only the rotation path is inconsistent
- saas/console/_core/Actions.php:278 - rotation overwrites token_hash in place with the new (rejected-format) token; old bearer dies immediately, new bearer never works

### الاصلاح المقترح
- Use bin2hex(random_bytes(30)) for the otv_ branch in rotate_secret (or widen the api/v1 gate to 64 hex consistently with create_key's documented prefix format), and add a regression test that a rotated otv_ token passes the endpoint format gate and authenticates.

## 13. Webhook HMAC endpoint omits the HTTPS-enforcement that device/v1 enforces

- Fingerprint: saas/console/hooks/index.php:13-24:missing_https_guard
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: Transport assurance was applied selectively: device/v1 guards $_SERVER['HTTPS'] but hooks/index.php was written as a server-to-server signed endpoint and skipped the same guard. HMAC protects integrity/authenticity, not channel confidentiality, so it does not substitute for TLS enforcement.

### مسار المصدر (Trace)
- [entrypoint] saas/console/hooks/index.php:13 - saas/console - POST entrypoint for external webhooks; accepts raw php://input body up to 32768 bytes.
- [propagation] saas/console/hooks/index.php:16 - saas/console - Reads Bearer otint_ token from Authorization header; no HTTPS check precedes this.
- [propagation] saas/console/hooks/index.php:19 - saas/console - Validates X-OnTrack-Timestamp (10-digit, ±300s) and X-OnTrack-Signature (64 hex) — integrity only, no transport binding.
- [propagation] saas/console/hooks/index.php:23 - saas/console - Verifies HMAC-SHA256(timestamp."\n".raw, token) with hash_equals; cryptographic verification passes without TLS enforcement.
- [sink] saas/console/hooks/index.php:27 - saas/console - Sink: resolves integration by sha256(token), then provisions orders/payments/bookings (payment.confirmed, booking.created, order.created).
- [sink] saas/console/device/v1/index.php:16 - saas/console/device/v1 - Contrast control: device/v1 returns 426 for cleartext requests — the equivalent guard missing in hooks.

### الادلة
- saas/console/hooks/index.php:13 - Method check; no HTTPS/`empty($_SERVER['HTTPS'])` guard exists anywhere in the file before signature verification.
- saas/console/hooks/index.php:24 - hash_hmac + hash_equals verification completes without any transport check.
- saas/console/device/v1/index.php:16 - Sibling device surface returns 426 https_required when PHP_SAPI!=='cli' && HTTPS empty or off — the control hooks/index.php lacks.

### الاصلاح المقترح
- Add the same guard used by device/v1 to hooks/index.php (return 426 when PHP_SAPI!=='cli' && (empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')), and confirm the host Apache/LiteSpeed virtualhost forces TLS redirect for /saas/console/hooks.

## 14. A stale campaign lease permanently blocks every future campaign claim on the pilot executor

- Fingerprint: campaign-lease-stale-global-block-pilot-attempts
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: The global 'leased' guard in pilot_campaign_claim (pilot_workspace.php:253) has no server-side expiry, reaper or operator release, and lease release depends entirely on a device-authenticated finish() call whose attempt_id may never be known to the device after a lost response or lost local state.

### مسار المصدر (Trace)
- [entrypoint] saas/mobile/server/campaigns_api.php:24 - POST action=claim with device Bearer key invokes pilot_campaign_claim() - Device-authenticated claim endpoint; also action=verify (line 29) and action=finish (line 34).
- [propagation] saas/mobile/server/pilot_workspace.php:253 - pilot_campaign_claim global leased guard - foreach($attempts as $attempt) if status==='leased' return null; checked before any campaign/contact is considered, so one lease blocks everything.
- [propagation] saas/mobile/server/pilot_workspace.php:272 - lease creation and persistence - New attempt with status 'leased' is appended to attempts.json (pilot_atomic line 273) with leased_epoch=time().
- [propagation] saas/mobile/server/pilot_workspace.php:306 - verify_claim does not release - Lease older than 30s returns ['allowed'=>false,'reason'=>'lease_expired'] but leaves the attempt status as 'leased'.
- [sink] saas/mobile/server/pilot_workspace.php:280 - only release path is device finish - pilot_campaign_finish is the sole transition away from 'leased'; requires the device to supply the attempt_id, which an unreachable device cannot do.
- [sink] saas/mobile/server/ops.php:118 - operator UI is read-only for attempts - ops.php only lists attempts; no release/finish control exists for an orphaned lease.

### الادلة
- saas/mobile/server/pilot_workspace.php:253 - Global block: any 'leased' attempt makes every claim return null.
- saas/mobile/server/pilot_workspace.php:306 - Lease-expiry is detected (30s) but never clears the attempt.
- saas/mobile/server/pilot_workspace.php:288 - finish() rejects anything not currently 'leased', so no alternate path can force-release.
- saas/mobile/server/campaigns_api.php:16 - Full operation set (claim/verify/finish/arm/preview/pause/resume/cancel/create) contains no release or lease-expiry action.
- saas/console/_core/Actions.php:334 - Console campaign actions stop at update_campaign (draft/paused/archived); no attempt or lease lifecycle control.
- saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/CampaignDialer.kt:26 - attempt_id survives only in Android SharedPreferences; clearing app data or losing the claim response leaves the device unable to finish.

### الاصلاح المقترح
- Add a server-side lease reaper: in pilot_campaign_claim (and on read) transition attempts whose leased_epoch is older than a defined grace window to 'unknown'/'failed' idempotently, and scope the 'leased' guard to the specific campaign/contact rather than the whole store. Expose an authenticated operator release action in ops.php/campaigns_api so a stuck lease can be cleared without editing host files, and record the release in the attempt record for audit.

## 15. Single shared SHA-256 secret is the sole root of trust for both the device API plane and the admin dashboard

- Fingerprint: mobile.auth.shared-secret:device+admin
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: One shared secret backs two distinct privilege planes (device API + admin UI) with no separation, rotation, or isolation.

### مسار المصدر (Trace)
- [entrypoint] saas/mobile/server/common.php:10 - mobile_authorized(): verifier for both device bearer and admin login - Compares hash('sha256', $key) to auth.sha256 with length and hex-format gates; single verifier for every mobile endpoint and the admin dashboard login.
- [propagation] saas/mobile/server/index.php:9 - admin login POST uses mobile_authorized() - index.php:10 authenticates the admin session with the same mobile_authorized() check used by the device bearer.
- [propagation] saas/mobile/server/admin_ticket.php:4 - device bearer mints admin sign-in ticket - mobile_require_device() passes on the device key, then admin_ticket.php:7-19 mints a 35s single-use admin sign-in URL.
- [sink] saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkService.kt:370 - device stores key in SharedPreferences and sends it as Bearer - Key from 'ontrack_link' prefs is sent as Authorization: Bearer on every poll; same key unlocks the admin plane via admin_enter.

### الادلة
- saas/mobile/server/common.php:13 - strlen($key) >= 24 && preg_match hex64 && hash_equals($expected, hash('sha256', $key)) — the identical check gates admin login (index.php:10) and device endpoints.
- saas/mobile/server/index.php:10 - if (mobile_authorized((string)$_POST['key'])) { session_regenerate_id(true); $_SESSION['mobile_admin']=true; } — admin login is the same shared secret.
- saas/mobile/server/admin_ticket.php:4 - mobile_require_device(); then writes admin-ticket-<sha256(ticket)>.json and returns a 35s admin URL — device bearer alone escalates to admin session.

### الاصلاح المقترح
- Separate the device bearer from the admin credential; issue per-device scoped keys with revocation; rate-limit device endpoints; ensure the deployed auth.sha256 is a fresh high-entropy random hash not derived from any committed value.

## 16. Final AndroidManifest.xml (exported-component closure) is not source-verifiable; it is generated by prepare.py merging upstream JemRec

- Fingerprint: manifest:exported:merge:upstream-not-in-repo
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: The merged manifest is not in the repo; prepare.py only partially constrains exported attributes and leaves the upstream component set to the merger's defaults.

### مسار المصدر (Trace)
- [entrypoint] saas/mobile/audio-proof/prepare.py:212 - saas/mobile/audio-proof - prepare.py opens the upstream app/src/main/AndroidManifest.xml and makes programmatic edits; no final manifest is checked into the repo.
- [propagation] saas/mobile/audio-proof/prepare.py:224 - saas/mobile/audio-proof - LAUNCHER intent-filter is removed from .MainActivity but its android:exported attribute is left to upstream defaults; upstream components are not enumerated or constrained.
- [sink] saas/mobile/audio-proof/prepare.py:260 - saas/mobile/audio-proof - exported=false is only set on the newly added receiver/provider/ProofActivity/MobileLinkService; the final OS-visible merged manifest is produced at build time by the upstream Gradle merger and cannot be inspected from this checkout.

### الادلة
- saas/mobile/audio-proof/prepare.py:224 - prepare.py:224-228 removes the LAUNCHER intent-filter from .MainActivity but leaves its exported attribute unset.
- saas/mobile/audio-proof/prepare.py:245 - prepare.py:245-260 sets exported=false on NativeInstallResultReceiver, FileProvider, ProofActivity and MobileLinkService only.
- saas/mobile/audio-proof/UPSTREAM.lock.json:1 - Pinned upstream is jemcik/JemRec commit f011062adfb3a2154c4d5a9cc4394032fb4345f2; its manifest content is outside this checkout.

### الاصلاح المقترح
- Declare android:exported="false" explicitly on every component in the final merged manifest (or add manifest-merger rules / a Gradle task), and fail the build in CI (apkanalyzer manifest print + tools/check_mobile_manifest.py) on any unexpected exported component.

## 17. Unhandled job validation/processing errors crash the worker every Cron minute (deployment control-plane DoS)

- Fingerprint: hostlink.worker.tick.unhandled_job_error.persistent_control_plane_outage
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: Per-job error handling in tick() only special-cases the 'Expired job' ValueError; all other ValueError/KeyError/OSError from validate_job/run_exec/JSON are allowed to escape to main()'s fatal except, and the poison job file is never cleared, so the failure repeats every minute.

### مسار المصدر (Trace)
- [entrypoint] ops/host-link/worker.py:209 - tick() queue fetch - tick() fetches the single job file (contents/<target>.json) from the ontrack-host-link branch via api.get. The queue file is fetched from the shared GitHub branch every Cron minute; the worker never writes back to it, so a poison job persists.
- [propagation] ops/host-link/worker.py:218 - id-reuse guard - job_id in state['completed'] with a different digest raises ValueError('Job id reused with different contents') unconditionally — no receipt is written.
- [propagation] ops/host-link/worker.py:225 - validate_job try/except - except ValueError only converts the exact string 'Expired job or incorrect clock' into a receipt; all other ValueErrors (and KeyError/OSError) re-raise to main().
- [propagation] ops/host-link/worker.py:97 - validate_job() - Raises ValueError for wrong schema/target/id, bad argv, cwd outside account home, timeout out of 1..30 range, tz-less or invalid timestamps.
- [propagation] ops/host-link/worker.py:133 - run_exec() - subprocess.Popen(job['argv']) can raise OSError for argv exceeding OS ARG_MAX; argv element size is not bounded by validate_job.
- [sink] ops/host-link/worker.py:279 - main() fatal handler - except Exception prints a generic message to stderr and calls sys.exit(1); next Cron minute re-fetches the same branch job and repeats the crash.

### الادلة
- ops/host-link/worker.py:229 - except ValueError as exc: if str(exc) != 'Expired job or incorrect clock': raise — only expiry is converted to a terminal receipt; all other validation failures re-raise.
- ops/host-link/worker.py:218 - raise ValueError('Job id reused with different contents') — unconditional raise, no receipt, no recovery.
- ops/host-link/worker.py:279 - print('Host Link: ' + type(exc).__name__ ...); sys.exit(1) — the fatal handler that ends the tick for every uncaught error.
- ops/host-link/worker.py:213 - len(content) > 16384 raise ValueError('Job too large') — also uncaught at tick level (re-raised).
- ops/host-link/tests/test_worker.py:110 - test_invalid_jobs_rejected asserts validate_job raises for bad jobs — tests validation, not tick() recovery; no test asserts tick() survives a poison job without process exit.

### الاصلاح المقترح
- In tick(), wrap validate_job(), the id-reuse check and run_exec() in a single try/except that, on ANY exception, writes a terminal rejected/failed receipt (job_id, target, job_sha256, status, error string, finished_at) to the branch and returns, mirroring worker.py:225-236. Do not re-raise to main(). Optionally cap individual argv element size in validate_job (e.g., <= 128 KiB) to convert the ARG_MAX OSError into a clean validation rejection. Add a regression test asserting tick() survives a malformed job without exiting the process.

## 18. Static-token-gated diagnostics proxy outbound requests and reflects responses

- Fingerprint: api.diag.static.token.proxy.fetch.reflect
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: A static committed token gates an outbound fetch-and-reflect routine with fixed external destinations and response echoing.

### مسار المصدر (Trace)
- [entrypoint] api/diag.php:37 - api/diag.php - Token accepted from header or query and compared against static TOKEN_HASH
- [propagation] api/diag.php:63 - api/diag.php - Curl proxy to Gemini with server key; response text truncated and stored
- [propagation] api/diag.php:110 - api/diag.php - Curl GETs to HuggingFace targets with body previews
- [sink] api/diag.php:135 - api/diag.php - Status, content type, and body previews reflected in the HTTP response

### الادلة
- api/diag.php:37 - Token gate via static TOKEN_HASH
- api/diag.php:63 - Gemini interaction POST carrying the server Gemini key header
- api/diag.php:110 - HuggingFace GETs with body previews reflected
- api/diag.php:96 - Response fields (http, content_type, response preview) returned to caller

### الاصلاح المقترح
- Remove or rotate the token, gate by an un-committed secret, restrict to operator network, and minimize reflected content.

## 19. Leftover operator proxy file relays third-party API with weak header gate

- Fingerprint: omnira.api.tester.x_requested_with.proxy
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: Leftover experimental/debug file with a non-authenticating header gate that proxies external API calls and reflects responses; protected only by a convention header.

### مسار المصدر (Trace)
- [entrypoint] omnira_api_tester.php:51 - omnira_api_tester.php - POST accepted with only X-Requested-With: OmniraTester gate
- [propagation] omnira_api_tester.php:52 - omnira_api_tester.php - api_key, model, and prompt taken from request and validated only for length/format
- [propagation] omnira_api_tester.php:109 - omnira_api_tester.php - cURL relay to https://ai.omnira-ai.net with the user key
- [sink] omnira_api_tester.php:143 - omnira_api_tester.php - Upstream response reflected to the caller

### الادلة
- omnira_api_tester.php:5 - Header comment: DELETE THIS FILE from public hosting once finished
- omnira_api_tester.php:51 - Only gate is a convention header X-Requested-With
- omnira_api_tester.php:80 - User-supplied key sent to fixed third-party origin
- omnira_api_tester.php:156 - Response reflected to caller (raw preview up to 25KB)

### الاصلاح المقترح
- Ensure the file is excluded from deploy artifacts (it sits at repo root, not under saas/); if a diagnostic is required, gate by strong auth, restrict the destination, cap reflection, and remove after use.

## 20. Unauthenticated Gemini ephemeral-token mint with no rate limit

- Fingerprint: api.live_session.unauthenticated.no.ratelimit
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: Unauthenticated endpoint with no throttle before an expensive third-party credentialed call.

### مسار المصدر (Trace)
- [entrypoint] api/live_session.php:11 - api/live_session.php - Anonymous POST body accepted; no auth check
- [propagation] lib/OutboundMission.php:5 - lib/OutboundMission.php - Mission text (brief or structured) normalized and folded into the system instruction
- [propagation] api/live_session.php:135 - api/live_session.php - Server Gemini key sent to Google auth_tokens
- [sink] api/live_session.php:169 - api/live_session.php - Ephemeral token returned to the anonymous caller

### الادلة
- api/live_session.php:7 - POST-only check; no authentication or identity layer
- api/live_session.php:17 - Only config gate: gemini_api_key must be non-empty
- api/live_session.php:135 - Server key sent to Google auth_tokens endpoint
- api/live_session.php:169 - Ephemeral token returned to the anonymous caller

### الاصلاح المقترح
- Add rate limiting (per-IP/token bucket), consider an origin check and a cap on concurrent tokens; keep the sanitizer but treat mission text as untrusted.

## 21. Session cookie Secure flag depends on request-time HTTPS header

- Fingerprint: console.session.cookie.secure.conditional.https
- الشدة: MEDIUM | الثقة: high
- السبب الجذري: Secure flag derived from a request header that may be absent when TLS is offloaded, rather than from deployment reality.

### مسار المصدر (Trace)
- [entrypoint] saas/console/_core/Bootstrap.php:166 - saas/console/_core/Bootstrap.php - startsession applies cookie params gated on $_SERVER['HTTPS']
- [propagation] saas/console/_core/Bootstrap.php:167 - saas/console/_core/Bootstrap.php - Secure flag computed from request header that proxies may not set
- [sink] saas/console/_core/Bootstrap.php:168 - saas/console/_core/Bootstrap.php - Session cookie set without guaranteed Secure attribute

### الادلة
- saas/console/_core/Bootstrap.php:167 - Session cookie params with Secure conditional on $_SERVER['HTTPS']
- saas/mobile/server/index.php:6 - Same conditional Secure pattern in the mobile admin session

### الاصلاح المقترح
- Force Secure=true (the app is HTTPS-only per recon) or detect TLS via a trusted proxy header/deployment constant; add HSTS.
