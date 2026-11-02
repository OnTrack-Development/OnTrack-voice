# OnTrack Voice — Staff Caller Identity & External Connector Contract
2026-11-02 · Development/demo only; not production telephony acceptance.

## Implemented on feature branch
- Additive database migration v13: tenant-isolated voice_staff_numbers and integration_facts.
- Account staff page: owner/admin can register several international E.164 numbers per accepted employee, select data scopes, set private 6–10 digit PIN (hashed), revoke numbers independently.
- Role scopes: owner/admin all; manager tickets, bookings, website, operations, general; receptionist and operator tickets, bookings, general; finance finance/general; support tickets/general. Current role is checked on each request even if it was later downgraded.
- Paired-SIM device API: POST /saas/console/device/v1 with operation=caller_identify returns recognized and pin_required but no private facts. operation=caller_verify only returns caller role and authorized facts after PIN. Bad PIN attempts lock for 15 minutes after five failures. The private DTMF PIN collection on Android is NOT connected yet.
- External connector providers: WordPress/WooCommerce and Generic REST/Webhook (plus Laravel and WHMCS) can submit signed knowledge.snapshot aggregates via the existing HMAC webhook endpoint. Newly added provider types are read-only.
- Flow AI can ground its suggested graphs in current tenant's signed, role-filtered fact summaries and agent knowledge. Reference material is untrusted data, not an instruction to execute.
- All external write actions are OFF by default. No operation is dispatched to an unapproved remote URL.

## Snapshot webhook contract

POST https://app.ontrackegy.com/saas/console/hooks/index.php

Headers:
- Authorization: Bearer (the account-owned integration secret)
- X-Ontrack-Timestamp: current Unix seconds
- X-Ontrack-Signature: lowercase hex HMAC_SHA256(timestamp + newline + exact JSON request bytes, secret)
- Content-Type: application/json

Example JSON:
{"event_id":"wp-site1-20261102-00001","type":"knowledge.snapshot","data":{"category":"bookings","facts":[{"key":"today_count","value":12},{"key":"available_slots","value":"10:00, 11:30"}]}}

Categories are general, tickets, bookings, finance, websites, operations. Maximum 30 facts per event and 500 characters per value. Send aggregates, not client secrets, passwords, raw medical notes, or unredacted personal information. Facts are unique by account + integration + category + key, and event_id prevents duplicate processing. Revoking the integration hides its facts from the read path.

Connector creation alone does NOT install a WordPress plugin, WHMCS addon, Laravel library or generic REST adapter. Each adapter must still be separately configured and tested.

## Remaining OPEN gates
1. Verified Android GSM caller number and bound SIM; private DTMF PIN entry; live authorized read-only answer, spoken Arabic, without PIN appearing in transcript or AI prompt.
2. Real WP/WooCommerce, Laravel, WHMCS and generic adapters, each configured with source-side permissions, mapping, test results and data freshness. No unrestricted remote crawling or code execution.
3. Uploaded file Knowledge Base: PDF/DOCX/TXT/CSV indexing, deletion, privacy and scoped retrieval. Current plain-text agent knowledge is not equivalent to uploaded-file ingestion.
4. Connector action catalog and tested execution gateway, per-resource roles, audit, human confirmation, idempotency, dry-run, and safe rollback. The current voice_action_ready function fails closed for every operation.
5. Real device, API and deployment E2E verification, including signed APK and production host receipt. Current accounts and phone data remain internal demos.

## Security regression tests
tools/test_voice_access.php checks PIN hashing, lockout, tenant isolation, finance/reception separation, permission downgrade, revocation, signed fact storage filtering and default-deny actions.
.github/workflows/voice-caller-connectors.yml runs PHP 8.3 lint and test on the feature branch. Publishing remains a separate tested pinned-source workflow.
