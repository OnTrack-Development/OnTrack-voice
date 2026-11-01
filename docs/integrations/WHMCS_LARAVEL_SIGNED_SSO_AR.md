# WHMCS/Laravel → OnTrack Voice: signed browser SSO (implementation candidate)
**2026-11-01 — branch feat/verified-external-sso-20261101; NOT an authenticated browser E2E claim.**

## Purpose and boundaries
This is a short-lived **identity assertion**, not a payment receipt, plan activation, account creation, or permission grant. It works for an existing OnTrack user already belonging to the integration account or a child of its reseller. The first federation requires that user to log in locally and explicitly consent; subsequent signed assertions can log in the same user after the on-screen confirmation. An unrelated OnTrack account cannot be attached by email, name or phone.

## Source application contract
- Use an **active** WHMCS or Laravel integration created under the correct OnTrack account. Its vault-backed integration secret (the same token used for the signed webhook) must be available to the source **server**, never the browser or mobile application. Legacy hash-only keys must be rotated.
- After the source authenticates its customer (not merely a successful invoice redirect), generate a JSON object with these fields and exact types:
  `{"v":1,"integration_id":123,"iss":"whmcs","aud":"ontrack-voice-console","sub":"immutable-customer-id","jti":"32-or-more-lowercase-hex","nonce":"32-or-more-lowercase-hex","iat":1700000000,"exp":1700000120}`.
  Use current Unix seconds for `iat`, `exp=iat+120` (maximum 180 seconds); use new cryptographically random `jti` and `nonce` for every attempt. The `sub` is a stable non-recycled external **user identifier** scoped to that integration, 1–128 ASCII letters/numbers/dot/underscore/colon/hyphen. `iss` must exactly match `whmcs` or `laravel` as stored for the integration.
- Compact-encode JSON, then base64url-encode without padding to obtain `body`. Calculate `mac = lowercase_hex(HMAC_SHA256(body, integration_secret))`. Form `ticket = body + "." + mac`.
- Browser should **POST** `ticket` in a hidden form to `/saas/console/sso` on the OnTrack origin. This avoids putting a bearer assertion in URL/query logs. A GET compatibility form is supported but discouraged. OnTrack validates the HMAC and claim constraints and immediately redirects to a clean URL `/saas/console/sso/confirm`.
- New external identity: OnTrack prompts for a local login (if needed), then explicit CSRF-protected approval to bind. Existing linked identity: the user confirms before OnTrack creates a login session. A different already-logged-in user cannot silently switch accounts.
- Replays are rejected using a per-integration unique hashed `jti` in the database; the ticket is revalidated at confirmation. Integration revocation/secret rotation invalidates new logins. `Referrer-Policy: no-referrer`, no-store pages and a rotated session ID are used.
- Scope: exact integration account, direct customer of a reseller integration, or any active account for the platform integration. Linking does **not** add a membership. No fallback to email matching. If the target user lacks active membership in the issuer scope, the flow fails closed.
- A source app must never send the integration secret to JavaScript or an HTML form. The form contains only the short-lived signed ticket. A source can render the ticket as a hidden field after server-side signing.

## Evidence and open gates
- Automated tests: `php tools/test_voice_sso.php` and `php tools/test_voice_saas_console.php`, plus PHP syntax and JS checks in both CI and publish workflows.
- This candidate still needs staged authenticated browser tests (first link, repeat login, local account mismatch, expired/replayed tickets, revoked integration) and independent WHMCS/Laravel integration tests with the real source login.
- **OPEN:** verified email self-registration for users without a local account; external paid order → customer claim → subscription activation; payment settlement verification; full 2FA; cross-domain reseller SSO. No real payment or calls should be used to test this slice.
