# OnTrack Voice SaaS — هيكل اللوحات للاعتماد

الحالة: STRUCTURE_PROPOSED / APPROVAL_PENDING — 2026-11-01.
هذا التسليم تخطيط معتمد للفحص فقط: لا نشر لوحة أو Migration قبل موافقة المالك.
قرار التنفيذ: PHP 8.3 مباشر، MySQL/MariaDB، HTML/CSS/JS؛ بدون Laravel Runtime. المصدر الجديد في saas/console/، والهوست في /home/appontrack/public_html/saas/console/، مع إبقاء /saas/mobile/ شغالًا كما هو. ذاكرة ومخططات المشروع داخل GitHub فقط.

## 1) ملكية الحسابات والعزل

OnTrack Platform ← Platform Admin/Staff.
Platform ← Reseller Account A ← Customer A1, Customer A2, ... .
Platform ← Reseller Account B ← Customer B1, ... .
Platform ← Direct Customer C (بدون Reseller).

- كل حساب له موظفون وأدوار وصلاحيات خاصة به. البشر users مستقلون عن accounts؛ العضوية membership تحدد الشركة والدور، ويمكن لعضو واحد الانتقال بين حساباته المصرح بها باختيار صريح.
- Reseller يتحكم في العلامة التجارية وخطط عملائه وأفراد فريقه؛ لا يقرأ تسجيلات ومحادثات وبيانات شركة عميلة افتراضيًا إلا بدعم مؤقت موثق بموافقة ودليل في audit.
- الحساب المباشر يرث هوية OnTrack، والعميل التابع يرث هوية الريسيلر. هوية كل شركة ومفاتيحها وبياناتها معزولة تمامًا.
- Default-deny في جميع APIs؛ لا يكفي إخفاء عناصر الواجهة، ولا يثق السيرفر في reseller_id/tenant_id يأتي من بيانات العميل.

## 2) الواجهات والقوائم

### A — Platform Admin

- Overview: عدد Resellers وCustomers والأجهزة والخطوط الفعّالة والمكالمات والإنفاق والتنبيهات.
- Resellers: إنشاء، تعديل، تعليق، دعوة، سقف العملاء، خطة الريسيلر، حصته، تسعيره ومطالباته المالية.
- Customers: جميع العملاء، ربطهم بريسيلر أو direct، تفعيل/تعليق، خططهم، حالة الخطوط والأجهزة، سياسات الدعم.
- Plans & Quotas: نوعان مستقلان Reseller Plans وUser Plans، الميزات والتكاليف والحدود.
- Voice Catalog: أصوات أساسية وPremium، اختبار التوفر، توزيع على Reseller وUser، إخفاء/تعطيل.
- AI/Agents: مزودات ونماذج AI واختيار لكل وظيفة وحساب التكلفة وسياسات التشغيل العامة (إعدادات مزود AI للأدمن فقط).
- Payments/Kashier: المعاملات، التسويات، فواتير الأدوار المختلفة، تجديدات/تعليق/استرجاع آمن.
- Platform settings: اسم الموقع، لوجو أساسي ولايت/دارك، favicon، البريد، الأمان، السياسات والتحديثات.
- Team & RBAC: موظفو OnTrack؛ super_admin، platform_admin، support، finance، auditor، أذونات متعددة.
- Operations: صحة العمال والأجهزة وقنوات الاتصال، queues/audit/security/errors، من غير اختلاق تشغيل SIM.

### B — Reseller

- Overview: العملاء والأرباح والحصص وطلبات التسجيل ومعدلات الاستخدام والتنبيهات.
- My Customers: شركات العميل، إضافة/دعوة/إيقاف، إسناد User Plan من الخطط التي يسمح بها الأدمن.
- My Plans: أسعار ونسخ عروض المستخدم، توزيع المكالمات من الـReseller Pool، استخدام كل شركة.
- White Label: الاسم التجاري، لوجو للأبيض والأسود، favicon، support contact، نطاق خاص بعد إثبات الملكية، معاينة الهوية.
- Voices: اختيار الأصوات المتاحة لعملائه من مجموعة الأصوات المسموح بها للريسيلر.
- Staff & Roles: Owner، Manager، Sales/Provisioning، Support، Finance بأذونات على موارد الريسيلر فقط.
- Integrations: API keys، Signed Webhooks، روابط إنشاء العملاء، ربط الموقع/CRM، Logs/Replay.
- Billing: مدفوعات الريسيلر واشتراكه وفواتير عملائه، دون كشف الفوترة الداخلية للمنصة.

### C — User / Customer Workspace

