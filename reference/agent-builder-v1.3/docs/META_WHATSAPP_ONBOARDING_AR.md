# WhatsApp Official Meta Cloud API — 3 onboarding methods (قرار معتمد)

**Owner approval: 27 October 2026.** Mandatory product specification for OnTrack AI Agent Builder. This document overrides generic WhatsApp setup descriptions in older handoff material. The customer owns the WhatsApp Business number and may connect it; **OnTrack exclusively owns/provides upstream AI providers and models**. Never confuse WhatsApp channel credentials (allowed for authorized tenant admins) with AI provider credentials (platform staff only).

## صفحة العميل: القنوات ← WhatsApp ← ربط رقم

عرض ثلاث بطاقات اختيار متساوية الوضوح، الأولى افتراضية للعميل غير التقني، الثالثة للمستخدم المتقدم:

1. **الربط التلقائي عبر Meta — Embedded Signup**: فتح تدفق Meta الرسمي، اختيار نشاط تجاري/WABA ورقم، الموافقة على الصلاحيات، ثم callback/tokens exchange server-side، والتحقق من الربط والاشتراك في رسائل webhook وإجراء اختبار. ينبغي تجهيز إعدادات Meta Tech Provider/APP Review اللازمة لهذا المسار. لا تعد نجاح الربط لمجرد إغلاق نافذة OAuth.
2. **ربط WhatsApp Business App الحالي — Coexistence**: تدفق Meta الداعم لربط تطبيق WhatsApp Business مع Cloud API، **للأرقام المؤهلة فقط** وطبقًا للشروط والإمكانات السارية؛ تعرض رسالة واضحة لو غير متاح ويُقدم مسار مدعوم بديل. لا تعد باستمرار واتساب العادي أو كل مزايا التطبيق لكل رقم، ولا تحوّل الرقم/تسجلّه بطريقة تمس خدمة العميل قبل موافقة صريحة.
3. **الربط اليدوي — Manual Cloud API (مثل موديول WhatsApp Notifications الحالي)**: إذا العميل لديه Meta App وWhatsApp Business Account وإعدادات Cloud API، يسمح صاحب الشركة/العضو المخول بإدخال بيانات الربط بنفسه دون أي اعتماد على مزود ذكاء خارجي يدخله العميل.

### حقول نموذج الربط اليدوي

| اسم الحقل بالعربي | قيمة/قاعدة | ملاحظات |
|---|---|---|
| اسم الربط/الرقم | تسمية داخلية | مثال: الدعم / المبيعات |
| رقم واتساب الظاهر | E.164 للعرض | لا تستخدمه كمعرّف Meta الوحيد؛ استخرج/تحقق من الرقم الرسمي عند الاختبار |
| `WhatsApp Business Account ID (WABA ID)` | إلزامي | أرقام Meta؛ اربطه بالشركة الحالية |
| `Phone Number ID` | إلزامي | أرقام Meta؛ نقطة توجيه الرسائل الأساسية |
| `Meta Access Token` | إلزامي | Token صالح بصلاحيات Cloud API للرقم؛ يُخزن مشفرًا، write-only، ولا يُعاد في API/UI/log |
| `Meta App ID` | مطلوب عند تهيئة Webhook الخاص بتطبيق العميل | يُطابق مع بيانات التطبيق ولا يستخدم بدل Phone Number ID |
| `Meta App Secret` | إلزامي لتفعيل استقبال Webhook آمن | مشفر؛ للتحقق من `X-Hub-Signature-256`؛ لا تعرضه لاحقًا |
| `Webhook Callback URL` | يولدّه OnTrack لكل اتصال | للنسخ، HTTPS، ليس حقل URL عشوائي يغير وجهة استقبال بيانات العملاء |
| `Webhook Verify Token` | يولده OnTrack بقيمة عشوائية قوية | عرض آمن للنسخ مرة أولى وإعادة توليد محكومة؛ يمكن إدخال token خاص عند وجود إعداد Meta قائم مع تحقق مناسب |
| API Graph version | يضبطها OnTrack | لا تستخدم إصدارًا ثابتًا قديمًا بلا اختبار توافق |
| نوع الاستخدام/الوكيل | مشروع/وكيل افتراضي، وسياسة توجيه | رقم واحد قد يوجه لأكثر من Agent حسب الشروط/الأقسام؛ لا تسمح بتوجيه عشوائي عابر للمشروعات |

