# OnTrack AI Phone Bridge — Android POC v0.1.0

First Android edge connector for `agent.ontrackegy.com`.

## Implemented
- Pair device with 6-digit dashboard pairing code.
- Store server-issued bearer token locally.
- Request Android Default Dialer role.
- `InCallService` incoming/outgoing call detection.
- Foreground heartbeat + job polling.
- Outbound `place_ai_call` jobs dial through the phone SIM via `TelecomManager.placeCall`.
- Report outbound states to `/api/device/call-update.php`.
- Report inbound ringing / answered / ended to `/api/device/incoming-event.php`.
- HTTPS-only server configuration.

## v0.1 safety boundary
Automatic answer and carrier conference / AI bridge are deliberately disabled in this build. This first field test validates device pairing, background connectivity, call detection and SIM-originated dialing before the conference experiment is enabled.
