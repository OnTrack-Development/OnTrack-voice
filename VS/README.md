# VS — Voice Changer (independent Android application)

This directory is the isolated workspace for **VS**, a separate Android APK within the existing OnTrack-voice repository. It must use a distinct Android applicationId, release configuration, and local data storage; do not reuse or modify the existing OnTrack Voice APK or SaaS to develop VS.

## Owner-approved scope (2026-11-08)

- **Provider:** Fish Audio only; no Gemini/AI conversation agent or other voice provider.
- **Configuration on device:** Fish Audio API key (encrypted via Android Keystore), manual **Voice ID** (`reference_id`), and **three on-device performance personas**. Each persona can use the default ID or its own saved Voice ID. A voice browser/list may be added later; manual Voice ID stays first priority.
- **Intended flow:** during a regular SIM phone call, capture the owner's speech, transcribe it, synthesize that same text with the configured Fish Audio voice, and deliver only the generated voice to the far end. Preserve what the user says; do not have AI compose replies.
- **Native floating controls:** show an on-screen overlay, enable/disable voice changing, and a **Take Over / استلام المكالمة** control to terminate the Fish processing path and restore normal human call audio. Do not use AccessibilityService.
- **Critical gate:** do not claim or enable original-voice isolation until actual two-phone tests establish that the other party cannot hear unmodified microphone audio. Confirm capture, routing, handoff, latency, Wi-Fi/mobile-data behavior, and failure recovery on the target device. Do not equate setting Android microphone mute with verified telephony uplink isolation.
- **No website, separate SaaS dashboard, or new repository.** Build VS as an independent APK. This README records scope, **not** completed implementation.


## Three Drama 3 performance personas (prototype)

The Android app has three selectable presets, with a radio selector, description, a sample phrase, and independently stored Voice ID:

1. **روشنة ومرقعة** — expressive cue `[تنهيده]`.
2. **حنية واشتياق** — expressive cue `[اشتياق]`.
3. **دراما وتنهيدات** — expressive cue `[تنهيده مثيره]`.

Fish TTS always uses the user's `drama-3` model and `/v1/tts` endpoint. The cue is prepended to text for TTS only; it does not rewrite or invent the user's speech, and it is not an LLM-generated conversational persona. Fish's actual interpretation of cue tags and the audible effect must be validated on device. The same default `reference_id` can be used for all three; each mode can have its own saved value. The API key is never committed or embedded.

## Mandatory separation from AI Voice Assistant

The owner has explicitly declared `VS/` a future, independent, **test-only placeholder**. It is not part of the AI Voice Assistant codebase. During AI Voice Assistant development, **do not open, edit, copy, import, build, execute, test, deploy, or reuse anything in VS**. Also do not share code or other resources in the opposite direction without a specific, direct owner instruction. The earlier exploration of the existing voice bridge was only background research, **not permission to reuse code**. All agents must follow [AGENTS.md](AGENTS.md) for the complete prohibition and exception rule.

## Current live-audio privacy requirements (owner correction, 2026-11-08)

- **Do not use JemRec.** The owner does not want a call-recording application or any recording component of the other project.
- **Never record or persist phone calls.** Current VS microphone test uses bounded `AudioRecord` PCM in RAM only; sends the sample to Fish Audio for required transcription. The sample is cleared after processing. No call recordings, call logs, or uploaded audio archives.
- `RECORD_AUDIO` is only for the microphone live signal, not permission to save calls. The unprivileged version still does **not** inject Fish audio into the SIM uplink; do not claim otherwise.
- The intended final call bridge is a **new VS-specific live streaming bridge**, independent of JemRec and independent of the OnTrack Voice app/SaaS. The actual telephony TX routing is pending compatible device-level integration and a two-phone proof.

## Handset findings — 2026-11-08

- Owner tested v0.1.0 during an ordinary SIM call. The other person heard the original microphone, not the Fish audio. This is **confirmed failure of the intended voice-replacement requirement**, not a missing user permission.
- The ordinary Android app has no ability to insert its playback into the cellular modem's uplink. Fish Audio preview plays locally only. Standard grants for microphone and overlay do not change this.
- v0.2.0 scope: standalone native RTL dark/red design; Fish key status; preview-vs-call warnings; block local preview while a voice call is active; overlay remains visible after «استلام» and closes only by explicit action; permission audit differentiates preview grants from privileged phone routing.
- **Do not claim end-to-end SIM voice replacement.** A new telephony bridge requires explicit separate owner authorization and supported elevated/vendor integration on this handset, followed by two-phone proof of exclusivity, voice leakage, latency, and handoff.
- Do not copy any code from the OnTrack AI project into VS without a direct and specific owner instruction.
- Successful CI build and APK packaging do not prove call functionality.

## VS 0.4.0 (November 8, 2026) - interaction fixes

- Added three Fish Drama 3 intensity levels: **Natural** (no tag), **Gentle** (default), **Moderate** (restrained, without loud bursts or synthetic sighs). The prior always-on expressive sigh tags were removed. The underlying cloned voice can still produce variable results; verify on a real device.
- Floating UI starts as a **58dp round bubble**; tap to expand/collapse; drag to move; **green enable** and **red stop** preview controls; explicit close button. No forced closure on takeover, and no Accessibility permission.
- Added **non-invasive SIM route diagnostics** showing the observed call audio mode and telephony output. This does **not** unlock, intercept or inject real SIM audio. Toggling green while on a SIM call does not transmit anything to the remote party.
- **Unresolved acceptance gate**: full-duplex SIM microphone capture after original-mic mute, stable Fish ASR+TTS latency and actual uplink injection. Requires a separate VS-specific privileged audio bridge and real two-handset confirmation. The ordinary Android app does not get privileged uplink access merely through runtime permissions.
- No JemRec, no call recordings, no call log and no reuse of OnTrack AI application code. The current Fish speech preview uses only volatile in-memory PCM and MP3.