**UX after saving:** حفظ المسودة → اختبار بيانات Meta → إعداد واشتراك Webhook → تحقق الاستقبال → إرسال رسالة اختبار مسموح بها إلى رقم اختبار اختاره العميل صراحة → ربط الوكيل/قواعد الأزرار → تفعيل. حالات واضحة `draft`, `credential_valid`, `webhook_pending`, `webhook_verified`, `send_test_passed`, `receive_test_passed`, `active`, `degraded`, `disconnected`, `revoked`. تجنب وضع `active` لمجرد نجاح API GET؛ اظهر السبب الدقيق دون أسرار. قد يحتاج إرسال الرسائل خارج نافذة خدمة العميل قالبًا معتمدًا من Meta.

### واجهة الرقم بعد الإضافة

- قائمة الأرقام لكل شركة (عدة أرقام لكل شركة)، الاسم، الرقم الظاهر، WABA ID مُخفّف، نوع الاتصال `embedded_signup | coexistence | manual_cloud_api`، الحالة وآخر نجاح وآخر خطأ منقح، الوكيل/المشروع المرتبط، حالة Webhook، جودة الرقم عند توفرها، حدود المراسلة عند توفرها.
- أزرار: `اختبار الاتصال`, `اختبار Webhook`, `إرسال رسالة اختبار`, `ربط الوكيل`, `إدارة القوالب`, `سجل الأحداث`, `تعطيل مؤقت`, `تغيير البيانات`, `فصل الربط`؛ الإجراءات المدمرة بتأكيد ومراجعة أثرها.
- القوالب: استيراد/عرض/تحديث قالب وفق إمكانات API وسياسة Meta، ربطه بأحداث الإشعارات. أزرار المحادثة الديناميكية تستخدم *interactive messages* المسموح بها في الجلسة وليس مجرد Broadcast templates؛ fallback للنص/الاختيارات المرقمة عند عدم دعم النوع.
- Webhook واحد/أكثر حسب تصميم التطبيقات المعتمد: لكل Meta App إعداد callback/verify token محدد. صياغة مقترحة: `/api/webhooks/meta/whatsapp/{public_connection_id}` للتعريف المستقر غير السري؛ لا تستخدم public id كبديل للتحقق من التوقيع. تحقق من signature على RAW request body قبل أي تفكيك أو حفظ. تحقق أن phone_number_id/WABA ID الواردين يعودان للاتصال داخل نفس tenant، وأن التطبيق وتوقيعه/الاشتراك صحيحان. ACK بعد التخزين durable؛ queue للمعالجة.

## Security and production restrictions

