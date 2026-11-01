# ملحق هيكل SaaS: دخول الدعم، دومينات الريسيلرز، المواقع/الإيجنتات، تكامل Laravel وWHMCS

**التاريخ:** 2026-11-01 · **الحالة:** STRUCTURE_PROPOSED / APPROVAL_PENDING  
**المبدأ:** هذا مخطط للموافقة فقط؛ لا تُنشأ ملفات تشغيل ولا migrations ولا يُنشر شيء على الهوست قبل موافقة المالك. التنفيذ النهائي PHP + MySQL مباشر في `saas/console/`، دون Laravel runtime، لكنه يوفّر **تكاملًا كاملًا مع أنظمة Laravel الخارجية وWHMCS**. كل الملفات والتحديثات على سيرفر OnTrack، ولا يحتاج الريسيلر رفع لوحة إليه.

## 1. الدخول لحساب العملاء (Support Access)

- **Admin مخوّل** يمكنه دخول أي Workspace على المنصة عبر زر «الدخول كعميل» مع **جلسة دعم مؤقتة ومقيدة**، وليس كلمة مرور العميل أو نسخ حسابه.
- **Reseller Owner/authorized staff** يمكنه الدخول لحساب أي **عميل تابع لهذا الريسيلر فقط**. الحسابات المباشرة أو التابعة لريسيلر آخر محظورة `403`.
- عند الدخول يطلب سببًا/رقم تذكرة وموافقة العميل عند لزومها؛ MFA للأدوار الحساسة؛ مدة افتراضية 15 دقيقة؛ شريط واضح «أنت داخل حساب ... بوضع الدعم» وزر دائم للعودة.
- افتراضيًا قراءة فقط؛ أي كتابة حساسة تحتاج Grant إضافي محدد المدى والزمن؛ عدم إظهار كلمات السر أو مفاتيح API الخام أو صلاحية الدفع. كل عملية Audit مع `real_actor_user_id`, `effective_account_id`, `scope`, `reason`, `grant_id`, `expiry`.
- تفعيل وإلغاء الدعم من لوحة العميل؛ انتهاء الجلسة يلغي السياق. لا تجاوز لحدود الحصص أو Consent أو DNC لمجرد الدخول بوضع الدعم.

## 2. الريسيلر يدخل من دومينه، والملفات واحدة على OnTrack

```text
voice.reseller-a.com ──┐
app.reseller-b.com   ──┼── DNS verified + TLS SNI / reverse proxy
app.ontrackegy.com   ──┘                    │
                                           ▼
                     Central PHP app: /saas/console/
                                           │
                       verified Host -> reseller brand
                       authenticated account -> RBAC
                       shared code + isolated MySQL data
```

- الريسيلر يضيف subdomain أو domain من `Reseller → Domains`، يرى TXT للتحقق من الملكية وCNAME/ALIAS لتوجيه النطاق. Domain states: pending_dns → verified → tls_ready → active.
- ربط DNS **وحده لا يكفي**؛ يجب إعداد domain alias/vhost وTLS certificate على الاستضافة حسب ما تسمح به cPanel/LiteSpeed. لا إعلان تفعيل قبل نجاح HTTPS وHost routing.
- `verified_domains` + trusted Host whitelist + HTTPS enforce + حماية Host header injection. لا قبول X-Forwarded-Host من مصادر غير موثوقة، وDomain غير مرتبط يُرفض.
- اسم ولوجو الريسيلر و favicon ورابط الدعم والرسائل وصفحة الدخول يظهرون تلقائيًا للعميل وفق الدومين الموثق والعلاقة الموثقة `reseller_account_id`، لا يمكن التلاعب بالبراند ببارامتر URL.
- ملفات التطبيق والإصدارات والـDB والـCron على سيرفر OnTrack فقط. جلسات host-only cookies لكل دومين، لا مشاركة Cookie بين ريسيلرز؛ SSO اختياري بإثبات هوية مؤقت signed one-time exchange.
- في تطبيق Android، محتوى الـWebView والإعدادات والإشعارات التي تدعم brand config تعرض براند الريسيلر؛ اسم التطبيق المثبت وأيقونة Launcher والـnative overlay تحتاج تحديث Native يدعم Dynamic Branding أو حزمة توزيع مستقلة. CSS OTA لا يستطيع تغيير أيقونة Launcher نفسها.

## 3. APK من لوحة Admin وReseller وUser

