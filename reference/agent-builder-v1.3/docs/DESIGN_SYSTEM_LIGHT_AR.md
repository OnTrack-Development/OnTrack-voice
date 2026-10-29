# OnTrack Light Design System — V1.0

## 0. التصميم هنا مُلزم؛ مش مجرد إلهام
النتيجة المطلوبة **منتج SaaS عربي premium**، إحساسه هندسي هادئ وفاخر وعملي، بديل حديث لـTAB **بدون تقليد بصري**. الشكل الأول Light فقط، الأحمر يستخدم كتركيز واضح، مش خلفية لكل سكشن. اعتمد `machine/design-tokens.json` مصدر الألوان؛ `design/tokens.css` مرجع مبدئي، والـTailwind theme يستورد نفس القيم. عدّل بقية الواجهة من نفس المصدر.

**ملاحظة هوية:** لا يوجد Brand Book أصلي أو Logo vector مرسل ضمن الحزمة؛ قيم الأحمر هنا proposed reference مستقلة مستلهمة من ألوان OnTrack الأحمر والأسود والأبيض. لو المشروع الحالي عنده logo/colors الرسمية غلّب الألوان الرسمية ووثق التغيير.

## 1. UI Art Direction
- Visual mood: **Precision SaaS / clean editorial / approachable enterprise**. مساحات بيضاء محسوبة، hierarchies قوية، خطوط هندسية خفيفة، بطاقات crisp، فصل بصري بالفواصل الهادئة بدل ظلال ثقيلة.
- Background: أوف وايت فاتح `#F7F8FA`, سطح `#FFFFFF`, تمييز خفيف `#F1F3F5`. Intro hero white/eggshell مع grid dots خافتة وred radial bloom شبه غير ملحوظ عند البعد.
- Text: titles `#12151B`, body `#374151`, muted `#687385`, border `#E5E7EB`.
- Brand: `#D5222C` Primary CTA / active tab / small status highlight; hover `#B81B26`; soft `#FFF0F1`. لا red input field backgrounds.
- Success `#118565`, Warning `#B57910`, Danger `#C32635`, Info `#2563B8` لا تعتمد فقط على اللون؛ أي badge لازم نص/أيقونة.
- Typography: Arabic **IBM Plex Sans Arabic** (حملها من مصدر مرخص أو CDN/حزم موثوقة)، fallbacks `Tajawal`, `Noto Sans Arabic`, `Arial`, sans-serif. English later `Inter` or system-ui. لا تشارك ملفات خطوط مع مستخدم ولا تعتمد على ملفات خط ليست أصلية.
- Weight: 400 body, 500 subhead, 600 card, 700 heading, 750 hero if supported. Arabic body min 14px tables and 15–16px reading, line-height 1.7 default. Hero 52–68px desktop/32–38 mobile depending line wrap.
- Icons: Lucide/own outline stroke 1.8–2px، موحدة sizes 18/20/24. لا emoji كأيقونات UI الأساسية. استخدم صور/illustrations اصلية أو compositor SVG نظيف لا plagiarized third-party.
- Contrast WCAG AA / large interactive hit targets 40–44px / visible focus ring.

## 2. Design primitives
**Grid**: desktop max width `1280px` landing (stretches section backgrounds), app `fluid` content padding 24–32px, tablet 20, mobile 16. 12-column editorial grid. Section vertical gap 96–112px desktop, 64–72 tablet, 48–56 mobile.

**Spacing**: 4px base; 4/8/12/16/20/24/32/40/48/64/80/96 scale. No arbitrary 27px chaos. 

**Radius**: Button 11px; input 11px; card 18px; large hero panel 24px; tags/pills 999px. Shadow faint `0 8px 30px -18px rgba(17,24,39,.18)`; border crucial. Sticky header hairline border + backdrop blur light.

**Control heights**: small 34; regular 42; large 48; hero CTA 52. Cards titles 17–20; section headlines 36–48; eyebrow labels 12 with tracking only for English. Data cells align labels RTL, numeric figures tabular LTR.

**Composition**: White container, gray canvas, one red action per emphasis group. Headings ~2–4 lines maximum per section. Use contrasting panel for product screenshots and narrative storyline from customer -> AI -> action -> change state -> human. Resist endless generic equal cards.

## 3. Landing visual structure — exactly targeted
### Global header
Right: official wordmark or logo when available + `OnTrack AI` lockup; then (in RTL) المميزات | الحلول | التكاملات | الأسعار | المطورون; left-side login and standout red `ابدأ مجانًا` / `ابدأ الآن` pending tier policy. Navbar height 72–78; sticky on scroll; responsive menu with accessible close/keyboard focus.

### Homepage Hero
- Right Arabic editorial headline: **«حوّل كل محادثة إلى تجربة أذكى.»**
- Highlight one **underlined red phrase** (not multicolor gradient typography).
- Subtext: AI الموظف لكل شركة؛ يفهم المعلومات، يتعامل مع الإجراءات المصرح بها، ويتكامل مع مشروعك.
- CTAs: primary «أنشئ وكيلك» secondary «شاهد التجربة».
- On left, **functional / data-driven demonstration component**, *labeled as demo unless really connected*, showing customer bubble, AI response, buttons, verified action timeline, a small OnTrack-managed service / plan status badge (never raw provider name), state changes. This is the visual centerpiece, not floating 3D fake dashboard.
- Tiny trust row labeled as product capabilities, not fake customer logos/counts: «مخصص للشركات»، «تكامل API»، «أزرار ذكية».
- Background subtle technical dot/grid, soft red highlight and two line paths ending in status nodes; avoid glowing red blobs or excessive parallax.

