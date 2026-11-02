# 2026-11-02 — WhatsApp standalone OnTrack Support Agent release

## Owner correction
"Like the WHMCS AI Support Agent" means the same customer-support behavior, conversation UX, smart buttons, human takeover and flow structure. OnTrack Voice must **not depend on WHMCS** to operate WhatsApp. Legacy WHMCS bridge remains an optional backward-compatibility adapter only.

## Evidence (internal demo only)
- Implementation snapshot: 3f91ee5c3b6b85ba9d50e6c40ce388b5336b88da (branch fix/standalone-whatsapp-support-agent-20261102).
- Native CI: GitHub Actions run 41459466372 SUCCESS — PHP lint, native AI two-pass reviewer, Meta quick replies, inbound button continuation, staff lock, and legacy regression simulations.
- Production release pipeline: run 41459587649 SUCCESS; uses pinned snapshot 3f91ee5...
- Host link receipt: ops/host-link/results/appontrack-server11/saas-console-41459587649.json on branch ontrack-host-link.
- Receipt: status success, exit_code 0; finished 2026-11-02T14:13:04Z; source 3f91ee5c3b6b, 56 files, target /saas/console/, PHP 8.3, backup=true.
- No real Meta message, Gemini response, provider token or live delivery was claimed. Neither CI nor host publish is real end-to-end proof.

## Safeguards
- Native OnTrack agent is opt-in OFF on each newly created channel; requires a published agent in that account/site and Meta verification.
- Outbound agent draft undergoes a separate reviewer request, including exact proposed buttons. Rejected or unconfirmed text is never sent.
- Human reply pauses AI 15 minutes and invalidates queued replies; permanent lock requires explicit release.
- Inbound interactive tap becomes a customer message, then a new native AI job. It does not execute external WHMCS/Laravel actions by itself.
- No credentials, secrets or caller data committed here.

## Open live checks
Meta challenge/signature + real inbound and outbound + delivered/read webhook receipts + actual OnTrack Gemini response and reviewer + staff race checks on owner's DEMO number. Flow Builder smart-button-to-action integration is separate and not yet proven.