- Overview: حالة SIM/Android، الإيجنت الحالي، مكالمات اليوم والحملات والرصيد.
- Phone Center: خطوط وأجهزة، حالة الربط، استقبال/إجراء اتصال، تشغيل/إيقاف وفق إثبات حالة الجهاز.
- Agent Builder: شخصية، صوت، تعليمات، شجرة الأسئلة/السيناريوهات، قواعد التحويل، المعرفة.
- Campaigns: CSV/XLSX/TXT/Phone Contacts، مع معاينة، جدولة، عطلات وساعات عمل، موافقات/DNC، النتائج.
- Call History: رقم واسم إذا طابق سجل Android المسموح، التاريخ، المدة بالدقائق والثواني، نتيجة المكالمة، تقرير، تسجيل إذا وُجد.
- CRM/Contacts/Follow-Ups، Knowledge Base، Reports & Analytics، Support/Inbox.
- Employees & RBAC: Owner، Manager، Operator/Agent، Viewer؛ صلاحيات دقيقة لكل خط/حملة/إيجنت.
- Integrations وAPI keys حسب الباقة، Billing/Usage، إعدادات المظهر، هوية الحساب الموروثة.

### UI Design

هوية OnTrack للمنصة والمباشر بالأحمر/الأسود/الأبيض. Light/Dark بزر شمس/قمر فقط، RTL أصيل، واجهة Desktop Sidebar + topbar، موبايل Bottom Nav، بطاقات KPIs وأزرار واضحة ومساحة فارغة منظمة. كل Brand Reseller يحلّ محل اسم OnTrack وشعاره في واجهة الويب والتطبيق ومحتوى الرسائل وتقارير العميل. لا نُظهر زر ميزة لا يسمح بها الدور والخطة، ولا نعتمد على ذلك لتأمين API.

## 3) RBAC والتوزيع

مصدر الصلاحية الفعلي: Membership roles and resource scope AND Plan Entitlements AND Device Capability.
- Platform: إدارة كل الريسيلرز والشركات، الباقات والأصوات والسياسات، والدعم المدقق.
- Reseller: إدارة حسابه وموظفيه وعملائه وباقات المستخدمين ضمن حصته فقط؛ لا وصول لشركة Reseller آخر.
- Customer Owner: إدارة موظفي شركته وخطوطها وإيجنتاتها وأذونات الحملات.
- Customer Staff: قراءة/تعديل موارد مفوضة؛ تشغيل حملة وإدارة تسجيلات المكالمات يحتاج إذن صريح.
- حماية آخر Owner من الإزالة، منع self-escalation، تسجيل التدقيق والقرار والسبب والجهة التي منحته.
- صلاحيات مثل platform.resellers.manage، reseller.customers.create، reseller.brand.manage، staff.manage، calls.execute، calls.read، recordings.read، voices.assign، billing.manage، integrations.manage، devices.manage.

## 4) باقات الريسيلر والمستخدم

Reseller Plan: أقصى عدد شركات، رصيد شهري مكالمات مشترك، موظفو الريسيلر، API، White Label، الأصوات المميزة، السعة.
User Plan: مكالمات متصلة شهريًا، حد محاولات الاتصال لمنع الإزعاج، SIM lines/devices، Agents، Staff seats، Campaigns، Contacts، Recordings/Retention، Reports/Integrations، Voice availability.

قواعد التنفيذ:
- مكالمة ANSWERED المؤكدة تستهلك وحدة connected_calls في خطة العميل وخطة الريسيلر الأب، بمعاملة واحدة وidempotency_key. محاولة دون رد لا تستهلك وحدة connected لكن تخضع لحد الاتصالات/الأوقات وDNC.
- أي ريسيلر لا يستطيع توزيع أكثر من الحصة المسموحة، ولا منح صوت أو ميزة أعلى من خطته.
- الأجهزة والخطوط تفرض التزامن الواقعي؛ لا يُعتبر خط SIM واحد عدة مكالمات متزامنة.
- Ledger + reservations + monthly reset مضبوط بالمنطقة الزمنية وanti-double-count. عدم دفع أو انتهاء اشتراك يوقف العمليات الجديدة حسب سياسة محددة دون حذف التاريخ.
- Kashier Gateway لاحقًا بمدفوعات test/live مفصولة: صحة HMAC ومطابقة المبلغ والعملة والمرجع وidempotent webhook قبل منح الاشتراك. الأسعار من الإدارة وليست constants.

مثال DEMO فقط (قابل للتعديل من لوحة الأدمن، ليس أسعارًا أو باقات بيع مقررة):
| Plan | Customers | Connected calls / month | Employees | SIM lines | Agents |
|---|---:|---:|---:|---:|---:|
| Reseller Start | 10 | 5,000 pool | 5 reseller staff | يتبع الأجهزة | يتبع العملاء |
| Reseller Scale | 50 | 25,000 pool | 20 reseller staff | يتبع الأجهزة | يتبع العملاء |
| User Starter | — | 300 | 3 | 1 | 1 |
| User Business | — | 2,000 | 15 | 3 | 5 |

