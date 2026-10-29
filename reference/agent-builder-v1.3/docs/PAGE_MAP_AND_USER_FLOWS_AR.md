# خريطة الصفحات والـUser Journeys

Use `machine/pages.csv` as a machine-readable initial inventory, but this document defines acceptance intent. Every visible menu entry MUST resolve to real route with at least appropriately truthful functionality; future functionality should show clear `قريبًا` state instead of pretending it's complete.

## Public marketing site (SEO + AR RTL)
| Route | Title | Sections / Content |
|---|---|---|
| `/` | الرئيسية | Hero + demo interactive + features + workflows + integrations + industries + proof-of-capability + FAQ + CTA |
| `/platform` | المنصة | Architecture story, tenant isolation, roles, agent operating lifecycle, demo |
| `/features/agents` | بناء الوكلاء | Agent Wizard/Studio + configurations + Playground + review |
| `/features/smart-buttons` | الأزرار الذكية | Four live business state examples + behavior explanation + channel differences |
| `/features/knowledge` | قاعدة المعرفة | Upload/FAQ/sync, scopes, status, citations |
| `/features/integrations` | التكاملات | Laravel/WordPress/WHMCS/REST/Webhooks showcase with truthful status |
| `/features/channels` | القنوات | Web Chat/Meta WhatsApp and future platforms, honest availability |
| `/features/inbox` | المحادثات وCRM | Human takeover, verified customer profile, inbox preview |
| `/features/operations` | غرفة العمليات | traces, audit, reviewer, performance, concrete examples |
| `/developers` | المطورون | API auth, quick start, signed identity, sample request, test sandbox |
| `/industries` | القطاعات | cards all business types + other; no forced first vertical |
| `/industries/{slug}` | 7 sector pages | per-sector copy, realistic workflows, demo CTA, shared template |
| `/pricing` | الباقات | Feature groups/configurable pricing; no fictional published price until approved |
| `/security` | الأمان | tenant isolation, credentials, action approvals, identity |
| `/enterprise` | المؤسسات | compliance/process, custom controls, contact sales, no unsupported certifications |
| `/about` | عن OnTrack AI | product mission + owner brand, no invented employee counts |
| `/contact` | تواصل معنا | validated contact form with delivery route and spam control |
| `/docs` | التوثيق | developer intro, guides and linkable page indexes, not dead page |
| `/login`, `/register`, `/forgot-password`, `/verify-email` | الدخول والتسجيل | functional auth and recovery |

## Dashboard route family (Authenticated + tenant context)
Use `/{workspaceSlug}` style only if tenant context cannot be forged; prefer stable IDs/UUID where collisions possible. Sample:
`/app/{workspace}/overview`, `/app/{workspace}/projects`, `/app/{workspace}/agents`, `/app/{workspace}/agents/create`, `/app/{workspace}/agents/{agent}/studio`.

### Core navigation
- **Overview**: accurate counts by tenant, action setup checklist, recent genuine activity, product health badges truthful.
- **Workspaces/Projects**: CRUD + environment isolation, role checks, search, empty/loading, integration status.
- **Agents**: grid/table, draft/published states, create wizard, duplicate safely, edit, version history, archive, publish.
- **Agent Studio (Customer)**: tabs `التعليمات`, `الأدوار`, `الإمكانيات ضمن الباقة`, `المعرفة`, `الأدوات`, `التجربة`, `النشر`; **لا مزودات ولا موديلات خام ولا مفاتيح AI**؛ Playground and real assistant response when OnTrack managed service is available within quota.
- **AI Provider Hub (Super Admin ONLY):** `/control/ai/providers`, `/control/ai/models`, `/control/ai/routing`; connection profiles, tests, manual model IDs, Writer/Reviewer/Fallback. Completely absent from Tenant routes/navigation/APIs.
- **Knowledge Hub**: upload jobs, scope, ingestion, versions, retrieval test, source references.
- **Smart Buttons**: condition/rule builder, state map, auth scope, ordering, per-channel preview, execute-test mode.
- **Integrations**: connectors, permission matrix, webhooks, generic API schema, dry-run, audit, signed identity SDK references.
- **Channels**: Web widget embed script and preview; WhatsApp Meta setup state with credentials and compliance instructions.
- **Inbox**: conversation list by actual tenant, verified identity flag, assignment, AI pause/Human Lock, messages, event history.
- **CRM**: contact list, verified/unverified identities, tags, channel links, notes, lead status, import/export only if authorized.
- **Operations**: live event stream only when active; filter by project, agent, provider, request and reviewer; trace details.
- **Analytics/Usage (Tenant):** real aggregates, entitlement/plan quota, consumed/remaining units, no upstream provider names/model IDs/internal cost or profit. Platform-only analytics shows provider/model distributions and costs.
- **Team/Roles/Billing/Settings**: permissions, invite and revoke, scoped roles, key management, billing placeholder without charges until chosen.

