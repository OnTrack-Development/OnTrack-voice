> ⚠️ **مرجع تاريخي مُلْغى جزئيًا (v0.4)**: اعتمد المالك بتاريخ 27 أكتوبر 2026 أن OnTrack توفر AI من حساباتها ومفاتيحها فقط. أي فقرة أدناه تقول إن المشترك يضيف Google/Custom API Key أو Model IDs أو BYOK أو يختار المزود/Writer/Reviewer بنفسه **ملغاة ولا تُنفّذ**. المرجع التنفيذي الأعلى: [`../docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md`](../docs/PLATFORM_AI_SERVICE_BOUNDARY_AR.md). استخدم هذا الملف للخلفية التاريخية فقط، لا تستخرج منه user flows أو admin boundaries.

# OnTrack AI Agent Builder — Product Blueprint & Competitive Gap Register

**التاريخ:** 27 أكتوبر 2026  
**النسخة:** v0.4 — اعتماد الإطلاق التجاري العام لجميع القطاعات من أول يوم، مع الاحتفاظ بالتحليل التنافسي السابق  
**الحالة:** مرجع قرارات للمنتج قيد الاكتشاف، **ليس** إثبات وجود منصة منشورة أو تصميم نهائي.  
**ملف التصميم التنافسي المرافق:** `OnTrack_TAB_Competitor_Design_Reference_2026-10-27.md` — لا يستبدله هذا الملف.

## 1. ماذا ثبت فعلاً عن TAB؟

تعتمد المقارنة على المصادر المنشورة علنًا وعلى إجابات بوت TAB التي نقلها المستخدم. هذه الأخيرة ليست دليلًا مستقلًا، خصوصًا عندما تتعارض مع الوثائق.

| المحور | رد بوت TAB | ما تؤكده الوثائق الرسمية | وضع المقارنة |
|---|---|---|---|
| الأزرار الديناميكية الحية | غير متاحة | توجد أزرار WhatsApp Templates؛ لم يُثبت محرك أزرار State-Aware في الشات | فرصة محتملة لـOnTrack، مع التحفظ بشأن الوظائف غير المعلنة |
| External API actions | متاحة جزئيًا | GET/POST وImport JSON collection، وليست PUT/PATCH/DELETE من واجهة إعداد External API | فرصة واضحة في إدارة الإجراءات الآمنة |
| SDK لكل إطار | لا يوجد | موثق Widget + External API عام، لا يوجد دليل SDK أصلي متخصص لـLaravel أو WHMCS أو WordPress | فرصة مشروطة بجودة SDK |
| التحقق من هوية مستخدم الشات | على API العميل | الدليل يصف Bearer/API Key لخدمة التكامل، وليس نموذج ربط هوية عميل نهائي مدمج | نحتاج نموذج هوية أقوى |
| BYO Google/Custom Provider | غير متاح | شرح إعدادات Agent يذكر مزوّدين داخليين OpenAI/Anthropic/Mistral؛ الصفحة التسويقية تذكر Gemini؛ **العبارتان لا تثبتان BYOK** | سؤال مفتوح عن الفرق بين موديل داخلي ومفتاح خارجي |
| Supervisor/Reviewer متعدد الوكلاء | غير متاح | لا دليل منشور يثبت Pipeline مراجعة مستقل | ميزة مستهدفة لـOnTrack |
| Knowledge عامة مشتركة | قال غير متاحة | **معلومة خاطئة في رد البوت**: الدليل يوضح Global Data Sources مشتركة، ومصادر خاصة بالوكيل | لا تُقدَّم هذه كنقطة تفرد |
| تحديث المعرفة | لم يجب بالدقة اللازمة | المحتوى الجديد أو المعدَّل يتطلب Re-Train يدويًا | التحديث والتتبّع الآلي فرصتنا |
| صلاحيات مفصلة | Enterprise RBAC مع عدم وضوح التفاصيل | دليل العمل يحدد Owner وMember ويمكن لكل عضو رؤية كل العملاء والوكلاء؛ التسويق يعلن RBAC/SSO/Audit Logs | تعارض بين تسويق وتشغيل يحتاج إثبات مستوى Enterprise |
| CRM متعدد القنوات | لم يجب عن ربط الهوية بدقة | التسويق يعلن ملفات عملاء ومحادثات موحدة؛ لا يثبت آلية Identity Matching واحدة للشخص عبر أرقام/قنوات مختلفة | لا تنفِ الميزة؛ حقّق تفاصيل الهوية فقط |
| Traces/Reviewer/cost attribution | لم يجب | تتوفر Analytics وUsage Activity Log؛ لم يُثبت Trace دقيق على مستوى استدعاءات النماذج/الأدوات | نحتاج معيار تشغيل أدق |