Voice catalog: ثلاثة أصوات Google الأساسية المتاحة حاليًا، وأصوات AYO Calm/Excited/Sleepy PREMIUM في حالة ready=false حتى نجاح الاختبار الفعلي لمحرك التوليد وحقوق الاستخدام؛ لا تُباع أو تُفعَّل باعتبارها تعمل.

## 5) Reseller White Label داخل Android

- تحديد البراند بعد توثيق الجهاز وربطه بـtenant_id/reseller_account_id؛ لا تحميل شعار ريسيلر اعتمادًا على ID في الرابط فقط.
- اسم المنتج والشعار داخل signed WebView/الإعدادات والرسائل وتقارير العميل يتبعان BrandResolver. الجهاز يسحب branding config عبر Authenticated Native Bridge، بدون تمرير API key إلى HTML غير موثوق.
- الاسم/الشعار الخاصان بـOnTrack لا يظهران لعميل Reseller داخل الواجهة. Direct User يرى OnTrack. ترخيص Admin UI يبقى OnTrack.
- **قيد Android مهم:** اسم التطبيق وأيقونته على شاشة Launcher لا يمكن تغييرهما لكل ريسيلر بصفحة HTML أو OTA فقط؛ إن طلب توزيع branded APK لكل ريسيلر يكون مسار Native مستقل. كذلك Overlay/Notification النصي يحتاج Native يدعم dynamic brand، ولا نعد بأنه يتغير عبر UI-only OTA.

## 6) Login وDemo Data

- Login form مشتركة (Email + Password) تعرض البراند الصحيح من verified domain أو signed invite؛ Login/Logout/Reset Password/Session management و2FA للأدوار الحساسة.
- After login role-aware redirect إلى /admin أو /reseller أو /workspace. إذا للعضو أكثر من Account تظهر شاشة اختيار منظمة.
- جلسة PHP Server-side مع Secure/HttpOnly/SameSite، تدوير session ID، CSRF، login throttling، Password hashing حديث، audit وPassword Reset مع TTL.
- Seed DEMO بيانات اصطناعية موسومة لا تُشغّل SIM ولا تدفع فعليًا: Platform Admin وأعضاء دعم وFinance؛ 2 Resellers (دلتا فويس/نيل فويس) بهويات مختلفة؛ 3 شركات عميل تابع، وشركة Direct؛ لكل واحدة Owner/Manager/Staff/Viewer، Agents، Campaign drafts، Contacts اختبار غير قابلة للاتصال، تقارير DEMO وQuotas وفواتير sandbox.
- حسابات demo لها Email addresses من مجال .test؛ كلمات سر عشوائية تُنشأ في مساحة خاصة على السيرفر ولا تُحفظ plaintext في GitHub؛ لوجن ديمو مقيّد وليس دخول إنتاج فعلي.
- Test matrix: admin sees all; reseller sees only own 2 customers; user sees own resources; employee sees only scopes; denied endpoint returns 403; no fake records shown as live metrics.

## 7) تكامل موقع الريسيلر وإنشاء المستخدمين تلقائيًا

Supported adapter contract: Generic REST API + Webhook + optional signup/widget embed، مع أمثلة WordPress/Elementor وLaravel/PHP وWHMCS وCRM/CS-Cart حسب الأحداث المتاحة. لا نفترض وجود Connector عامل قبل اختبار الطرف الآخر.
1. موقع الريسيلر يرسل signed webhook عند Registration أو Booking أو Lead مع external_customer_id والتاريخ وconsent.
2. API gateway يتحقق من reseller API key scope + HMAC + timestamp + nonce + idempotency + HTTPS; يحدد reseller_id من المفتاح وليس من payload.
3. Registration يخلق pending customer/tenant ودعوة Branded؛ يربط Reseller + Allowed Plan. Booking يولد CRM Lead/Appointment مربوط بشركته بعد التحقق؛ المكرر لا ينشئ حسابًا جديدًا.
4. إذا اشتراكه مصرح ودفع موثق يمكن التفعيل؛ إلا فلا نشر خط اتصال تلقائيًا. نشاط التسويق يحتاج موافقة ومراجعة DNC وساعات الاتصال، ولا يتم الاتصال لمجرد وصول booking event.
5. Outgoing webhook موقع الريسيلر: tenant.created, subscription.activated, lead.created, appointment.created, campaign.result، توقيع وحفظ delivery+retry وDLQ، وسجل للأخطاء وإعادة التشغيل بعد التأكد من idempotency.
6. API keys revoke/rotate، حد معدل، logs محدودة لا تكشف PII، عدم الوصول بين resellers، وخيارات ربط صفحة التسجيل والبوابة.

