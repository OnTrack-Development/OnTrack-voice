# OnTrack Voice — Stitch design intake (2026-11-07)

Source provided by project owner: `stitch_ontrack_new_initiative.zip` (SHA-256 `c7120fab9847a1d9a04410acd3c055bc013c22ec00b5603dd7610b2a5b69f492`). The attachment contains nine `code.html`/screen screenshot pairs, a palette screenshot, and `serene_clinical_intelligence/DESIGN.md`.

**Visual direction from the supplied archive:** retain the existing product features and navigation, use the well-spaced RTL cards, bottom-nav mobile composition, status chips, call summaries, and update center; do **not** import the medical-specific text, mock numbers, invented records, or unrelated business flows. Keep the existing Android native bridge and server account/SIM/Flow functionality.

**Palette (exact owner-selected reference):** Black Bean `#280B08`, Champagne `#F9E7C9`, Scarlet Red `#C92925`, warm canvas `#FBF7F0`. Secondary border `#E8DEC8`, muted text `#6E5D57`, green ready `#2E6F40`. See [tokens.css](tokens.css).

**Design checkpoints:**
1. Never hide the Native Android update center: always show check-update button and a manual download/install action only when the signed manifest confirms an update; preserve automatic in-app banner when one is available.
2. Separate UI OTA from native APK. A refresh must not silently begin a native install. Preserve incoming calls and SIM audio behavior.
3. Keep both mobile and SaaS designs responsive and RTL. Owner must see version, update result, and link to official signed APK when provided by server.
4. Design files are **references**, not a replacement for source app screens. No third-party secrets or Stitch API keys belong in Git.

The **complete original Stitch archive**, including all nine screenshot PNGs, nine HTML files, the owner palette screenshot, and the original `DESIGN.md`, is committed unmodified as [stitch_ontrack_new_initiative.zip](stitch_ontrack_new_initiative.zip). Verified SHA-256: `c7120fab9847a1d9a04410acd3c055bc013c22ec00b5603dd7610b2a5b69f492`. See [screen-map.md](screen-map.md).
