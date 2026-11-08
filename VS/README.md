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

## Status

Android **source prototype** exists in `VS/app/`: Fish TTS, experimental ASR recording, API-key storage, three personas, and preview controls. **APK build, runner execution, Windows checkout, Fish live response, cue quality and SIM-call audio-path isolation are NOT YET VERIFIED.** The preview plays on the device only; it is not a proven SIM-call voice replacement.