- `Admin → Android Releases`: الملف المنشور المعتمد، `versionCode`, SHA256 والتوقيع، ملاحظات النسخة، تنزيل/مشاركة رابط موقّع إن لزم.
- `Reseller → Android App`: صفحة تنزيل على دومينه باسمه ولوجوه مع invite/device pairing scoped؛ **نفس APK المركزي** والتحديثات من المصدر، لا رفع نسخة عنده.
- `User → Devices`: تنزيل وربط الجهاز والخط ومراقبة الإصدارات وحالة OTA؛ لا كشف مفتاح الربط القديم في روابط التنزيل.
- UI HTML/CSS/JS تتحدث بـsigned OTA من غير APK؛ تحديث كود Android native يحتاج موافقة تثبيت النظام ولا يمكن عمل silent install من الويب.

## 4. Admin وReseller وUser لديهم Sites + AI Agent Builder

- **حتى أدمن المنصة** يملك `Internal Customer Workspace` مستقلًا، يضيف فيه موقعًا وإيجنتات وصوتًا ومعرفة ويجرب المكالمات ضمن Entitlements خاصة، بلا إساءة استخدام Support Access.
- خطط المستخدم تشمل حدودًا مستقلة: `max_sites`, `max_agents`, `max_agents_per_site`, `max_lines`, `connected_calls_monthly`, `staff_seats`, `campaigns`, `api_keys`.
- المستخدم يضيف `Site` (مثل صيدلية) ثم `Agent` مرتبطًا **حصريًا** بـsite_id، ويضيف موقع ملابس/Agent/Knowledge مختلفة. Web Widget وAPI key وFlow لكل موقع لها سياق منفصل. لا يصح أن يرد Agent الصيدلية بمعرفة الملابس.
- اختيار الموقع داخل لوحة المستخدم أو التطبيق `Site Switcher`؛ لكل موقع Analytics وContacts وAgents وFlows وLogs وIntegrations، مع shared company staff permissions بحسب الموقع.
- **Agent Builder + Flow Canvas كامل**: الاسم، الشخصية، التعليمات، اللهجة/الصوت، greeting، الأسئلة والأهداف، Nodes (start/say/listen/ask/condition/knowledge_lookup/collect_field/crm_lookup/crm_write/schedule/handoff/end)، branching، retries، fallback، versioned drafts، validate/test/publish/rollback.
- لكل Agent إعدادات قنوات Voice SIM وWeb Chat وAPI وWidget منفصلة؛ لا مزج مع المعرفة/هوية موقع آخر. خط SIM واحد يدعم جلسة هاتف واحدة فعليًا ولا نحول وجود إيجنتين إلى مكالمتين متزامنتين.
- `Admin` يدير Catalog/Template عام، وReseller ينشئ قوالب لعملائه **ضمن الصلاحيات**، والعميل يبني محتواه. تفعيل الصوت Premium محكوم بـready flag + Voice Grant + Plan.

### مثال باقة Business مع موقعين

```text
Customer Account: شركة التجارة
  ├─ Site A: pharmacy.example
  │    ├─ Agent A + Flow A
  │    ├─ Knowledge A + Widget A
  │    └─ API Key: pharmacy-production (site_A only)
  └─ Site B: fashion.example
       ├─ Agent B + Flow B
       ├─ Knowledge B + Widget B
       └─ API Key: fashion-production (site_B only)
```

## 5. API Keys مسماة، إنشاء وإلغاء وتدوير

- Create API Key يتطلب `name` واضحًا، `description` اختياريًا، `account` و`site_id`، `scopes`, `expires_at`, `rate_limit`, IP allowlist (اختياري).
- المفتاح يُعرض **مرة واحدة فقط**؛ يخزَّن Hash وليس Plaintext، وتُعرض لاحقًا `key_id/prefix/name/last_used/scopes/status`.
- إجراءات Create / Rename / Rotate / Revoke الفوري / Scope reduction / View audit. Revoke يلغي الوصول للجلسات الجديدة والأحداث التابعة له، ولا يمس محفوظات المدفوعات.
- Reseller API key يمكن من `customers:create` داخل ريسيلره فقط؛ Site API key `agents:invoke`, `leads:write`, `knowledge:read` مقيّدة بموقعها. لا يمنح أي مفتاح `site_id` أو Tenant آخر عبر Request.
- HMAC للمراسلات Webhooks منفصل عن Bearer token. نمنع كشف Server Keys داخل JS Public Embed.

## 6. Laravel وWHMCS وKashier: التسجيل، الحجز، الدفع والتفعيل التلقائي

