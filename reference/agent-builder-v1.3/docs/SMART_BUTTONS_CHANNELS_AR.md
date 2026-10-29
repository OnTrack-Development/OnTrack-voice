# Dynamic Smart Buttons & Omnichannel — Functional Spec

## Goal
Preserve the **dynamic state-aware action button idea** the owner developed for OnTrack WhatsApp/chat, but implement it as a reusable tenant-isolated platform engine independent of WHMCS. A button is not only a label: it is a policy-checked, versioned presentation of an allowed action for a verified subject in a known real business state.

## State source truth
`BusinessContextResolver` -> known customer identity + project data from authorized connector -> canonical business state + revision/version. `RuleEvaluator` -> candidate buttons -> filter by tenant/project/agent, verified identity, tool permission, channel capability, expiration -> signed presentation tokens. `ButtonInvoke` -> re-fetch state and authorize again -> show confirmation/approval if needed -> dispatch idempotent tool -> verify persisted result -> publish new state -> recalc visible buttons.

Sample workflow **synthetic demonstration only**:
```
GUEST -> identity not verified -> [التحقق من الحساب، تحدث مع موظف]
VERIFIED -> booking found pending -> [تفاصيل الحجز، تعديل الموعد، تحدث مع موظف]
CHANGE_REQUESTED -> requires confirmation -> [تأكيد التعديل، إلغاء]
UPDATED -> backend confirms booking new state -> [عرض التفاصيل، حجز جديد]
HUMAN_LOCKED -> [متابعة مع الموظف] (AI actions disabled)
```
Do not change the button because the LLM *claims* the state changed; require signed event / verified API result. If source times out: show safe general options only, no privileged action.

## Button rule data contract
- `rule_id`, `agent_id`, `project_id`, `label.ar`, `label.en` (draft future), `icon`, `priority`, `channels[]`, `action_key`, `min_identity_level`, `permissions[]`, `business_state_in[]`, `condition_ast`, `confirmation_text`, `expiry_seconds`, `version`, `status`.
- Conditions use a safe structured builder: **all/any** with typed allowed fields (booking.status, customer.verified, order.total). No eval/string PHP expressions or unchecked SQL.
- Rule engine server-side; channel renderers consume filtered **presentation objects**, not editable raw conditions.
- Reject expired/foreign-state/foreign-user presentation tokens; cannot change action key through client payload.

## Web Chat
- Provide embeddable JS/iframe isolation by design: CSP, origin allowlists, strict postMessage checks, isolated styling/theme props. Widget session token created by backend identity binding, not exposed provider credentials.
- Accessible keyboard buttons, send feedback, action pending spinner, undo where possible, live state changes, no duplicated click. Realtime push or bounded polling fallback.
- Dynamic list may display 2–5 context actions; status badge and read receipt only when real.
- Embed generator shows script snippet that loads served production bundle and public project/widget ID, not placeholder URL masquerading as live.

## WhatsApp (Official Meta Cloud API)
- Adapter layer independent of provider model. Receive webhook after signature/verification, dedupe message IDs, parse channel message/interactive replies; only send if channel configured and verified.
- In-session interactive message options and template-based notifications obey Meta current per-message, conversation-window and template restrictions. Limits/eligibility can change; use provider adapter capability check. Fallback to text/numeric choices if interaction type unavailable.
- Do not confuse approved Broadcast template buttons with unrestricted context-driven WhatsApp buttons; implement supported interactive flows and graceful fallback.
- Phone identity is NOT automatic account verification for sensitive customer data; enforce verification challenge via connected business or explicit signed identifier.
- Use channel event queue and delivery ledger; retry with backoff and idempotency, never claim success from HTTP enqueue alone.

## Handoff and shared Inbox
- `AI_ACTIVE` -> human request or policy escalation -> `HUMAN_LOCKED` with assignee and reason; AI output generation might already be in-flight, but delivery must **re-check lock** before send and reject stale queued messages.
- Agent and staff status changes are audited; only staff with proper scope can release lock.
- Messages attributed to AI vs staff vs customer explicitly. Cross-channel CRM linking only after verification; history displays origin channel and timestamp.

## Demonstration acceptance
1. Demo starts as guest with two allowed buttons.
2. Clicking mock verification visibly moves to verified state **labeled as demo**, then adds booking actions.
3. Confirming permitted sample action once updates canonical mock business state and the buttons change.
4. Duplicate click with same presentation idempotency key has no repeated result.
5. Human takeover hides privileged AI action controls and blocks AI replies.
6. Automated tests cover guard denial, cross tenant, race condition, stale token and channel fallback.

## Meta channel onboarding modes — must implement all three
See `META_WHATSAPP_ONBOARDING_AR.md` for the approved **Embedded Signup / Coexistence / Manual Meta Cloud API** connection methods, fields, tenant-per-connection secret storage, webhook verification, multi-number management, delivery E2E evidence, and per-number agent routing. WhatsApp channel credentials can be entered by authorized customer administrators; this does not imply access to OnTrack's AI provider credentials. Show 3 onboarding choices on the customer's Channels > WhatsApp screen.

## WhatsApp Cloud API independent Inbox + staff reply pause (v1.3)
A Cloud API-only WhatsApp number gets its own realtime operational Inbox and all Meta-permitted media, emoji and interactive message formats. A human staff outgoing message starts/extends AI timed suppression **only in that conversation**, default 15 minutes configurable by the tenant owner in minutes. This temporary mode is not the permanent `human_locked` state; automatic resumption never overrides that state. Never send a stale queued AI answer after a manual staff reply. Full UX, state and tests in `docs/WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md`.