**روابط المصادر الأساسية:**
- https://docs.theagentbuilder.ai/training-your-agent/adding-knowledge/
- https://docs.theagentbuilder.ai/training-your-agent/training-process/
- https://docs.theagentbuilder.ai/deploying-your-agent/external-api-integration/
- https://docs.theagentbuilder.ai/settings/managing-team/
- https://docs.theagentbuilder.ai/deploying-your-agent/whatsapp-integration/
- https://docs.theagentbuilder.ai/managing-leads/overview/
- https://docs.theagentbuilder.ai/agents/agent-specifications/
- https://docs.theagentbuilder.ai/settings/usage/
- https://theagentbuilder.ai/

## 2. الرؤية ونطاق المنتج

**OnTrack AI Agent Builder** منصة SaaS متعددة الشركات والمشروعات لإنشاء وكلاء ذكيين ونشرهم عبر قنوات التواصل والتطبيقات، وتزويدهم بمعرفة وأدوات وإجراءات آمنة قابلة للتحكم، مع سجل محادثات موحد وتصعيد بشري ومراقبة فعلية.

- **قرار معتمد من مالك المنتج (27 أكتوبر 2026): إطلاق OnTrack AI Agent Builder لكل أنواع الشركات من اليوم الأول، وليس بتركيز تجاري أول على قطاعات مختارة.** أي شركة يمكنها إنشاء حساب ومساحة عمل ومشروع ووكلاء بغض النظر عن مجال نشاطها. WHMCS يصبح تكاملًا متخصصًا اختياريًا، لا أساسًا يقيد المنصة.
- دعم العربية RTL والإنجليزية LTR من الواجهة الأولى، والقدرة على رد الوكيل بلغة العميل.
- فصل **المعرفة** عن **بيانات العميل الحية** عن **أفعال تنفيذ الإجراءات**؛ لا تحوّل استخراج بيانات حساسة إلى RAG عمومي.
- لا خلط بين المختبر OnTrack Evolution Lab ومحرك منتج SaaS ولا ادعاء نجاح تشغيل WHMCS AI Support Agent لمجرد وجود مصدر كود.

## 3. القرارات المنقولة من طلبات المالك — مؤكدة على مستوى المتطلبات

1. **ربط أي مشروع برمجي:** Laravel، WordPress/WooCommerce، WHMCS، PHP، Node/Python/.NET/Java وFrontend/Mobile عن طريق SDKs الرسمية المناسبة أو REST API/Webhooks.
2. **أزرار ديناميكية:** تتبدل حسب الحالة الحقيقية والمستخدم والإذن على الشات؛ نفس المنطق العام مع Renderer مختلف لكل قناة، لا افتراض أن قدرات Web UI = قدرات WhatsApp.
3. **Google Gemini وCustom Provider:** اتصال عبر OnTrack Gateway أو طبقة مزود آمنة؛ فحص الاتصال قبل إظهار إضافة الموديلات اليدوية في Custom؛ حفظ معرّف الموديل **حرفيًا** وإجراء Test Model منفصل قبل السماح باستخدامه.
4. **الاستفادة من تجارب WHMCS Support Agent:** قراءة معلومات العميل المصرح بها، human handoff، Reviewer/تخصصات، الأمان والسجلات، لكن النقل يحتاج إعادة تصميم لعزل الشركات وإثبات اختبارات حقيقية.
5. **منصة SaaS عامة:** اشتراكات للشركات، وكلاء خدمة عملاء ومبيعات وعمليات، CRM ومعرفة وتكاملات.
6. **مرجع تصميم TAB:** محفوظ في ملف تحليل مستقل؛ لا تدَّعِ Pixel-perfect CSS/خطوط/ألوان عند غياب فحص متصفح فعلي.

