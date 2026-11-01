# OnTrack Voice — session entry

> **Authoritative 2026-11-01 owner contract:** [Approved master decisions](docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md). Q01–Q24 are answered, Q07/Q13 delegated options resolved. This overrides earlier pending-approval notes. Before production features, read the checkpoint and show evidence for CI, host, auth UI, device E2E separately. The signed hook payment path intentionally stages an unclaimed paid order, not identity by email; verified account claim/SSO is still required before activation.\n\n
> **Read first:** [Owner decisions + pending questions (2026-11-01)](docs/discovery/OWNER_DECISIONS_AND_OPEN_QUESTIONS_2026_11_01_AR.md). Newer owner-approved decisions override contradictory legacy status notes. The Admin login works for the owner; separate assistant authenticated E2E verification remains open.\n\n
Read [AGENTS.md](AGENTS.md), then [CURRENT_STATE_AR.md](docs/project/CURRENT_STATE_AR.md), before changing anything.

This repository includes existing PHP voice demo code, a private GitHub host-control worker, and imported Agent Builder specifications. It is not a completed telephone SaaS. Continue from the verified checkpoint and preserve working host connectivity.

Authoritative decisions: [DECISIONS_AR.md](docs/discovery/DECISIONS_AR.md). Current implementation roadmap: [IMPLEMENTATION_PLAN_AR.md](docs/plan/IMPLEMENTATION_PLAN_AR.md). Phone audio remains unverified; do not rebuild pairing/UI and present that as call success.

Workspace directive: application code only in `saas/`; host target `/home/appontrack/public_html/saas`. Memory/logs/prompts stay on GitHub under `docs/`, never in the host application. Read `docs/memory/CHECKPOINT.md` and `docs/execution/MASTER_EXECUTION_PROMPT_AR.md`; current scope is preparation before the owner starts implementation.
