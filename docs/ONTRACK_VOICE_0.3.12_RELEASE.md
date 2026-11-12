# OnTrack Voice 0.3.12 — verified owner test release

- Native version: **0.3.12**, Android versionCode **35**.
- Release build: R8 minified and resource-shrunk Android **release** APK.
- APK size: **10,915,364 bytes**, versus 54,995,571 bytes for the prior 0.3.11 debug APK (**80.15% smaller**).
- APK SHA-256: `c4180c75c9b245821d3c5d95e624e727801e4137ad665b0c21023d22675098da`.
- Signature: original installed signing identity, certificate SHA-256 `d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f`.
- UI OTA revision: **32**, compatible with native version codes **34–35**, runtime 1.
- Package: `com.ontrack.voiceproof`.
- Build workflow run: [41756530712](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/41756530712) — Android build succeeded, targeted unit/daemon tests succeeded, UI contract and signed OTA regression checks succeeded, signature + package/version + manifest verified.
- One-time host publication run: [41758139629](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/41758139629) — succeeded.
- Verified host receipts on branch `ontrack-host-link`: `mobile-ota-41758139629` (UI revision 32 installed) and `mobile-native-41758139629` (native version 35 published, SHA-256 matches).
- Host OTA path: `mobile/updates/native/ontrack-voice-v35-c4180c75c9b245821d3c5d95e624e727801e4137ad665b0c21023d22675098da.apk`.

## Interface and updates
Five bottom tabs: Home, Calls, Campaigns, Device, Settings. SIM management moved to Device, voices moved into Settings, campaign controls grouped by purpose. A visible in-app update banner, tab dot and header shortcut appear when the signed manifest indicates a newer Android package; tapping opens Settings → Updates, then Android's package installer requests approval. Existing tenant pairing, account data, contact consent and call history are preserved by the build changes. Android auto-install without explicit OS consent is not supported.

## Verification boundaries
- Real mobile handsets and full-duplex call/audio **have not been validated by this build pipeline**; owner handset smoke test remains necessary.
- The full upstream test suite previously reported 13 failures out of 746; this release gate ran its targeted OnTrack unit and daemon tests successfully instead of claiming the full upstream suite passed.
- External public HTTP download from the host could not be verified through the browser tool; trusted host worker receipts attest to successful publication.
- No private keystore was uploaded, cached, committed or placed in the signed OTA artifact.
