# VS — Voice Changer (independent Android application)

This directory is the isolated workspace for **VS**, a separate Android APK within the existing OnTrack-voice repository. It must use a distinct Android applicationId, release configuration, and local data storage; do not reuse or modify the existing OnTrack Voice APK or SaaS to develop VS.

## Owner-approved scope (2026-11-08)

- **Provider:** Fish Audio only; no Gemini/AI conversation agent or other voice provider.
- **Configuration on device:** Fish Audio API key and a manually entered **Voice ID** (Fish Audio `reference_id`), saved locally and securely. A voice browser/list may be added as an optional alternative, but manual Voice ID is first priority.
- **Intended flow:** during a regular SIM phone call, capture the owner's speech, transcribe it, synthesize that same text with the configured Fish Audio voice, and deliver only the generated voice to the far end. Preserve what the user says; do not have AI compose replies.
- **Native floating controls:** show an on-screen overlay, enable/disable voice changing, and a **Take Over / استلام المكالمة** control to terminate the Fish processing path and restore normal human call audio. Do not use AccessibilityService.
- **Critical gate:** do not claim or enable original-voice isolation until actual two-phone tests establish that the other party cannot hear unmodified microphone audio. Confirm capture, routing, handoff, latency, Wi-Fi/mobile-data behavior, and failure recovery on the target device. Do not equate setting Android microphone mute with verified telephony uplink isolation.
- **No website, separate SaaS dashboard, or new repository.** Build VS as an independent APK. This README records scope, **not** completed implementation.

## Mandatory separation from AI Voice Assistant

The owner has explicitly declared `VS/` a future, independent, **test-only placeholder**. It is not part of the AI Voice Assistant codebase. During AI Voice Assistant development, **do not open, edit, copy, import, build, execute, test, deploy, or reuse anything in VS**. Also do not share code or other resources in the opposite direction without a specific, direct owner instruction. The earlier exploration of the existing voice bridge was only background research, **not permission to reuse code**. All agents must follow [AGENTS.md](AGENTS.md) for the complete prohibition and exception rule.

## Status

Workspace created. VS Android source, Fish integration, standalone APK, runner build, local PC checkout, and live SIM-call validation remain **NOT IMPLEMENTED / NOT VERIFIED**.
