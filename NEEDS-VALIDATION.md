# عناصر تحتاج تحقق (Needs Validation)

## 1. Mobile device API enforces only the bearer secret, not application-layer TLS

- Fingerprint: mobile.device_api.no_tls_enforcement
- السبب الجذري المدعى: saas/mobile/server/common.php:10-19 performs only bearer-hash verification; there is no $_SERVER['HTTPS'] / X-Forwarded-Proto check. Contrast with the documented HTTPS requirement on saas/console/device/v1.

### مسار المصدر
- [entrypoint] saas/mobile/server/common.php:15 - mobile_require_device parses Bearer from HTTP_AUTHORIZATION/REDIRECT_HTTP_AUTHORIZATION
- [propagation] saas/mobile/server/common.php:10 - mobile_authorized compares sha256(key) to stored digest; no TLS check
- [sink] saas/mobile/server/session.php:4 - device bearer authorizes Gemini token minting (and control/call-ledger/admin-ticket)

### الموانع
- needs_validation: whether the live ingress forwards or terminates plaintext HTTP to /saas/mobile/* — source confirms app-layer non-enforcement only.

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 2. Shared admin/device key digest is committed in the repository while the deploy flow deliberately preserves the host copy

- Fingerprint: saas/mobile/server/auth.sha256:committed-digest:shared-admin-and-device-key
- السبب الجذري المدعى: The credential-binding artifact that gates the shared admin/device plane is stored in the repository rather than only at a private host path, so its value is not guaranteed to differ from the deployed one.

### مسار المصدر
- [entrypoint] saas/mobile/server/.htaccess:2 - Requires all denied for Files auth.sha256, so the digest is not web-servable where this rule ships (deploy asserts this rule is present).
- [propagation] saas/mobile/server/common.php:10 - Device bearer is hashed and compared to the stored digest; the same digest also backs the admin login.
- [sink] tools/deploy_mobile_server.py:94 - Deploy requires auth.sha256 to exist and refuses when missing/symlinked, and never writes it, so the live value is whatever was installed on the host — not necessarily distinct from the committed one.

### الموانع
- Cannot read the value (secret discipline); cannot observe whether the live host digest equals the committed one from source.

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 3. hooks/index.php (and api/v1) do not enforce HTTPS in-endpoint; transport security for the otint_/otv_ bearer and HMAC secrets depends on the host-scoped .htaccess redirect matching only app.ontrackegy.com

- Fingerprint: saas.console.hooks.transport-tls-conditional
- السبب الجذري المدعى: Transport protection is delegated to a host-conditional .htaccess rewrite instead of being enforced at each credential-bearing endpoint; only the device pairing endpoint self-enforces TLS.

### مسار المصدر
- [entrypoint] saas/console/hooks/index.php:13 - Only checks REQUEST_METHOD and reads the HMAC bearer; no HTTPS check (compare device/v1/index.php:16-17).
- [propagation] saas/console/hooks/index.php:23 - hash_hmac('sha256', timestamp+"\n"+raw, $token) — the bearer token is the HMAC key, so token capture over plaintext HTTP directly enables forging every allowed event type for that integration.
- [sink] saas/console/hooks/index.php:67 - Token holder can mark orders paid (payment_status paid, EGP, matching amount, valid reference) and enqueue payment.received_pending_claim outbox.

### الموانع
- needs_validation: whether the live deployment serves /saas/console/hooks or /saas/console/api/v1 over plaintext on any host (cPanel/LiteSpeed TLS termination and per-vhost redirect behavior are deployment facts not visible in this source tree).

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 4. Suspend is not a uniform tenant revocation: API keys and integration tokens ignore owning-account status

- Fingerprint: saas.console.suspend.not-uniform-revocation
- السبب الجذري المدعى: Account status is enforced ad-hoc via per-endpoint JOIN conditions rather than a single shared authorize_tenant() gate; the 'suspended' state has no cascading revocation for API/integration bearers or owner web sessions.

### مسار المصدر
- [entrypoint] saas/console/_core/Actions.php:352 - Account status enum and update
- [sink] saas/console/api/v1/index.php:21 - API-key bearer gate (key-status only)
- [sink] saas/console/hooks/index.php:27 - Integration bearer gate (integration-status only)
- [sink] saas/console/_core/DevicePairing.php:62 - Device bearer gate (account-status joined, revokes on suspend)

### الموانع
- Decisive validation requires execution or a deployment observation unavailable in this source-only run

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 5. DNC ledger is account-scoped and append-only; survives contact delete and account suspend

- Fingerprint: saas.console.dnc-ledger.account-scoped-append-only
- السبب الجذري المدعى: n/a — design is sound for retention.

### مسار المصدر
- [entrypoint] saas/console/_core/DncLedger.php:19 - Ledger create/insert
- [sink] saas/console/_core/DncGuard.php:98 - Account-scoped lookup
- [sink] saas/console/_core/Actions.php:332 - Delete guard on dnc contact

### الموانع
- Decisive validation requires execution or a deployment observation unavailable in this source-only run

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 6. No hard account/tenant deletion exists, so cascade-failure and orphan-residue after delete cannot occur

- Fingerprint: saas.console.no-hard-tenant-deletion
- السبب الجذري المدعى: Lifecycle implemented as soft status only; no erasure workflow exists (OPEN per project docs).

### مسار المصدر
- [entrypoint] saas/console/database/schema.mysql.sql:1 - Schema definitions (no FK)
- [entrypoint] saas/console/_core/Actions.php:26 - Dispatcher without delete_account
- [sink] saas/console/_core/Actions.php:332 - Account-scoped contact delete

### الموانع
- Decisive validation requires execution or a deployment observation unavailable in this source-only run

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 7. omnira_api_tester.php gated only by a spoofable X-Requested-With header; fixed-destination key relay left in root

- Fingerprint: root.authz.omnira_tester.header-only-gate
- السبب الجذري المدعى: Authorization depends on a client-controlled request header that any caller can emit, with no server-side secret; the file is a developer tool that should not persist on a shared public host.

### مسار المصدر
- [entrypoint] omnira_api_tester.php:49 - POST method branch
- [propagation] omnira_api_tester.php:51 - Only gate: HTTP_X_REQUESTED_WITH !== 'OmniraTester' -> error; header is client-controlled
- [propagation] omnira_api_tester.php:52 - Reads user-supplied api_key, prompt, model, auth from POST
- [sink] omnira_api_tester.php:80 - curl to OMNIRA_ORIGIN /api/v1/messages|/models with the supplied key (fixed destination, no arbitrary SSRF)

### الموانع
- needs_validation: confirm whether omnira_api_tester.php is present under public_html on app.ontrackegy.com (source-only cannot assert deployment state)

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 8. Privileged local ADB/shell/PCM bridge and ADB-over-WiFi (port 5555) exposure details are unverifiable; the controlling classes live in upstream JemRec outside the checkout

- Fingerprint: adb:5555:wifi-free:local-bridge:upstream-not-in-repo
- السبب الجذري المدعى: Local privilege/socket helper code is upstream and absent from the checkout; prepare.py patches these classes but the full implementation and bind/auth behavior are not observable.

### مسار المصدر
- [entrypoint] saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkActivity.kt:413 - setWifiFreeMode(enabled) calls StartWithoutWifi.setEnabled and CaptureDaemon.revive — both upstream classes absent from the checkout.
- [propagation] saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLiveCall.kt:48 - CaptureDaemon.livePcm(context, screening) opens the authenticated local socket carrying live PCM; implementation is upstream.
- [sink] saas/mobile/audio-proof/overlay/app/src/main/java/com/ontrack/voiceproof/MobileLinkService.kt:174 - CaptureDaemon.linkControl(this,'S')/'A' issues speaker-toggle and auto-answer commands over the local shell socket; the socket's peer-auth and bind behavior are not observable here.

### الموانع
- Upstream local-bridge classes not in checkout; cannot confirm bind address or auth from source.

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 9. No cross-instance lease: two installed workers execute the same single job twice

- Fingerprint: hostlink.worker.double_exec.two_instances.no_shared_lease
- السبب الجذري المدعى: Concurrency control is per-install (local lock + local state.json) while the job queue and results are shared on a GitHub branch; there is no global lease tying a job to one executing worker.

### مسار المصدر
- [entrypoint] ops/host-link/worker.py:264 - fcntl.flock on home/'worker.lock', scoped to each install directory — no cross-instance mutual exclusion.
- [propagation] ops/host-link/worker.py:197 - statefile = home/'state.json' — the 'completed' idempotency set is per-install, not per-branch.
- [propagation] ops/host-link/worker.py:209 - api.get(contents/<target>.json) fetches the same single job from the shared branch for every instance.
- [sink] ops/host-link/worker.py:242 - Both instances pass validate_job and call run_exec()/probe() on the same job; results are PUT to the shared branch last-writer-wins.

### الموانع
- Deployment topology: confirm only ONE worker install/crontab exists on the appontrack account (single operator, single host). This is outside the repository and cannot be asserted from source.

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic

## 10. Single shared device key is sole mobile trust root; git-tracked digest, no per-tenant isolation

- Fingerprint: mobile.single.shared.key.trust.root
- السبب الجذري المدعى: A single shared key is the only mobile authN; the git-tracked digest may be the deployed secret, collapsing the boundary.

### مسار المصدر
- [entrypoint] saas/mobile/server/common.php:11 - auth.sha256 digest read from repo-tracked file
- [propagation] saas/mobile/server/common.php:13 - hash_equals gate against the single shared digest
- [sink] saas/mobile/server/index.php:10 - Same check grants admin session on the dashboard

### الموانع
- needs_validation: confirm the live host's /home/appontrack/public_html/saas/mobile/server/auth.sha256 is a strong random value, not the committed digest (secret-discipline: do not read the value; rotate and observe 401 on the old key).

### خطة التحقق
- محليا: Bounded local fixture inside an OS-enforced sandbox (not available on this host)
- على النشر (بمراقبة المالك): Owner-observed check of the live configuration/route without sending audit traffic