## 4. القرارات المعمارية الموصى بها — قابلة للاعتماد في النقاش

### 4.1 الهوية وعزل الشركات
- `Tenant → Workspace → Project → Environment → Agents/Integrations/Knowledge` مع تنظيم واضح للملكية.
- RBAC على Workspace، Project، Agent، Inbox، Action، Knowledge، Secrets وBilling.
- Audit على تغيير الصلاحيات، تغيير المفاتيح، إصدارات الوكلاء، الأفعال الحساسة، النشر والحذف.
- جلسة العميل النهائي موثّقة من التطبيق المالك، مع signed identity claims قصيرة المدة، وليس قبول أي Customer ID نصّي يولده الوكيل.

### 4.2 Provider Hub — OnTrack Super Admin حصريًا
- **لوحة Super Admin فقط** بروفايدر جوجل وبروفايدر Custom OpenAI-compatible، إضافة إلى بروفايدرز موجودة اختيارية؛ السماح بمزوّدات إضافية بإصدارات Adapter دون تعديل محرك الشات.
- Test Connection: DNS/TLS/Auth/endpoint reachability + response classification.
- Test Model: استدعاء توليد قصير باسم النموذج المدخل + اختبار parse/usage ونوع الخطأ.
- `Writer/Reviewer/Fallback` لكل وكيل/مهمة بشكل مستقل؛ التنبيه عند عدم إمكانية توفير Reviewer دون إرسال رد غير مراجع وفق سياسة المؤسسة.
- مفاتيح مشفّرة وفصل بيئات Dev/Staging/Production؛ منع SSRF، redirect، credential leakage.

### 4.3 Agents وRuntime
- مسار بسيط للـFAQ/Lead باستخدام وكيل واحد سريع مع الضوابط المقررة، ومسار أعلى تعقيدًا لتشغيل Supervisor ومتخصص أو أكثر وReviewer عند الحاجة/حسب السياسة.
- Tools مُعرّفة بعقود schema لا بأوامر Shell مفتوحة؛ Read Only/Approval Required/Auto حسب الإذن مع حماية خاصّة لعمليات المالية والبيانات الشخصية.
- State machine للمحادثة وأوامر مؤمنة وIdempotency لمنع إرسال/تنفيذ مكرر.
- Budget/Rate Limit/Fallback/Circuit Breaker/Queue مع كشف `provider_timeout`, `429`, `5xx`, `invalid_json` و`delivery_failed` كحالات منفصلة.

### 4.4 Dynamic Smart Buttons
- لكل زر: مفتاح، تسمية عربية/إنجليزية، الحالة/شرط الظهور، الـAPI action، صلاحية العرض والتنفيذ، شكل القناة، مدة الصلاحية، خطوة التأكيد.
- المحرك لا يثق بإجابة AI بشأن نجاح الإجراء؛ يقرأ Event أو نتيجة API موثقة ثم يعيد بناء الأزرار.
- بالويب تصميم وتفاعل أغنى، وWhatsApp يطبق الرسائل التفاعلية المسموحة أو fallback نصيًا حسب قيود Meta.

### 4.5 Integration Hub
- Web Widget SDK بسيط، Laravel Package، WordPress/WooCommerce Plugin، WHMCS Addon، REST/Events/Webhooks.
- Generic Connector Builder يدعم GET/POST/PUT/PATCH/DELETE ولكن مع Allowlist ومخططات طلب/استجابة، سرية الاعتماد، سياسات Read/Write/Approval، صلاحية مالك السجل، وتشغيل تجريبي.
- استيراد OpenAPI مع مراجعة يدوية للصلاحيات، منع auto-import لفعل حساس مع تفعيله فورًا.
- أحداث `customer.verified`, `order.updated`, `ticket.created`, `booking.confirmed` وغيرها بتوقيع ومنع إعادة التشغيل.