## 8) قاعدة البيانات المنطقية

Accounts/security: users, accounts (reseller/customer), memberships, roles, permissions, role_permissions, memberships_roles, invitations, sessions, audit_events, support_access_grants.
Branding: brand_profiles, brand_assets, verified_domains, platform_settings.
Plans/billing: plans (kind reseller/user), plan_entitlements, subscriptions, reseller_allocations, usage_ledger, usage_reservations, orders, payment_events, invoices.
Voice: voices, voice_grants, devices, phone_lines, device_pairing, projects, agents, agent_versions, agent_flows, knowledge_sources, contacts, dnc_entries, campaigns, campaign_targets, call_sessions, call_events, call_recordings, call_summaries, call_dispositions, crm_leads, appointments, staff_assignments.
Integration: api_credentials, integration_connections, inbound_webhook_events, external_id_map, outbound_webhook_subscriptions, webhook_deliveries, provisioning_requests.

كل جدول متعدد الشركات يحفظ account_id؛ foreign keys/indexes وunique idempotency keys، تدقيق وصلاحيات backend، تشفير secrets، وعدم حذف التاريخ المالي بمجرد حذف حساب.

## 9) مسارات المصدر والتنفيذ

Source GitHub (new, no Laravel runtime):
- saas/console/index.php : front controller / login and role routes.
- saas/console/assets/{css,js,img}/ : RTL theme and branding.
- saas/console/api/v1/index.php : scoped APIs.
- saas/console/hooks/index.php : signed inbound webhooks.
- saas/console/_core/Auth/ : sessions, login, password, invite, MFA.
- saas/console/_core/Tenancy/ : membership, BrandResolver, isolation.
- saas/console/_core/Security/ : CSRF, RBAC, audit, signatures.
- saas/console/_core/Controllers/{Admin,Reseller,Workspace}/.
- saas/console/_core/Views/{Auth,Admin,Reseller,Workspace}/.
- saas/console/_core/{Billing,Voice,Integration,Database}/.
- tools/tests & docs/plan & docs/memory remain on GitHub only.

Hosting:
- /home/appontrack/public_html/saas/console/ : public entry / assets / protected app code (block _core via .htaccess and verify actual HTTP 403).
- /home/appontrack/.ontrack-saas/ : private config, DB credentials, secrets, uploads, logs, backups.
- /home/appontrack/public_html/saas/mobile/ : existing mobile pilot untouched.
- Public URL: https://app.ontrackegy.com/saas/console/login (initial); role paths /admin, /reseller, /workspace.
- Existing saas/app/ Laravel prototype remains reference code only; not bootstrapped/deployed as this console. Existing single-device /mobile key is NOT a multi-tenant credential; migration/device tokens need explicit account binding before SaaS access.
- Existing GitHub host-link Cron is for controlled deployment and health, not a telephony worker; keep deployments safe and independent.

## 10) تنفيذ ورفع بعد الموافقة

Phase 1 Auth + Database + roles + secure Login + Demo Seeder + role isolation tests.
Phase 2 Admin/Reseller/User responsive dashboards + Employees + White Label (light/dark).
Phase 3 Plan entitlements + quota engine + Voice Catalog + Kashier sandbox.
Phase 4 Integrate existing Android SIM / Agents / Campaigns / Calls / Reports / real-device state, without breaking current phone pilot.
Phase 5 Reseller API + Webhook onboarding, CRM leads/bookings + logs.
Phase 6 QA: cross-tenant 403, DNC/consent, quota race/idempotency, impersonation audit, demo isolation, responsive RTL screenshots, real HTTP checks.
Then deploy via existing GitHub host-link to /saas/console, verify installed files, DB state, login + demo accounts & features; update CHECKPOINT. No root-site changes, no secret files in public_html, no code/report/log export to public areas.

## 11) شروط الاعتماد النهائي

- 3 login perspectives؛ صفحات وصلاحيات وديمو مختلف.
- Admin manages reseller/user plans, voices, all accounts and branding.
- Reseller owns branding and manages only its customers and staff, with pooled quota and per-customer caps.
- User manages SIM/agents/campaigns/contacts/calls/knowledge/staff within its entitlements.
- Employees and role permissions tested server-side (not menu hiding).
- Direct user sees OnTrack; reseller user sees ONLY reseller brand inside app/console; dynamic native overlay requires actual supporting Android release.
- Signup/booking integrations create customers/leads exactly once under correct reseller.
- Quotas, payments and Premium readiness never fabricate billing or running audio.
- Synthetic demo isolated from real lines, tokens, customers, call history.
- PHP deployment under /saas without breaking existing Android/OTA; every production gate has CI+host+device evidence as appropriate.
