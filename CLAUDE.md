# OnTrack Voice — session entry

Read [AGENTS.md](AGENTS.md), then [CURRENT_STATE_AR.md](docs/project/CURRENT_STATE_AR.md), before changing anything.

This repository includes existing PHP voice demo code, a private GitHub host-control worker, and imported Agent Builder specifications. It is not a completed telephone SaaS. Continue from the verified checkpoint and preserve working host connectivity.

Authoritative decisions: [DECISIONS_AR.md](docs/discovery/DECISIONS_AR.md). Current implementation roadmap: [IMPLEMENTATION_PLAN_AR.md](docs/plan/IMPLEMENTATION_PLAN_AR.md). Phone audio remains unverified; do not rebuild pairing/UI and present that as call success.

Workspace directive: application code only in `saas/`; host target `/home/appontrack/public_html/saas`. Memory/logs/prompts stay on GitHub under `docs/`, never in the host application. Read `docs/memory/CHECKPOINT.md` and `docs/execution/MASTER_EXECUTION_PROMPT_AR.md`; current scope is preparation before the owner starts implementation.