## Experimental VS 0.5.0 shell call bridge — NOT verified on handset

- Implemented a separate VS-only minimal Android `app_process` ADB shell helper in `VS/shellbridge` (not JemRec). The Windows CI compiles this source with the Android SDK and embeds a DEX JAR as an APK asset.
- In the app tap **تجهيز الجسر ونسخ أمر ADB** to export the helper into app-specific files and copy the exact `adb shell` command. Run the command using an already-authorized USB or Wireless Debugging connection to this owner's phone. The bridge must be re-launched after phone restart. No root, no Accessibility, and no phone-call recording.
- With a regular active SIM call, tap green **تشغيل**. The shell helper checks for a real `TYPE_TELEPHONY` device, requests that audio route and reads it back using an inaudible PCM preflight. Only then does it try muting the original microphone, saving its prior state for rollback. It returns diagnostic errors on failure.
- The VS microphone foreground service attempts `AudioRecord` while the SIM microphone is muted, in transient approximately two-second RAM chunks. Fish ASR transcribes only the user's speech, Drama 3 synthesizes it, MediaCodec decodes to PCM16 mono 48kHz, and the app sends that PCM via an authenticated abstract Unix socket to the VS shell helper. No caller/downlink audio is captured or saved by the bridge.
- Press red **إيقاف** to close the session and restore the prior microphone state. Disconnect, permission denial, stream failure, or prolonged silence attempts the same rollback. A forced Android process kill may require manually unmuting the phone, so do not rely on this experimental feature for important calls.
- **Acceptance gate NOT PASSED:** on the owner's Xiaomi device we still need to prove (a) shell helper can start under ADB, (b) audio is routed to the remote caller rather than a dead output, (c) the VS microphone remains audible to Fish after call mute, (d) zero original voice leakage, and (e) reliable fail-safe restoration, Wi-Fi and mobile data quality. Do not describe successful APK packaging or a route readback as end-to-end call voice conversion.
- No code or secrets taken from OnTrack AI or its SaaS. Do not automatically add this experimental helper to the existing OnTrack Voice app.

## Status

VS v0.1.0 APK **was built on the Windows runner** and installed/tested on the owner's handset. The owner observed local Fish voice playback but **the SIM call still transmitted the real microphone**. VS v0.2.0 adds UI, permission, and overlay fixes; compilation must be checked on its new commit before delivery. The intended SIM voice replacement is **FAILED / BLOCKED under current unprivileged Android constraints**, not implemented. No two-phone success proof exists.

## VS 0.6.0 — OnTrack Voice call path parity (2026-11-09)

The owner explicitly authorized taking the **same native floating-call UI** and **shell VOICE_CALL PCM path** from OnTrack Voice into isolated VS. VS now has a 58dp draggable button, expanded 284dp RTL 2×2 grid, real start/stop, takeover, speaker and app actions. The experimental VS helper now captures stereo PCM16 at 48 kHz from the **shell's VOICE_CALL source**, matching OnTrack Voice's phone capture rather than trying to record the app microphone during a SIM call. In-memory left/right-channel downmix uses the original LinkPcm 48k-to-16k approach (user channel right), then Fish ASR -> Drama 3 -> 48k PCM -> shell telephony media/uplink route. Temporary buffers are cleared; no call files, automatic recording, or call archives are added to VS. Fish ASR necessarily transmits the user's brief audio sample to Fish's API for conversion.

**Remaining gate:** APK compilation and actual two-handset confirmation; the shell's audio route readback is not evidence that the far end hears exclusively Fish. Manual ADB helper start may still be necessary; OnTrack's fully automatic device-pairing lifecycle has not been ported. If the shell capture fails or post-mute audio becomes silent, VS stops and requests original mic restoration. The existing OnTrack Voice app, keys and SaaS are not changed.


## USB / Wireless debugging setup and proof (2026-11-09)

- **Owner's current defect, confirmed:** VS has no embedded Android ADB pairing client like the original OnTrack Voice application. Granting microphone/overlay permissions does NOT start the privileged bridge. Do not label a green overlay as SIM transmission proof.
- **Now available without fetching external project source:** `VS/tools/Connect-VS.ps1` is a repository-local Windows helper. It checks for Android platform-tools `adb` installed on the computer, supports `-Mode USB` for approved USB-debugging devices, and `-Mode Wireless -PairAddress IP:pairPort -DeviceAddress IP:connectPort` for on-device Wireless debugging pairing followed by connection. It validates the VS-generated command instead of executing arbitrary clipboard text. On the phone, open VS, tap **تجهيز الجسر ونسخ أمر ADB**, paste that generated command into the PowerShell helper prompt, then tap **فحص الربط الفعلي الآن**. The app also has a direct Developer Options shortcut and a separate live bridge state readback.
- **Not implemented:** direct on-phone wireless pairing using an embedded ADB client, boot-resilient automatic daemon startup, non-Wi-Fi transport lifecycle or guaranteed telephony uplink audio. The original OnTrack embedded ADB pairing implementation is supplied by source outside the checked-in repository's source tree, and cannot truthfully be called ported while work is limited to this repository. The Windows helper requires a computer with `adb`, not merely the APK.
- **Not proved by CI:** Pairing and shell helper startup on Xiaomi, VOICE_CALL capture after muting, exclusive Fish Audio far-end reception, and lossless takeover. Those require a real-device test and two handsets. No call files or call archives are added.
