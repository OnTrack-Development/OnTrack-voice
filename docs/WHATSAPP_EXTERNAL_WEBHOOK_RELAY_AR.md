# OnTrack Voice — WhatsApp Webhook direct integration

## What the owner requested (2026-11-02)
The existing "old WHMCS AI Support Agent bridge" is **wrong** and removed from the WhatsApp channel form. The integration is with the **WhatsApp webhook receiver** belonging to the existing WHMCS WhatsApp Notifications module or any other WhatsApp integration, NOT with the WHMCS AI agent addon. OnTrack's own independent AI agent handles replies; do not add support-ticket functionality.

## Two correct operating modes
1. **Direct Meta Cloud API**: The authorized tenant enters WABA ID, Phone Number ID, Meta App ID, App Secret, Access Token, Graph version. OnTrack generates its own private per-connection callback and verify token, then the tenant points Meta WhatsApp webhook configuration at the OnTrack callback. Verify Meta asset information and actual inbound delivery before calling it connected.
2. **Existing WhatsApp receiver / webhook relay**: An existing WhatsApp Notifications module, or another provider that already receives Meta events, may keep its current callback. It forwards a copy of the *already verified original Meta event body* to the tenant-specific OnTrack Callback URL. It signs the POST using the freshly generated per-channel 256-bit key and the header X-OnTrack-Webhook-Signature-256: sha256=<lowercase hexadecimal HMAC SHA-256>. OnTrack accepts only verified signatures; same Meta Phone Number ID must match; message IDs are deduplicated. Do NOT put the old module's incoming webhook URL in a new "AI bridge URL" field; posting arbitrary data there does not connect the two systems.

The source integration must actively forward messages; OnTrack cannot passively read a webhook belonging to someone else. Optional drop-in PHP forwarder for verified original Meta bodies: integrations/whatsapp-webhook-relay/forwarder.php. It is not automatically installed in the WHMCS module; the WhatsApp Notifications module's code/host needs a separate authorized edit and end-to-end validation. Never assume that installer has happened.

## Generic normalized event format
An integration that can't forward the original Meta JSON may send this JSON object instead (UTF-8), signed in exactly the same way:

```json
{
  "version": 1,
  "channel": "whatsapp",
  "event": "message.received",
  "phone_number_id": "THE_CONNECTED_META_PHONE_ID",
  "message": {
    "id": "THE_PROVIDER_STABLE_MESSAGE_ID",
    "from": "201000000000",
    "name": "Demo contact",
    "text": "Hello"
  }
}
```

HTTP POST to the OnTrack Callback URL generated for this channel. Body must be signed **as raw bytes**, before sending, with the OnTrack signing key; no URL query secret. This contract accepts incoming *text* only. Official Meta events (text, buttons, status receipts) can be forwarded unmodified. Do not infer that arbitrary third-party JSON message shapes or outgoing send APIs are already supported.

## Outbound behavior
This version keeps the user's own Meta Cloud API Access Token/Phone Number ID to send AI replies and WhatsApp interactive buttons, even when the original incoming message was forwarded by another receiver. A third-party inbound webhook **is not** automatically a send-message API. AI remains OFF until the user explicitly enables a published OnTrack agent after Meta verification. It does not call WHMCS AI Support.

## Security and acceptance
- Each callback belongs to the authenticated account; DB lookup verifies Phone Number ID and tenant, never trusts a requested account ID in the event.
- Signing key encrypted at rest and shown on initial setup only; wrong signature and altered raw body rejected. Do not expose it in logs, links or Git.
- Both Meta original X-Hub-Signature-256 and the independent relay HMAC are supported; cross-tenant events and duplicates must be rejected.
- Source module must verify Meta authenticity before forwarding; untrusted arbitrary remote endpoints must not be called.
- A successful test with mocked Meta is not a live integration. Evidence requires an actual signed event from the existing module -> OnTrack inbox -> OnTrack agent -> actual Meta accepted/delivered callbacks. Do not claim this until tested.

## Progress
Implemented as code candidate in feature branch feat/whatsapp-manual-credentials-ui-20261102. No claim of module-side deployment or existing module forwarding until a real test confirms it.