### 4.6 Knowledge & CRM
- Knowledge عامة للشركة، خاصة بالمشروع، وخاصة بالوكيل. Versioned ingestion مع Crawl/API/Schedule/Upload، إدراج/تحديث تفاضلي، تجارب استرجاع، Attribution وتقييم الجودة.
- CRM مرحلي: عميل/Lead/Conversation/Tag/Assignment/Handoff/History أولًا، ثم pipelines وAutomation متقدم لاحقًا.
- التحقق من الهوية قبل دمج سجلات نفس العميل عبر القنوات أو الوصول للطلب/الفاتورة.

### 4.7 Observability & Quality
- Traces حقيقية لكل محادثة وTool Call/Provider/Model/Queue/Reviewer/Send، مع زمن الاستجابة والتوكن/التكلفة حيث متاحة، وأخطاء HTTP، وسبب التوقف/التحويل.
- Playground مع اختبارات حالات، Preview للأزرار والـAction sandbox، مقارنة نسخ قبل النشر، وإثبات E2E للقنوات المهمة.
- Dashboard للـInbox وHuman Handoff، Operations Room لحظية من أحداث واقعية وليست Animation تمثيلية.

## 5. خريطة الصفحات المقترحة

**الموقع العام:** Home / Platform / Agent Builder / Smart Buttons / Integrations / Channels / Knowledge / CRM / Use Cases / Industries / Pricing / Enterprise / Security / Developers / Docs / Changelog / Help / Contact & Demo / Login / Signup.

**المنتج:** Overview / Workspaces / Projects / Environments / Agents / New Agent Wizard / Studio / Prompt & Policies / Specialist Roles / AI capabilities / Knowledge / Playground / Evaluation / Channels / Web Widget / WhatsApp / Dynamic Buttons / Actions / API Connectors / Webhooks / CRM / Unified Inbox / Escalations / Operations Room / Traces / Analytics / Usage & Billing / Team & Roles / Security / Settings / Developer Keys / Deployment History.

## 6. Design Direction — مرتبط بمرجع المنافس

- **OnTrack هوية مستقلة**: أسود/أبيض/أحمر، Light وDark، خطوط عربية/لاتينية متوازنة، واجهة عملية ذات جمالية قوية وحركة Cinematic محسوبة، وتجنّب نسخ أصول أو أكواد TAB.
- Hero الصفحة الرئيسية يعرض منتجًا تفاعليًا **حقيقيًا**: تدفق رسالة → Tool Call → تحقق الحالة → تغيير Smart Buttons → مراجعة → إرسال/تحويل، وليس Mockup يزعم تشغيلًا لم يُثبت.
- Dashboard: Sidebar قابلة للطي، محدد Workspace/Project، Command/Search، Log Inspector، جداول عالية القراءة، حالات loading/error/empty، Mobile responsive وAccessibility.
- **لا توجد قياسات معتمدة** لخطوط TAB أو CSS التفصيلي أو صفحة كل حالة من لوحة التحكم. الفهرس البصري السابق جزئي وقد يكتمل فقط بمعاينة Screenshot/DevTools مرئية.

## 7. أولويات البناء العملية (اقتراح، لا موعد أو إنجاز مزعوم)

**P0 — Core SaaS:** auth/tenancy/roles/projects; agent create/edit; Provider Hub Google/Custom; KB ingestion; Playground; Web Widget; generic read-only Connector; signed identity; tests/audit/usage basics.

**P1 — Smart Operations:** Dynamic Buttons، Actions آمنة بموافقات، WhatsApp الرسمي، Inbox/Handoff، trace/reviewer/fallback، Laravel وWordPress وWHMCS integrations بتجارب فعلية.

**P2 — Expansion:** Instagram/Messenger، قطاعيات Templates، CRM Pipelines، Marketplace موصلات، قدرات Enterprise، Voice إن تقرر إطلاقه ضمن هذا المنتج.

> معيار القبول: نجاح المنطق الأساسي عبر Web وWhatsApp مع هوية صحيحة، لا تسرّب بيانات بين الشركات، واختبارات E2E حقيقية وأثر Audit/Reviewer. HTTP 200 أو لقطات UI ليست وحدها PASS.

## 8. أسئلة فقط لما لم يُحسم بعد