## Four end-to-end user journeys
### A. Getting Started
Public CTA -> register + verify email -> choose available plan (or pending pricing/no-charge demo mode) -> create tenant (industry optional) -> workspace -> project -> agent template / custom -> knowledge -> playground test using OnTrack-managed AI -> publish widget snippet -> install in test webpage -> messages appear in Inbox. **No provider setup by tenant.** Preserve wizard progress transactionally.

### B. Custom model — PLATFORM SUPER ADMIN ONLY
OnTrack internal `/control/ai/providers` -> add custom profile -> HTTPS URL + API key -> connection test -> enable model entry -> add exact IDs -> per-model generation test -> assign internal Writer/Reviewer/Fallback routing by plan/feature -> save/reload -> tenant playground consumes managed service without seeing internals. No tenant may access `/control` even by guessing URL; no unsupported active state if test failed.

### C. State-aware action
Signed web session verified -> customer asks about booking -> backend tool returns owned booking and `pending` state -> show `متابعة الحجز` / `تعديل الموعد` (if policy) -> action requires confirmation -> server executes idempotently -> re-fetch latest booking state -> new buttons reflect confirmed reality. Replaying click does not duplicate booking.

### D. Human takeover
Inbox flagged customer wants employee -> assign staff -> conversation becomes `human_locked` -> AI generation/delivery blocked from that point -> staff writes -> staff ends lock -> AI can resume; full audit and authorization; status displayed instantly where channel supports.

## Navigation and structure rules
- Public links all active. The first marketing iteration may use truthful descriptions for later features, but not functional-looking pages with dead forms.
- Dashboard must have authorized server routes not solely Vue client conditional rendering.
- **Strict security boundary:** Platform Super Admin is NOT any tenant owner; tenant sidebar never renders `Providers`, `Models`, `Google`, `Custom`, or AI API Key setup, and API responses redact model/provider identifiers.
- Add one clear `Create Agent` CTA globally in dashboard and separate `Docs`/`Support` path.
- Browser Back and direct deep links must preserve selected workspace and route state.
- Consistent breadcrumbs and last-active tab persistence per project if user preference stored.

## Route + user journey additions for WhatsApp phone linking
- `/app/{workspace}/channels/whatsapp` numbers management with `Connect WhatsApp Number` and **three selectable official paths**.
- `/app/{workspace}/channels/whatsapp/new?method=embedded|coexistence|manual` wizard; route parameters never grant permission to mutate another tenant's channel.
- `/app/{workspace}/channels/whatsapp/{connection}/settings` editable label, masked credentials, webhook copy/status, active agent/routing, templates, tests, disable/revoke.
- Manual wizard: company-owned Meta credential fields → save encrypted draft → test metadata/WABA/number binding → copy callback & verify token → signed webhook validation → opt-in send/receive test → assign agent → activate. At each phase show truthful connection status and actionable errors. No fabricated live success in static previews.
- Embedded & Coexistence follow official flow and Meta app review constraints; migration to exclusive Cloud API never automatic or implicit.
- `preview/channels.html` contains visual reference of the three choices; real Laravel UI must be wired to backend.

### WhatsApp operations additions — v1.3
- `/app/{workspace}/inbox/whatsapp`: standalone WhatsApp Web-inspired real-time inbox, list/thread/media/composer/staff assignment/unread/search/notifications; filters for connection/phone.
- `/app/{workspace}/channels/whatsapp/{connection}/automation`: owner-managed `مدة إيقاف AI بعد رد الموظف (دقائق)` default 15, optional connection override, persist/readback.
- Conversation thread status: `الذكاء نشط`, `رد موظف — إيقاف مؤقت 14:59`, `مستلم بشريًا` (indefinite). User workflow staff sends manual reply -> immediately pause just that chat -> staff may reply again to refresh pause -> at timeout automatic resume and one pending inbound processing if needed.
- Required UI views: responsive list/thread/contact detail, message status timeline, emoji/sticker/media composer (Meta capability check), notifications permission, send failure retry, reconnect and durable missed events sync. Mock preview: `preview/inbox-whatsapp.html`; do not use it as production deliverable.
