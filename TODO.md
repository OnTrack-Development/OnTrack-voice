# OnTrack Voice — Active TODO (2026-11-05)

**Execute strictly in order. Current priority: 01 only.** Detailed scope, acceptance gates and evidence live in [the canonical Arabic execution checklist](docs/plan/ACTIVE_EXECUTION_TODO_2026_11_05_AR.md). No unchecked task may be labelled complete just because code was merged or HTTP 200.

- [ ] **01 — P0 / IN_PROGRESS:** Google Live key rotation **during** the same call; 900-second cooldown, same-tenant pool, bounded conversation continuity. [Implementation PR #52](https://github.com/OnTrack-Development/OnTrack-voice/pull/52) — PHP tests passed; Android compile + physical SIM E2E required.
- [ ] **02 — P0:** Admin simulation of 10–20 concurrent calls, per-call latency/failures.
- [ ] **03 — P0:** Android 5-second auto-answer and verified bi-directional SIM audio.
- [ ] **04 — P0:** Mobile-data (4G/5G) audio/latency parity with Wi-Fi.
- [ ] **05 — P0:** Stable signed Android APK, in-app OTA notification/update, verified delivery.
- [ ] **06 — P1:** Consent-aware call recording bound to the call history.
- [ ] **07 — P0:** Call summary, statuses, post-call employee escalation in 10 seconds.
- [ ] **08 — P1:** Admin/reseller/customer auth + white-label registration/domain/roles.
- [ ] **09 — P1:** Reliable Agent Studio/Flow Builder, published flow inside live call.
- [ ] **10 — P1:** AYO /io mic, text, streaming speech, 403s, STT, latency, UI/lipsync.
- [ ] **11 — P1:** Voice providers and per-tenant credentials, quotas and fallback.
- [ ] **12 — P1:** Official WhatsApp Cloud API / coexistence / manual/auto + WHMCS.

**Evidence rule:** CODED → CI_PASS → HOST_DEPLOYED → DEVICE_E2E_VERIFIED (when SIM/audio is involved). Update the canonical checklist for every transition. Do not start #02 while #01 lacks acceptance or an explicit BLOCKED reason.