### أسئلة عن TAB لتصحيح التعارض
1. وثائقكم تذكر Global Data Sources مشتركة، بينما البوت قال لا توجد. هل تعمل الآن؟ ما طريقة ربطها وإعادة التدريب؟
2. الصفحة الرئيسية تذكر Gemini وEnterprise RBAC/SSO/Audit، والدليل يذكر خيارات نماذج أخرى وOwner/Member فقط. هل هذه قدرات فعلية عند تسجيل الدخول أو Enterprise-only أم تسويق قيد التطوير؟ اطلب رابط توثيق/فيديو.
3. هل CRM يربط شخصًا بعينه بين WhatsApp وWeb وInstagram بهوية موثّقة واحدة، أم يسجل محادثات منفصلة لكل قناة؟
4. ما واجهات الـWebhooks والـConversation APIs المتاحة الآن وكيف تفشل/تعاد المحاولة؟
5. هل توجد أمثلة فعلية لتواريخ وسجلات Tool Calls أو TraceIDs وموافقات قبل actions؟ لا يكفي Activity Log لفواتير الرصيد.

### قرارات منتج للمالك
- **القطاع المستهدف: محسوم ومعتمد** — جميع أنواع الشركات من أول يوم؛ لا قطاع أول حصري ولا قيود تسجيل حسب النشاط.
- **القرار الجديد ملزم:** اشتراكات وباقات AI مدارة من OnTrack فقط؛ غير محسوم عدد الأرصدة والأسعار وطريقة التحصيل، وBYOK للعميل ملغي.
- **أول قنوات التشغيل:** Web + WhatsApp من بداية الإطلاق أم Web أولًا ثم WhatsApp؟
- **الموافقات:** Auto read والعمليات البسيطة فقط، والأفعال الحساسة Ask؛ أي حالات تجارية استثنائية يريد مالك الشركة السماح بها؟

## 9. سجل الإصدارات ومصدر الحقيقة

- v0.1: التصور الأولي والأسئلة العشرة.
- **v0.2**: مقارنة وثائق TAB بإجابات البوت، إصلاح خطأ قاعدة المعرفة المشتركة، فصل الحقائق المتحققة عن مزاعم التسويق، ضبط معمارية الهوية والأزرار وActions، وتحديد الأسئلة النهائية.
- ملف وصف TAB التفصيلي والفهرس الكامل مستقل في `OnTrack_TAB_Competitor_Design_Reference_2026-10-27.md`.
- **لم ينفَّذ أو يُنشر أي كود SaaS بهذا المستند.**


---

## 10. تحديث التحقق — رد بوت TAB الثاني (27 أكتوبر 2026)

**قاعدة الاستدلال:** جواب البوت دليل على ما يدّعي مساعد الشركة أنه يعرفه، وليس شهادة تقنية على المنتج المنشور. نميز بين (أ) موجود بوثائق الإعداد التفصيلية، (ب) معلن تسويقيًا بلا إثبات تنفيذي، (ج) غير مثبت.

