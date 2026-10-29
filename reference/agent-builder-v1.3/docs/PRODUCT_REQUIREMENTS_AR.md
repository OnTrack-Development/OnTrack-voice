# متطلبات المنتج — PRD

## 1. الهدف والسوق
- اسم المنتج الرسمي: **OnTrack AI Agent Builder**؛ علامة المالك OnTrack Development.
- **قرار APPROVED**: جمهور الإطلاق = جميع أنواع الشركات من يوم الإطلاق الأول. لا تدفع المستخدم إلى اختيار استضافة أو تجارة إلكترونية كحالة افتراضية؛ تقدم تسجيلًا عامًا واختيار نشاط اختياري/«أخرى».
- القيمة: مساعد AI يمكن تهيئته بالمعرفة، ربطه ببيانات Business Live، توجيهه بقواعد Smart Actions، مراقبته، وتسليمه لموظف عند الحاجة عبر أكثر من قناة.
- **ليس** نسخة من TAB؛ مرجعه في فهم تبويب التسويق ورحلة Onboarding، ولا يجوز نسخ نصوصه/شعاره/رسوماته أو مكونات كوده.

## 2. المشتركين والوحدات
- Organization/Tenant: شركة ومالك وحالة اشتراك وبيانات فوترة (من غير مدفوعات فعلية قبل قرار الأسعار).
- Workspaces: تجمع فرق ومشاريع العميل وعزل منطقي داخل الشركة.
- Projects: مشروع تجاري/موقع/تطبيق مع بيئة وبيانات وتكاملات مستقلة.
- Agents: دعم/مبيعات/حجوزات/مساعد عام/مخصص؛ شخصية وصوت العلامة وتعليمات وحدود وOwner وModel Policies.
- Knowledge: ملفات PDF/DOCX/TXT/CSV ضمن حدود/تنظيف، أسئلة وأجوبة، نص، موقع (عند السماح)، مصادر عامة/خاصة بProject وAgent؛ Job ingestion واختبار استرجاع وإصدارات.
- **Platform AI Providers (OnTrack Super Admin فقط):** Google + Custom OpenAI-compatible first؛ رموز IDs تُدخلها OnTrack نفسها حرفيًا بعد الاختبار، مع Routing مركزي Writer/Reviewer/Fallback. **لا يرى العميل مزوّد الذكاء ولا يضيف API keys له.**
- Channels: Web Chat Widget عامل، WhatsApp Meta official integration مخطط كقناة عمل حقيقية تحتاج اختبار credentials؛ جاهزية القنوات الأخرى كـadapter contracts لا ادعاء تكامل مكتمل.
- Dynamic Buttons: بناء triggers + conditions + actions + channel-specific UI من حالات Backend.
- Integration Hub: Custom REST API clients, events, signed webhooks, OpenAPI import مع review، Laravel/WordPress/WooCommerce/WHMCS wrappers.
- CRM: contacts/leads, identities verified/unverified, tagging, assignment, inbox, conversation histories and handoff lock.
- Operations: reports based on actual events, agent usage, model call errors, audit, approvals, delivery statuses.

## 3. تجارب أساسية must work
1. زائر يسجل؛ ينشئ شركة/مساحة/مشروع؛ يختار نوع الوكيل «عام»؛ يختبره في Playground؛ ينشر Widget ويحصل على snippet مرتبط بمفتاح عام غير سري.
2. مشرف OnTrack الداخلي يضيف Custom Provider، يختبر الاتصال، يُدخل أسماء الموديلات يدويًا ويختبرها ويحفظ سياسات Writer/Reviewer/Fallback؛ **مدير الشركة المشترك لا يستطيع رؤية أو فتح هذا القسم حتى لو عرف الرابط**.
3. مطوّر يربط API مشروع Laravel أو WordPress أو WHMCS بدون منح Agent مفتاح Admin عام، مع توثيق signed identity وauthorization per action.
4. عميل على الشات لديه طلب قيد المعالجة: الأزرار تتغير من «تفاصيل الطلب» إلى «إعادة جدولة» و«تواصل مع موظف» فقط عندما يرجع النظام حالة مُثبتة وصلاحياته تسمح. لا يعتمد على تخمين LLM.
5. عند التحويل لموظف يُمنع رد AI لحين فك قفل Handoff يدويًا وفق الصلاحيات.