**هذا التكامل مطلوب في الاتجاهين (Inbound & Outbound)** ويعمل مع Reseller/Direct Account من لوحة Integrations.

### مصادر الأحداث

| النظام الخارجي | حدث بداية التهيئة | حدث إثبات الدفع | الدفع اليدوي | وسيلة إرسال العميل |
|---|---|---|---|---|
| Laravel website/booking app | `user.registered`, `plan.ordered`, `booking.created` | server event `payment.captured` أو Kashier webhook مثبت | admin/finance `payment.approved` من واجهة Laravel | Laravel Notifications Mail/SMS/WhatsApp الخاصة بموقع الريسيلر |
| WHMCS | `ClientAdd` + `AfterShoppingCartCheckout`/order linked to product | `InvoicePaid` + حالة Invoice & Order/Transaction verified عبر WHMCS API | order/invoice manual review و`payment.approved` server-side | WHMCS email templates و`SendEmail` / local hook |
| Kashier direct | `checkout.created` (pending) | **valid signed server-to-server webhook** + order ref/amount/currency/status | لا يُعتد بإتمام Redirect وحده | Platform Outbox أو جهة التكامل الأصلية |

*أسماء hooks/APIs الخارجية تُثبَّت وفق نسخ WHMCS وLaravel المُستخدمة قبل كتابة adapter. جداول الأحداث أعلاه Contracts منطقية؛ لا نفترض وجود Hook باسم موحد في كل مشروع Laravel.*

### Workflow محدد: Order → Payment → Provisioning → Notification

```text
User registers/orders on reseller's Laravel/WHMCS
         │
         ▼
Signed Integration Adapter -> normalized order + external_order_id
         │
         ▼
PENDING_PAYMENT (customer/account reserved but NO active service)
         │
      ┌──┴─────────────────────────────┐
      │                                │
Online: Kashier / card           Manual bank/cash/transfer
      │                                │
Server verifies signed payment    Reviewer approves explicitly
amount, currency, order, paid     + receipt/audit/policy
      │                                │
      └──────────┬─────────────────────┘
                 ▼
       PAYMENT_CONFIRMED
                 ▼
Provisioning transaction + idempotency lock
  -> find/create Customer by reseller+external_customer_id
  -> create/reuse Site/Project requested
  -> map external product to approved User Plan
  -> allocate quota from reseller pool
  -> active Subscription + branded invite
  -> create initial Agent DRAFT (not unconsented outgoing calls)
                 ▼
      PROVISIONED (exactly once)
                 ▼
Outbox: account.created / plan.activated / invitation.ready
      -> Laravel/WHMCS adapter -> external notification
      -> branded email/SMS/WhatsApp where connected and consented
      -> delivery receipt, bounded retry, dead-letter queue
```

#### Online payment details
- Redirect إلى `success_url` **واجهة عرض فقط**؛ لا ينشئ الخدمة ولا يفعّل خطة بناءً على Query String أو Browser success page. عند وصول المستخدم للـSuccess Page، تعرض الحالة `جاري تأكيد الدفع` وتعيد الاستعلام عن **الحالة الموثقة على السيرفر**؛ الانتقال إلى `active` فقط بعد webhook موقّع أو مراجعة Transaction موثوقة API.
- Kashier callback: signature/merchant/order amount and currency/ref and merchant state، replay protection، idempotency DB transaction، merchant order tied to Reseller + plan mapping. InvoicePaid من WHMCS يراجع `invoice.status` وربط المنتج والحساب/الخطة وتفاصيل Transaction قبل Provision، ولا يثق بWebhook مزور.
- الإشعار يتم بعد التفعيل الناجح فقط، وليس بعد فتح صفحة Success.

#### Manual payment details
- `PENDING_MANUAL_REVIEW`: يستلم النظام إثباتًا/مرجعًا مع صلاحية محددة للمراجع؛ **لا يتم إنشاء Subscription فعّالة قبل الضغط على Approve من جهة مفوضة** والتحقق من المرجع والحساب والقيمة والسياسة.
- approve/reject actions محمية من self-approval عند الحاجة، مع reviewer ID, timestamp, proof reference, audit.
- عند Approve ينتج `PAYMENT_CONFIRMED` ويمر بنفس Provisioning Engine؛ رفض الطلب يترك account pending ويُرسل رسالة رفض غير مضللة؛ أي محاولة ثانية تعيد نفس النتيجة بدون إنشاء مكرر.