| الموضوع | الوثيقة التفصيلية المتاحة | التصريح التسويقي/البوت | حكم المقارنة |
|---|---|---|---|
| Global Data Sources | الدليل يؤكد مصادر عامة يمكن ربطها بأكثر من Agent، إلى جانب مصادر خاصة؛ ويلزم `Re-Train` بعد إضافة محتوى | البوت تراجع عن نفي وجودها وقال إنه لا يملك التفاصيل | ميزة موجودة في TAB، ولا يجوز اعتبار المشاركة تفردًا عندنا؛ التفاصيل الدقيقة لنشر التحديث على عدة Agents غير محسومة |
| Gemini | Glossary وWhat's New يذكران OpenAI/Anthropic/Mistral | الصفحة الرئيسية تذكر Gemini، والبوت ينفي توفره | لا تعتبر توفر Gemini للعميل مؤكدًا في TAB، ولا تساوِ بين موديل تديره المنصة وBYO API Key |
| Enterprise RBAC/SSO/Audit | دليل Managing Team يشرح فقط Owner/Member ورؤية جميع الوكلاء والعملاء داخل Workspace | الصفحة الرئيسية وHelp يعلنان RBAC/SSO/Audit | الادعاء التسويقي موجود، لكن قدراته وحدوده ومرحلة إتاحته غير مثبتة في دليل التشغيل |
| Unified cross-channel CRM | الدليل يثبت سجل المحادثات مع معلومات القناة؛ الدليل يؤكد إمكانية الرد البشري على قنوات مختلفة | الموقع التسويقي وHelp يعلنان وحدة العملاء والمحادثات بلا تكرار | لا نؤكد ولا ننفي ربط WhatsApp/Web لنفس الهوية دون تجربة مستخدم موثقة |
| External APIs | GET وPOST في إعداد External API، مع Bearer/API Key/Basic Auth وتكوين لكل وكيل وImport Collection | البوت يقول API جزئي فقط | ثابت؛ ليست كل تكاملات TAB محدودة بذات وظائف أداة External API؛ يوجد Shopify/Salla بعمليات متجر إضافية |
| WhatsApp | التوثيق الرسمي يذكر Meta Embedded Signup مع Coexistence للرقم والتدخل البشري | الردود السابقة ركزت على أزرار القوالب | لا نقلل من هذه الميزات: ينبغي اعتبارها حدًا تنافسيًا أساسيًا لنا، مع تقديم Smart Buttons خاصة بالمحادثات |

### روابط تحقق مباشرة
- https://docs.theagentbuilder.ai/training-your-agent/adding-knowledge/
- https://docs.theagentbuilder.ai/settings/managing-team/
- https://docs.theagentbuilder.ai/deploying-your-agent/external-api-integration/
- https://docs.theagentbuilder.ai/managing-leads/overview/
- https://docs.theagentbuilder.ai/deploying-your-agent/whatsapp-integration/
- https://docs.theagentbuilder.ai/integrations/overview/
- https://docs.theagentbuilder.ai/help/whats-new/
- https://theagentbuilder.ai/
- https://theagentbuilder.ai/help

### تحديث قرارات التصميم — موصى باعتمادها
1. **معتمد: استهداف جميع أنواع الشركات عند الإطلاق:** التسويق والتسجيل وتجربة بدء الاستخدام متاحة لجميع القطاعات من أول يوم، بلا اشتراط نشاط معين. ترتيب تطوير Connectors مثل Laravel وWordPress/WooCommerce وWHMCS ترتيب هندسي لا تقليص لجمهور الإطلاق.
2. **المحرك مستقل عن التطبيق والوسيلة:** Company → Workspace → Project → Agent، وطبقة تكامل تفصل الهوية والمعرفة والأفعال والقنوات.
3. **Identity-first:** لا تكتفي برقم الهاتف أو البريد الذي يكتبه AI؛ التحقق عبر Login Session موقّع أو OTP/طريقة موثوقة وتفويض لكل Endpoint؛ CRM contact لا يساوي Verified account.
4. **Knowledge versioning:** Global/Project/Agent scopes مع تاريخ إصدارات ونشر تدريجي ونطاق وصول؛ البيانات الحية لا تخلط بمستندات RAG غير المحدثة.
5. **Dynamic buttons:** الواجهة تعرض إجراءً بعد تحقق الحالة والصلاحيات من النظام المصدر؛ كل قناة لها قدراتها وقواعد Meta؛ لا يجوز أن يكتسب AI صلاحية عبر زر.
6. **Custom Provider:** إدخال عنوان API ومفتاح آمن؛ Test Connection قبل فتح إضافة Model IDs يدويًا؛ Test Model برسالة فعلية قبل تفعيله؛ الاحتفاظ بالمعرّف حرفيًا؛ Model Writer/Reviewer وفشل/بديل محدد.
7. **Approvals + Audit:** دورة Action `proposed → validated → (approved if needed) → executed → verified`، وتخزين أثر منقح؛ منع التكرار والـRetries غير المحدودة.
8. **Go-live Gate:** لا تفعيل لقناة أو Action ذات أثر إلا باختبار end-to-end يثبت الهوية والإرسال/التنفيذ والمراجعة/التصعيد مع رجوع آمن عند الفشل.

