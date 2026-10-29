# القرار الحاسم — OnTrack هي مزود خدمة الذكاء للعميل
**OWNER APPROVED · 27 أكتوبر 2026 · SUPERSEDES ALL CONFLICTING BYOK / TENANT PROVIDER INSTRUCTIONS**

## 1. نموذج الخدمة (NON-NEGOTIABLE)
- **OnTrack توفر الذكاء الاصطناعي كخدمة مدارة بالكامل (Platform-Managed AI).** OnTrack تشتري/تربط مفاتيح Google Gemini وCustom OpenAI-compatible و/أو Omnira وغيرها، وتدير العناوين والاعتمادات والموديلات والتوجيه والفشل والتكلفة عبر **Super Admin خاص بالمنصة فقط**.
- العميل/الشركة **لا يرى ولا يضيف ولا يختار** AI provider أو base URL أو AI API key أو موديل بالـID أو Writer/Reviewer/Fallback. لا يوجد BYOK للمشتركين؛ لا تسعير BYOK؛ لا حقول Provider في التسجيل أو الـOnboarding أو Agent Studio أو Workspace Settings أو API العامة.
- العميل يختار **الباقة** ثم يصنع وكلاءه، يخصص التعليمات والمعرفة والأزرار وسير العمل والقنوات والتكامل مع مشروعه ويستهلك أرصدة/حصص الباقة. توجيه الموديلات داخلي ومملوك لفريق OnTrack.
- قد يربط العميل **API مشروعه التجاري** (Laravel/WordPress/WHMCS/WooCommerce/أي REST API) لتنفيذ أفعال الأعمال؛ مفاتيح تلك الأنظمة **Business Connector Credentials** منفصلة كليًا عن مفاتيح AI Provider. واجهة التكاملات تبقى متاحة بحدود الصلاحيات والتحقق.

## 2. لوحتان منفصلتان أمنيًا
### OnTrack Super Admin — `/control/*` (أو ما يماثله من مساحة إدارية مستقلة)
`platform_owner`, `platform_ai_ops`, `platform_billing_ops` بصلاحيات دقيقة على مستوى المنصة فقط. إجراءات AI Provider محصورة في المالك/AI Ops ومعها تدقيق كامل:
- إدارة Google وCustom والمزودين الداخليين؛ API URL/Key مخزنان مشفرين؛ تعيين Environment، enabled، health.
- `Test Connection` الحقيقي أولًا، ثم إدخال Model IDs اليدوي الحرفي فقط بعد نجاح الاختبار، ثم `Test Model` الفعلي. لا تمنح Admin صلاحية تفعيل نموذج لم ينجح، ولا تخلط Test HTTP 200 مع جودة الرد.
- سياسة مركزية لوكيل الرد (`writer`)، الوكيل المراجع (`reviewer`)، ومسار بديل (`fallback`) **بالـplan/feature/risk class أو Policy scope الذي تتحكم به OnTrack**، وليس بالعميل.
- Rate limits, cost budgets, retries, circuit breaker, fallback, capacity/quotas, margins, health/traces, tenant usage ledger, adjustable plan allowances, financial reports (internal only).
- مفاتيح AI وRaw upstream model IDs لا تظهر في استجابات Tenant APIs أو الـWidget أو Logs العميل، وحتى لو حاول عميل طلب مسارات `/control`, تُرفض على مستوى Policies/Middleware وليس بمجرد إخفاء رابط Sidebar.

### Tenant/customer dashboard — `/app/{workspace}/*`
- `الشركة، المشاريع، الوكلاء، التعليمات، المعرفة، تجربة الوكيل، القنوات، الأزرار الديناميكية، تكاملات الأعمال، المحادثات، CRM، غرفة العمليات، التحليلات، الرصيد والاستهلاك، الباقات، الفريق، إعدادات الشركة`.
- خطوة الـWizard: `المعلومات الأساسية → الدور والتعليمات → المعرفة → الإجراءات والقنوات → تجربة مجانية/من رصيد الباقة → نشر`. **ممنوع خطوة «اختر المزوّد» أو «ادخل API Key للذكاء» أو «حدد الموديل».**
- عند انقطاع المزود، الرسالة العامة «خدمة الذكاء غير متاحة مؤقتًا، سنحاول تلقائيًا أو نصعّد وفق سياسة الخدمة» دون إظهار المفاتيح/URL/أسماء مزودات خلفية.
- شاشة الاستهلاك تعرض رصيد الباقة والرسائل/المحادثات/الأفعال والمبالغ المصرح إظهارها حسب التسعير المعتمد. لا تكشف هوامش التكلفة الداخلية أو تكلفة المورد الخام.
- Playgrounds تستخدم الـPlatform AI Gateway وتخصم من الاستخدام الحقيقي وفق سياسة خطة قابلة للضبط؛ Demo/Fixtures فقط لو صرّح به ذلك.