### Following homepage sections (order)
1. Brand capability strip (4 quiet horizontal capability items); no fake partner logos.
2. «نظام واحد. كل محادثاتك.» live component/cards for AI Agents, Channels, Smart Actions, Knowledge.
3. Guided explainer **workflow 01..04** (الشركة → الوكيل → المعرفة والتكامل → نشر وتجربة) with illustrated connectors.
4. **Interactive Smart Buttons** example showcasing state changes and action eligibility.
5. Integration Hub with Laravel/WP/WHMCS/REST (official marks only if licensed, else text chips).
6. Knowledge Hub + managed AI service value proposition (customer sees capabilities, never Gemini/Custom URLs or keys); admin manages upstream separately.
7. Omnichannel + unified inbox (only list supported/planned accurately).
8. Industries: 7+ sector examples + «وأي قطاع آخر» to avoid launch segmentation.
9. Security/permissions: state verification, access roles, audit, secret-safe keys.
10. Pricing teaser with configurable plans and honest CTA (no made-up numbers).
11. FAQ concrete + final CTA + large structured footer.

## 4. SaaS Dashboard visual structure
- **Sidebar on right** in Arabic, width 264 expanded / 72 collapsed, pure white with gentle border left. Top project/workspace switcher, then grouped menu:
  - الرئيسية: نظرة عامة، المشاريع، الوكلاء.
  - التشغيل: المحادثات، CRM، أزرار ذكية، العمليات.
  - المعرفة والاتصال: المعرفة، إمكانيات الباقة، التكاملات، القنوات. **AI Providers فقط في OnTrack Super Admin وليس في العميل.**
  - الإدارة: التحليلات، استهلاك، الفريق والصلاحيات، الإعدادات.
- Topbar height 72: Breadcrumb, universal search, project switcher (where appropriate), notice icon, avatar/dropdown. Light `#FFF` with border bottom.
- Main body right-aligned in RTL, max readable width when forms, fluid tables where data-heavy. No thousands of bright status widgets.
- Empty state has illustration/line icon, clear what to do now CTA, never fake 'live' data.
- Agent Studio main tabbed segments with right editor and left Playground Inspector; center panel when suitable. Playground text input at bottom, trace/steps revealed on demand.
- Provider detail uses 4-step progress UI: Config → Test Connection → Models → Activate. Connection success badge only from authenticated API response; Test Model separately.
- Buttons rules editor uses list of condition rows, states and preview card; on `state changed` the preview buttons update; never agent-trusted JSON.
- Operations room uses event timeline from real events; no animated agent avatars that simulate work when idle.

## 5. Reusable components to build
`AppShell`, `MarketingShell`, `SectionHeading`, `Button`, `IconButton`, `Input`, `Select`, `Combobox`, `Switch`, `Badge`, `Tooltip`, `Tabs`, `Stepper`, `Modal`, `Drawer`, `Toast`, `Skeleton`, `Card`, `DataTable`, `Stat`, `EmptyState`, `ErrorState`, `CodeField`, `JsonPreview`, `Timeline`, `ChatBubble`, `QuickActionPill`, `ProviderHealth`, `PermissionGate`, `ProjectSwitcher`, `ConfirmAction`, `AuditInspector`.
Every component should have disabled/loading/hover/focus and RTL checks; inputs are actual accessible labels; errors linked to fields. Use tokens only, do not hard-code dozens of color instances.

## 6. Motion & responsive
- Micro-motion 120–220ms opacity/translate 3–6px; page reveal subtle once and after user intent, no mandatory cinematic preloader on every route.
- Charts animate once, if any; no infinite spinners on disconnected services. Honor `prefers-reduced-motion` disables nonessential motion.
- Mobile: cards stack, hero demo below text, drawer replaces permanent sidebar; table scroll isolated or adaptive row cards. Important actions remain visible and keyboard/touch usable.
- RTL nuances: logical CSS properties (`margin-inline-start`, `border-inline-end`), reverse icons only where directional, ensure paths and logos LTR when needed. For code/model/API keys always `dir="ltr"` and `unicode-bidi: plaintext`.
- Test at 360, 390, 768, 1024, 1280, 1440; check horizontal overflow (must be none for whole page except explicitly scrollable tables).

## 7. UI quality fail conditions
- Generic violet gradient landing template, whitewash all cards, fake animated integrations, placeholder Lorem Ipsum, inert nav, 404 section CTAs, English UI fragments while Arabic mode, wrong RTL table actions, inaccessible small icons, wizard that loses state, fake price and live status, unreadably small Arabic table text.

## 8. Visual prototypes
`preview/landing.html` and `preview/dashboard.html` are illustrative. Build real Vue components in application and use prototype only to validate design rhythm, colors, typography, spacing and content structure. Do not simply embed HTML in page or represent its synthetic data as live SaaS.