- لا تسمح أبدًا بربط Phone Number ID أو WABA asset موجود ومفعل لشركة أخرى بدون مسار نقل ملكية/فصل واضح وموافقة وحالة آمنة؛ Unique constraints + service-layer authorization.
- لا ترسل access token أو app secret أو verify token للسجل، Analytics، webhook payload، HTML render، API GET، أو Agent prompt. Data encryption at rest، secret redaction، scoped staff roles، token rotation/revocation، retries وحدود.
- اختبار Meta read-only باستخدام GET مسموح من واجهة المستأجر المخوّل؛ أي outbound test send يحتاج رقم وجهة يحدده المستخدم وتأكيده. لا ترسل رسائل عشوائية للعملاء/عملاء تجريبيين بلا صلاحية أو بدون قواعد نافذة المحادثة.
- تحقق HMAC constant-time من `X-Hub-Signature-256` باستعمال App Secret المناسب، من body الأصلي. GET webhook challenge يعتمد على verify token مربوط بالتطبيق والـcallback ولا يمثل تأكيدًا لاستقبال/إرسال رسالة فعلية. لا تقبل `hub.challenge` بدون تحقق صحيح.
- Webhook dedupe باستخدام message ID والإشعار/attempt ID، وIdempotency عند الإرسال، وQueue leasing، ومعالجة out-of-order، وstatus callbacks، وعدم الرد على الرسائل الخارجة الصادرة من النظام أو الموظفين.
- عندما تكون الحالة `human_locked`، لا يرسل AI ردًا تلقائيًا ولو كان مسبقًا في الـqueue.
- Identity verification منفصل عن معرفة رقم واتساب، خصوصًا للفواتير والبيانات الحساسة.
- لا تسمح بكتابة AI provider keys/models في هذه الشاشة: WhatsApp manual credentials *قناة عميل مملوكة له*، وليس BYOK لمزود الذكاء. AI من OnTrack فقط وفق الباقة.
- ملكية Meta App قد تختلف في طرق embedded/coexistence عن التطبيق اليدوي المملوك للعميل؛ حافظ على secure credentials per connection وبروتوكول مناسب لكل وضع.

## Functional acceptance / Real E2E

1. Tenant A يربط رقمًا باستخدام manual، يجتاز metadata check، يُعطى callback/verify token، يستقبل challenge صالح، ثم signed webhook inbound، ويحفظ المحادثة ويرد من Agent مرتبط بعد مراجعة الحالة.
2. Token خاطئ، WABA ID لا يطابق Phone Number ID، أو App Secret غلط => فشل واضح، ولا يمكن تفعيل الرقم أو إرسال/استقبال كأنه Active.
3. Tenant B لا يرى بيانات Tenant A أو message logs/credentials ولا يمكنه تسجيل Phone Number ID ذاته بالالتفاف على API، بما في ذلك تغيير tenant_id في POST.
4. 3 modes display and persist correctly; switching onboarding route does not migrate/deregister a live business number without explicit confirmation.
5. Raw webhook body HMAC verification, challenge, retry/dedupe, signature rejection, outbound 24-hour/template restriction and status callbacks tested independently.
6. Human Lock blocks pending automatic sends; support reply actions/interactive button payload map to one authorized current-state tool, stale interactive requests do not execute.
7. Token rotation/reconnect preserves conversation history, revocation disables sending. Test credential never appears in logs/API/screenshots.
8. Multi-number/multi-project routing and handoff verified, plus managed AI use without tenant-controlled AI provider keys.
9. Show `MOCK_ONLY` unless Meta live credential + incoming and outgoing delivery evidence tested; HTTP 200 metadata is *not* live E2E success.

## Public implementation references
- Meta Embedded Signup: https://www.postman.com/meta/whatsapp-business-platform/documentation/du6gzjv/embedded-signup
- Meta WhatsApp Cloud API: https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api
- Meta number registration and verification: https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api?entity=request-13382743-f4211a46-9301-4735-a23c-7359f1a7f849

**The v1.2 handoff is a spec, not a configured Meta channel or demonstrated live WhatsApp integration.**

## v1.3 — Additional real-time Inbox and post-human-send suppression
Once an official Meta Cloud API number is active, offer the owner/staff a dedicated WhatsApp Web-inspired inbox, even for Cloud API-only numbers without a WhatsApp Business mobile app. The inbox supports all media/message types actually permitted by Meta, low-latency push, browser notifications, and live delivery status. After a staff member manually sends a message through OnTrack Inbox, suppress automatic AI in **that conversation** for a tenant-configurable number of minutes, default 15; each staff send restarts the deadline; restore automatically after expiry unless permanent Human Lock remains active. Exact specification `docs/WHATSAPP_INBOX_REALTIME_HUMAN_PAUSE_AR.md`.
