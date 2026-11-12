# OnTrack Voice — Root release and AI Relay status (2026-11-03)

## Confirmed host migration
- Canonical public application: https://app.ontrackegy.com/ ; PHP app files physically in /home/appontrack/public_html (not a wrapper or redirect to /saas/console).
- Existing root backup: /home/appontrack/backup/ontrack-full-2026-11-03/account-files.tar.gz (~953 MiB). Manifest SHA-256: df65e6adc95b22ae19be4be7e3c57e9cea9f1d87735d0a7e1b712ef8d9479bc8. Includes private account files and a separate SQLite consistent snapshot; do not publish or link backup over HTTP.
- Old document root preserved at /home/appontrack/backup/ontrack-full-2026-11-03/legacy-public_html; every later root deploy keeps its own private rollback under /home/appontrack/backup/releases/.
- Real cutover verified: root /login HTTP200, /assets/css/app.css HTTP200 and legacy /saas/console/login HTTP200. /saas/mobile is a compatibility symlink to /mobile; existing native Android/OTA paths must continue working without a forced APK update.
- Root deployment publisher changed from /saas/console to direct root; private rollback preserved; old session cookie path normalized to /.

## AI Relay
- Admin > Settings: two separately ordered unlimited pools: Voice Gemini Live credentials; Assistant Google Live, Google Text REST and HTTPS OpenAI-compatible Custom endpoints with model IDs. Add/edit/delete, enable/disable, move priority up/down, real connection test and reset cooldown.
- AES encrypted secrets retained privately; no API keys in GitHub. Per-provider cooldown 15 minutes for 429 / 503 overload and transport timeout; next eligible provider selected for a new request.
- One-time copy of the previous encrypted Google Live key into Voice Pool on first access. Assistant **does not use Voice Relay providers** when its own pool is empty: it retains the pre-existing platform legacy Google credential until configured separately.
- Failover for Android occurs when creating a new ephemeral Live session, not by seamlessly replacing a running GSM Live WebSocket. Real Xiaomi end-to-end handoff is not proven.
- Assistant proposals remain reviewed drafts; no auto-publish. Gemini 3.8 Live missing function calls remains a known provider E2E issue; configure a proven Google Text or OpenAI-compatible provider as a separate fallback if desired.
- Root PHP lint, dedicated CI and host receipts are independent. Only call a release deployed after checking the matching host receipt; broader WhatsApp/WHMCS regression is separately failing and not in scope for voice-first.

## Restore
- Backup manifest is private: /home/appontrack/backup/ontrack-full-2026-11-03/status.json.
- Never restore private database or move original root without a maintenance window and first verifying the active device sessions and current latest snapshot. Root release rollback can rename /home/appontrack/backup/releases/<release-sha> to /home/appontrack/public_html with a newly backed-up current root.
- Keep mobile compatibility routes and scheduled Cron updates in every future release.

## Root migration: historical mobile Pilot dependencies

The original handset Pilot `/saas/mobile/session.php` depended on the previous root `/api/live_session.php`, `/lib/OutboundMission.php` and local demo/provider settings. When the old root was archived, that relative dependency broke. The deployed root now keeps its Pilot server builder in `/home/appontrack/public_html/mobile/pilot_api/` (HTTP denied by its .htaccess), with any legacy API credential file stored under `/home/appontrack/.ontrack-mobile/` mode 0600. The new source `saas/mobile/server/session.php` first uses this protected runtime with a fallback path for older installations. The root publisher copies the complete existing `/mobile/` runtime on every release, so the protected dependency is retained and no Android re-pairing is needed. Pilot producer API verification is distinct from real handset audio E2E; don't declare GSM audio proven solely from an HTTP/CLI smoke.