## 3. تصميم DB وAPI
- جدول `platform_ai_provider_profiles` ملكية Platform فقط (لا `tenant_id` أو `workspace_id`)، مع encrypted keys وURL محميين.
- `platform_ai_models(provider_id, model_id_exact, test_status, ...)` و`platform_ai_routing_policies(plan_id_nullable, feature, risk_class, writer_model_id, reviewer_model_id, fallback_model_id, ...)`.
- `plans`, `plan_entitlements`, `subscriptions`, `tenant_usage_ledgers`, `platform_ai_request_costs` و`platform_ai_provider_tests`, `platform_ai_credentials_rotation`, `platform_ai_audit_events`.
- `tenant_usage_ledgers` مرتبط بالعميل للأرصدة والعدادات، لكن لا يمنح أي صلاحية إعداد على provider profiles. كلفة AI الحقيقية محفوظة في جدول/مستوى صلاحيات Platform فقط.
- `business_connector_profiles(tenant_id, project_id, ...)` يضم APIs مشاريع العملاء ومفاتيحها، منفصل عن ProviderHub تمامًا.
- Endpoints الإدارة: `/control/ai/providers/*`, `/control/ai/models/*`, `/control/ai/routing/*`, `/control/plans/*`; ممنوعة على جميع أعضاء الشركات بما فيهم tenant owner.
- Public/Tenant API للإعدادات يعرض **capabilities and entitlements** فقط، لا Provider URLs أو مفاتيح أو model IDs أو حتى حقول تحريرها.
- اتبع tenancy scoping لجميع بيانات العملاء والفواتير والمحادثات، مع مراجعة فصل Platform/Admin عن Tenants.

## 4. قابلية البيع والتكلفة
- نموذج **اشتراك/باقات تدير OnTrack استهلاك AI بداخله**، مع quotas, credits, feature limits, metering, overage policy/freeze في `Plans/Entitlements` قابلة للضبط. لا تُفرض أسعار أو عملات أو مدفوعات غير معتمدة. لا تذكر أن العميل يوفّر مفتاحه؛ التكلفة يتحكم فيها صاحب المنصة.
- OnTrack هي المسؤولة عن احتساب تكلفة API لكل موديل/طلب، تجميع الفواتير وتحديد الهامش والـoverages؛ لا تظهر هذه التفاصيل سوى للمشرفين المصرح لهم.
- Anti-abuse، استهلاك الآلات، سبام، أحجام المدخلات، rate quotas، وbudget ceilings ضرورية لأن التكلفة تقع على OnTrack.

## 5. اختبارات قبول إجبارية
1. Tenant owner وtenant admin وtenant member يحصلوا على `403` لأي `/control/ai/providers`, CRUD, test, routing، وكذلك أي API platform secret؛ بدون تسريب مفاتيح أو حتى آخر 4 حروف.
2. لا توجد أي حقول أو قوائم AI Provider/Model/API Key على Customer Landing/Dashboard/Studio/Onboarding/Settings/JSON.
3. OnTrack platform admin يضيف Custom، يختبر الاتصال فعليًا، يدخل exact model ID، يختبره ثم يربط writer/reviewer/fallback؛ حفظ وإعادة قراءة.
4. Customer Agent publish + Playground يعملان دون أي مزوّد خارجي يضيفه العميل، ويستخدمان route مسموحًا من Platform بعد فحص الباقة والاستهلاك.
5. استنفاد quota يمنع التوليد المكلف، يحتسب ledger idempotently، ويعرض تنبيهًا لطيفًا للمشترك؛ لا يعاد خصم نفس الطلب عند retry.
6. Business Connector يسمح فقط بمفاتيح مشروع العميل وبصلاحياته؛ لا يمكن استغلاله كـAI provider proxy مجاني أو الوصول لمفاتيح OnTrack.
7. تبديل مزود داخلي/تفعيل fallback لا يغيّر إعداد العميل ولا يكشف اسم المزود بالرد ولا يتطلب من العميل اتصال API.

## 6. لو التصميم القديم بيخالف القرار
هذه الوثيقة **تعلو** على كل ما ورد في ملفات v1.0 وفي مرجع منتج v0.4 عن BYOK أو Customer Provider Hub. عند اختلاف أي نصّ، نفّذ Platform-managed policy هنا. لا تحافظ على UI قديم لصفحة Providers عند العميل؛ استبدله بصفحة قدرة الوكيل/الباقة حسب السياق.

## Clarification v1.2 — manual WhatsApp Meta API data are NOT AI provider keys
The ban on tenant BYOK applies strictly to **upstream AI providers** (Google/OpenAI/Omnira/Custom LLM). It **does not** forbid tenant administrators from entering their **own WhatsApp Business Cloud API** credentials to connect an owned phone number. The customer Channels/WhatsApp area MUST expose manual Meta setup alongside Embedded Signup and Coexistence. Protect these channel credentials per tenant and never expose platform provider secrets. `docs/META_WHATSAPP_ONBOARDING_AR.md` is authoritative for this boundary.

### v1.3 Customer WhatsApp control is not AI Provider access
The tenant owner can configure automatic human-reply timeout (default 15 minutes) in WhatsApp settings, manage numbers and send messages via Inbox. None of these rights grant access to upstream AI provider credentials/models, which remain exclusively under OnTrack Super Admin.
