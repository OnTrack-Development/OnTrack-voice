# WhatsApp Inbox + WHMCS AI Support Agent — OnTrack Voice

**Date:** 2026-11-02. **Stage:** internal demo only. No real customers.

## Implemented in the live Console source

- Authenticated **WhatsApp Inbox** tab for Admin/Reseller/User, scoped by account; tenant users can inspect a conversation and send a human reply.
- Official Meta Cloud API manual connection: sealed Access Token and App Secret; hashed user-supplied Verify Token; unique webhook id; GET challenge + POST HMAC SHA-256 verification.
- Incoming text + interactive messages, by-number isolation, Meta message ID deduplication, customer name, redacted audit, delivery/read event updates. Arbitrary attachments are marked as needing a human.
- Genuine Meta message send after a read-only phone-number credential verification; **never** classify HTTP 200 as delivered. Free-form reply only within the customer's last 24-hour support window.
- Staff manual reply **pauses AI 15 minutes**, and optional persistent human lock is released explicitly. Generation counters invalidate old draft jobs after a human intervention.
- AI uses **WHMCS AI Support Agent only**, via a separate, explicitly verified WHMCS addon bridge with strict host/path validation, public DNS, pinned HTTPS, bearer secret and **mandatory independent reviewer**. No Voice Gemini substitute, no WHMCS billing writes, no auto-import of the old WhatsApp Notifications module.
- Durable WHMCS inbox AI jobs and a CLI worker at `saas/console/whatsapp-worker.php`. The webhook fast-CGI/LiteSpeed request is acknowledged before best-effort job progression.
- Simulation tests with fake Meta and WHMCS transports; no claims of actual Meta credentials, live deliveries or a deployed original WHMCS bridge.

## Required from the original WHMCS AI Support Agent addon — NOT YET INSTALLED OR VERIFIED

The original AgentEngine in the accessible Lab source is **ticket-oriented** (`AgentEngine::process(ticket_id)`). It has no proven public WhatsApp channel draft endpoint. Therefore setting a URL or saving an API key **does not activate AI replies**.

A separate addon-owned bridge at `https://services.ontrackegy.com/modules/addons/ai_support_agent/<verified-name>.php` must be installed after inspection of the REAL WHMCS addon, with:

1. Strong independent server-side authentication (bearer token), rate limiting, nonce/request idempotency and zero customer-data leakage.
2. `POST {"action":"capabilities","channel":"whatsapp","probe":"ontrack_whmcs_channel_bridge_v1"}` returning HTTP 200 **only when actually ready** with:
   `{"agent":"ai_support_agent","channel":"whatsapp","ready":true,"reviewer_required":true,"tool_scope":"read_only"}`.
3. `POST` containing `{"action":"draft_reviewed_reply","channel":"whatsapp","version":1,"conversation_reference":"wa:<id>","external_contact":"<Meta wa_id>","customer_identity_verified":false,"allowed_actions":[],"history":[{"role":"customer","text":"..."}]}`. Return a response only after the existing WHMCS AI Support Agent and its independent reviewer finish. Accepted:
   `{"agent":"ai_support_agent","decision":"reply","reviewer_approved":true,"actions":[],"reply_text":"..."}`. For escalation return `decision=handoff` or `no_reply`. Never fabricate WHMCS invoice/service verification on WhatsApp Caller ID alone.
4. No mutating WHMCS ticket/billing or server actions from WhatsApp bridge without separate, verifiable authorization.
5. End-to-end tests on the owner's DEMO Meta number and the actual installed addon. Test Meta outbound acknowledgements and delivery receipts separately; test human-reply concurrency and duplicate webhooks.
6. Schedule trusted PHP 8.3 cron worker, e.g. every minute, **only after verifying the actual bridge**. Neither this code commit nor the GitHub deploy pipeline silently modifies crontab.

## Operator URL

`https://app.ontrackegy.com/saas/console/?tab=whatsapp`

The connection creates a draft. Run **Meta credential test**, configure its callback URL and verify the Meta webhook, then test a real inbound message before asking the live WHMCS addon to verify the bridge. If an installed endpoint is absent, the WHMCS AI status remains **unverified** and automatic responses remain OFF. Manual reply still needs a genuinely verified Meta sender.

## Safety/limitations

- WhatsApp Notifications for WHMCS is a separate, unmodified module. A second webhook cannot silently replace or duplicate its existing Meta subscription: coordinate callback routing/forwarding with the actual owner-owned WhatsApp module.
- No real customer accounts or production auto-messaging were activated. A successful integration simulation is not the same as Meta/WHMCS live E2E.
- Service providers must follow Meta's conversation windows/templates, customer opt-in and sending restrictions.