#### WHMCS specifics
- Hook/Add-on خفيف وآمن في WHMCS الريسيلر يرسل **signed events**، يدعم Multi-Product mapping من `WHMCS Product ID` إلى `SaaS user_plan_id` و`site template` و`quota` و`brand`.
- ربط Customer ID وOrder ID وInvoice ID وService ID بنطاق reseller_id؛ لا يمنح مفاتيح API الخاصة بالـWHMCS للمتصفح أو لعميل آخر.
- WHMCS `InvoicePaid` ليس إذنًا لتفعيل خاطئ إن فاتورة جزئية أو خدمة لم يتم تحديدها؛ يتأكد Adapter من حالة الفاتورة والخدمة والـinvoice items ومطابقة السعة.
- Lifecycle: New Order → Pending → Active, Renewal → extend same subscription, Upgrade/Downgrade → adjust entitlement with audit, Cancellation/Refund/Chargeback → hold/reconcile and notify. Renewals don't create duplicate Site or Agent.
- Outbound: تحديث custom fields/service status داخل WHMCS وtrigger correct branded templates؛ Event outbox مع dedup.
- لا نعدل موديول WhatsApp WHMCS الحالي بشكل مباشر؛ integration connector منفصل.

#### Laravel specifics
- Composer package اختياري لاحقًا أو **standalone Laravel Controller/Job/Listener snippet** يوضع بموقع الريسيلر فقط إن أحب؛ لا يحتاج هو رفع SaaS console.
- Webhook event adapter + optional REST API client، MySQL transaction على جانب منصتنا وqueued jobs على جانب Laravel لعمليات re-delivery.
- Registration/Booking/InvoicePaid from Laravel business logic are mapped per integration version, signed and timestamped.
- Outbound event `customer.provisioned` / `subscription.activated` يدفع إشعار Laravel Notifications حسب القنوات المفعلة. النظام المركزي يمكنه إرسال branded email fallback إن فشل تسليم الحدث، لكن لا يكرر رسالة واحدة دون dedup.

### حالات العملية والرد على API

`draft → pending_payment | pending_manual_review → payment_confirmed → provisioning → active | provisioning_failed → suspended | cancelled | refunded`.

- Response `202` عند accepted job/pending verification؛ `200` مع نفس resource id عند duplicate idempotent؛ `403` خارج الريسيلر؛ `409` على تعارض product/order identity؛ لا `200 active` قبل DB commit.
- Internal idempotency key `integration_id + event_id + external_order_id + action_type`، Foreign identity `(reseller_account_id,external_customer_id)` UNIQUE؛ invoice payment reference unique.
- قسم Integrations لكل طرف: Connection status, Product/Plan mappings, Events, Test Hook, Last Success/Error, Replay same event, Disable/Revoke, logs redacted، no PII في logs.
- لا مكالمة SIM ذاتية بمجرد الدفع/الحجز؛ التفعيل يُنشئ مساحة/Plan/Agent Draft وقد يفعّل Chat Widget، بينما حملة اتصالات تحتاج consent وDNC والدوام وخط عامل وفحص Device ACK.

## 7. بيانات تجريبية لعرض كل الحالات

- Demo Reseller Delta مع WHMCS متجره يرسل `ClientAdd → OrderPending → InvoicePaid → Active`، وعميل آخر فاتورته Manual Pending ثم Approval → Provisioned.
- Demo Reseller Nile مع Laravel booking/registration وKashier `payment.captured`; success page قبل webhook تبقى Pending حتى يصل التأكيد.
- Demo Customer لديه موقع صيدلية وموقع ملابس، 2 AI منفصلين وتباعد KB/keys؛ Reseller team وCustomer employees.
- Demo Support Access: Admin account All، Reseller فقط عملاءه، وسياسة رفض نقل الدعم إلى Reseller آخر.
- Mock email/integration delivery sink يثبت الرسائل المرسلة **بدون إرسال رسائل حقيقية** أو إنفاق رصيد أو اتصال SIM.

## 8. خريطة الملفات الجديدة بعد الاعتماد (PHP مباشر)