### قرار جمهور الإطلاق — معتمد؛ باقي خيارات النطاق التشغيلي قيد الحسم
- **جمهور الإطلاق المعتمد هو جميع أنواع الشركات**. نطاق المميزات الأولي المقترح يركز على Web Chat وWhatsApp وPlayground وإدارة المعرفة والـActions الآمنة والأزرار الديناميكية، مع البنية متعددة المشروعات من اليوم الأول؛ تفاصيل الإتاحة والتسعير والقنوات لم تُعتمد نهائيًا.
- دعم Integrations الأصيلة يتوسع عبر حزم مستقلة مدققة؛ لا يعني وجود Rest API وحده أن تطبيق Laravel أو WordPress متكامل بالفعل.
- التزام المنتج النهائي بميزات Advanced Multi-Agent وOperations Room وCRM ذكي، مع مراحل نشر منضبطة لا وعود نجاح غير مختبرة.

**ملاحظات الحالة:** هذا تحديث مواصفات فقط. لا يوجد ادعاء أن OnTrack AI Agent Builder قد بُني أو نُشر أو أن أي بوت WhatsApp نجح باختبار E2E مؤكد في هذه المهمة.


---

## 11. سجل قرار معتمد — استهداف كل أنواع الشركات من أول يوم (27 أكتوبر 2026)

**نص قرار المالك:** «اعتمد استهداف كل أنواع الشركات من أول يوم لإطلاق OnTrack AI Agent Builder.»

**الحالة:** APPROVED — هذا قرار نطاق جمهور المنتج، وليس اقتراحًا أو خيارًا قيد النقاش. يتقدم على أية توصية سابقة بتركيز تجاري أول على الاستضافة أو التجارة الإلكترونية أو وكالات البرمجة/التسويق.

### الأثر الملزم على المنتج

1. **التسجيل والتهيئة:** لا يتم رفض أو حصر التسجيل بحسب النشاط؛ اختيار الصناعة اختياري لتخصيص البداية والقوالب، مع خيار عام أو تصنيف آخر.
2. **المعمارية:** محرك الوكلاء والمعرفة وCRM والتكاملات والأزرار والصلاحيات مستقل عن قطاع بعينه، مع عزل `Tenant → Workspace → Project`.
3. **التسويق والتصميم:** Home وPricing وSignup وPlatform تستهدف الشركات عامة. صفحات Industries/Use Cases تعرض تطبيقات متعددة، من غير تقديم مجال واحد بوصفه الاستخدام الوحيد أو الأساسي.
4. **التشغيل:** Agent عام قابل للإعداد عبر نصوص المعرفة وREST APIs وWeb Widget من البداية. الدعم الشامل للجمهور لا يعني أن كل قطاع سيملك قالبًا متخصصًا أو SDK أصليًا أو تكاملًا جاهزًا في أول إصدار.
5. **التكاملات:** Laravel وWordPress/WooCommerce وWHMCS وغيرهم عبر واجهات عامة؛ أولوية بناء الموصلات الأصلية قرار تنفيذي لاحق ولا تقيّد من يحق له شراء المنتج.
6. **المعايير قبل الإطلاق:** اختبار رحلة التسجيل وإنشاء الشركة/المشروع/الوكيل وتجربة الـWidget والـKnowledge والـActions عبر قطاعات متنوعة، مع منع تسرب البيانات بين الشركات؛ لا إعلان عن جاهزية إنتاجية قبل اجتياز الاختبارات.

### ما يزال غير محسوم

- التسعير والباقات: OnTrack تدفع تكلفة النماذج وحدها وتبيع باقات/أرصدة للعميل؛ BYOK ملغي.
- قنوات الإصدار التجاري الأول وتاريخ تفعيل كل قناة.
- سياسة الموافقات التفصيلية للأفعال الحساسة والفئات المحظورة.
- ما إذا كانت قوالب صناعات معينة تظهر افتراضيًا أو كاختيار بعد التسجيل.

**تحديث الوثائق:** يحفظ هذا الملف القرارات المعلنة في المحادثة؛ لا يدل على تشغيل تطبيق SaaS أو نشر كود.