## 4. الوظائف التي **لا يجوز تمثيلها كمنفذة** دون دليل
- Meta WhatsApp connectivity, Instagram/Messenger, paid billing, social OAuth, actual semantic vector provider, TTS/voice production, real webhooks to third-party systems.
- لا تستخدم أرقامًا تسويقية غير حقيقية (عدد العملاء، نجاح 99.9%، SLA، نسب توفير/رفع مبيعات، تقييمات). أرقام Demo لابد أن تُوسم بوضوح.

## 5. حالات الإنشاء والتشغيل
- Agent draft / configured / tested / published / paused / archived.
- **Platform provider (NOT tenant-visible):** draft / connection_verified / models_pending / model_verified / active / degraded / disabled.
- Channel disconnected / setup_pending / test_passed / live / error.
- Conversation new / active_ai / pending_action / human_locked / resolved / failed.
- Action proposed / validated / awaiting_approval / executing / succeeded / failed / reversed_if_possible.
- Knowledge uploaded / queued / processing / indexed / failed / superseded.

## 6. التوجه التجاري
- نظام اشتراكات configurable، **OnTrack وحدها تدفع وتدير AI upstream** وتبيع الخدمة للعميل ضمن باقات وحصص استخدام، وتراقب التكلفة الحقيقية والهامش داخليًا. **لا BYOK للعملاء مطلقًا**، ودون فرض أسعار افتراضية.
- صفحة Pricing تستخدم وصف خصائص واضح + CTA «اطلب عرضًا» إلى حين اعتماد الأسعار؛ لا تتظاهر بأن Checkout الحقيقي جاهز.
- العملاء يمكنهم تغيير الصناعة أو عدم اختيارها؛ الصناعة تستخدم Templates فقط لا صلاحيات/قيود حساب.

## 7. قرارات محفوظة وغير محسومة
**Approved:** جميع الشركات من أول يوم؛ العربية أولًا؛ Light مع OnTrack red/black/white؛ **Platform-managed AI by OnTrack only; NO customer BYOK**؛ Google وCustom Admin-only؛ Custom manual model IDs by OnTrack admins after connection tests؛ Smart Buttons؛ Open Business Integrations.
**Implementation proposal:** Laravel 13 / MySQL / Inertia Vue 3 / Redis; واجهات تسويق متكاملة؛ Auth؛ Project-based multi-tenancy.
**Owner undecided:** الأسعار، العملة، الدفع، launch dates، المزايا الاجتماعية الجاهزة في أول Release، سياسة Reviewer لكل رد، العقود القانونية. عدم البت لا يوقف بناء المكونات القابلة للتهيئة.

## Approved WhatsApp onboarding amendment (27 October 2026)
Each company can add multiple WhatsApp numbers via **three** channel connection methods: (1) Meta Embedded Signup, (2) official coexistence with existing WhatsApp Business App where eligible, (3) manual official Meta Cloud API credentials (WABA ID, Phone Number ID, Access Token, App ID/Secret, webhook verify token and callback URL) like OnTrack's existing WhatsApp notification module. Numbers are bound to authorized agents/routes and managed independently. Do not mix customer-owned WhatsApp channel keys with platform-only AI provider keys. See `META_WHATSAPP_ONBOARDING_AR.md`.

## WhatsApp Human Reply AI pause (owner approved, 27 Oct 2026)
In WhatsApp real-time Inbox, tenant staff manual reply triggers a conversation-scoped automatic AI timed pause (default **15 minutes**); tenant owner can configure integer 1..1440 minutes globally/per number. Any new human reply extends the pause. Restore AI automatically after the clock expires, subject to `human_locked` and channel/agent/subscription policy. If customer inbound arrived while paused and remains unanswered by staff, process one coherent pending turn once after resume; otherwise wait silently for next inbound. Persist deadline and enforce before final Meta send, including stale/in-flight AI jobs; exact behavior in `WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md`.