```text
OnTrack-voice/
├── AGENTS.md
├── docs/
│   └── plan/
│       ├── VOICE_SAAS_CONSOLE_STRUCTURE_AR.md
│       └── VOICE_SAAS_RESELLER_DOMAINS_AGENTS_ACCESS_AR.md
├── saas/
│   ├── mobile/                           # Android pilot + signed UI OTA (existing/untouched)
│   ├── app/                              # Laravel prototype source (reference only)
│   └── console/                          # NEW PHP SaaS runtime after approval
│       ├── index.php                     # central front controller
│       ├── .htaccess                     # routing, security, TLS
│       ├── assets/
│       │   ├── css/{tokens.css,app.css,rtl.css}
│       │   ├── js/{app.js,agent-flow.js,site-switcher.js}
│       │   └── images/                   # safe public assets only
│       ├── api/v1/index.php              # authenticated role/site APIs
│       ├── hooks/index.php               # signed inbound adapters
│       └── _core/                        # DENY direct HTTP access
│           ├── Bootstrap.php
│           ├── Auth/{Login,Session,Mfa,PasswordReset}.php
│           ├── Security/{Csrf,Policy,Audit,ApiKeys,WebhookSignature}.php
│           ├── Tenancy/{AccountContext,Membership,SupportAccess,BrandResolver}.php
│           ├── Domains/{DnsChallenge,TlsStatus,DomainRouter}.php
│           ├── Staff/{Roles,Permissions,Invitations}.php
│           ├── Plans/{ResellerPlans,UserPlans,Entitlements,QuotaLedger}.php
│           ├── Billing/{Orders,Payments,ManualApproval,Provisioner,Renewals}.php
│           ├── AI/{Sites,Agents,AgentVersions,FlowBuilder,Knowledge}.php
│           ├── Calls/{Devices,PhoneLines,Campaigns,CallHistory,Reports}.php
│           ├── Branding/{BrandAssets,AppBrandConfig,MobileReleaseCatalog}.php
│           ├── Api/{ScopedKeys,SiteApi,ResellerApi,RateLimiter}.php
│           ├── Integrations/
│           │   ├── Laravel/{InboundMapper,OutboundNotifier}.php
│           │   ├── WHMCS/{ClientMapper,InvoiceMapper,ProductPlans}.php
│           │   ├── Kashier/{WebhookVerifier,PaymentReconciler}.php
│           │   └── Webhooks/{Inbound,Outbox,Delivery,Retry}.php
│           ├── Controllers/{Auth,Admin,Reseller,Workspace,Support}.php
│           ├── Views/
│           │   ├── Auth/{login,forgot-password}.php
│           │   ├── Admin/{dashboard,accounts,resellers,plans,voices,staff,releases,settings}.php
│           │   ├── Reseller/{dashboard,customers,domains,branding,plans,staff,integrations,android}.php
│           │   ├── Workspace/{dashboard,sites,agents,flow,knowledge,devices,calls,campaigns,staff,api-keys}.php
│           │   └── Shared/{layout,theme,alerts,support-banner}.php
│           └── Database/{Connection,Migrator,DemoSeeder}.php
├── tools/                                # testing/deployment ONLY in GitHub
└── ops/                                  # existing host-link worker queue/results

HOST:
  /home/appontrack/public_html/saas/console/     # shared web application
  /home/appontrack/public_html/saas/mobile/      # existing pilot preserved
  /home/appontrack/.ontrack-saas/                # PRIVATE DB secrets/assets/logs
  verified-reseller-domain.example/            # DNS + TLS -> SAME /saas/console/
```

## 9. Acceptance checklist (additional to main structure)

- [ ] Admin Support Mode for any authorized customer; Reseller Support Mode only own customers; 403 cross-tenant; full actor/effective account audit.
- [ ] Custom domain authenticated DNS/TLS; correct brand/session at reseller hostname; no duplicate files, no Host header cross-tenant and no OnTrack customer UI leakage.
- [ ] APK link on Admin/Reseller/User console shows exact signed approved release and branding; UI OTA/native boundaries documented.
- [ ] User has 2+ sites and 2+ independent AI/Knowledge/Flow/API keys when plan allows; quota and one active phone agent per line verified.
- [ ] Admin can own an Internal Customer Workspace with its own agent without overriding users.
- [ ] API keys named, one-time secret, scoped to Site, rotate/revoke, audit + negative cross-site test.
- [ ] WHMCS order/signup + valid InvoicePaid event maps to plan exactly once; manual approval gate before active; correct client notification and renewal mapping.
- [ ] Laravel registration/booking+Kashier webhook verified; **success_url alone never provisions**; manual payment requires reviewer approval.
- [ ] Signed webhook replay/mismatch/missing HMAC, forged payment, duplicate invoice, cross-reseller product, refund/chargeback, outbox retry tests all PASS.
- [ ] Dummy demo data never triggers real payment/email/SMS/WhatsApp/calls.
