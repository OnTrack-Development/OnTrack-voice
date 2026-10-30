# OnTrack AI Voice Cloud — Phone Control Center (مواصفات معتمدة)
**التاريخ:** 2026-10-31 · **الحالة:** SPEC_APPROVED / EXPERIMENTAL_IMPLEMENTATION_PENDING
**النطاق:** شركة/خط/جهاز/إيجنت واحد في ملف تعريف تجريبي؛ ليس تعديلًا على المكالمات الحية في الإنتاج.
**القرار:** الطلب المعتمد من المالك: تشغيل الرد الآلي = ON؛ طريقة الرد = DELAYED؛ الانتظار = 5 ثوانٍ؛ الإيجنت = `sales`.

## 1. السياسة المطلوبة (Target)
```json
{
  "profile": "phone_control_demo",
  "auto_answer_enabled": true,
  "answer_mode": "after_delay",
  "answer_delay_seconds": 5,
  "agent_slug": "sales",
  "policy_status": "experimental_not_live"
}
```
القيم مرتبطة بملف تعريف الخط/الشركة، ولا تعني تعميمها على كل العملاء. `sales` هنا تعيين منطقي مطلوب للإيجنت المستقبلي، **وليس إثباتًا بوجود Agent ID فعلي منشور**.

## 2. الوضع الحالي المؤكد من المصدر
- `saas/mobile/server/index.php` يحفظ `enabled` و`auto_answer` و`agent_name` وتعليمات المحادثة وصوت النموذج وقناة الالتقاط؛ لا يوجد فيه حقل `answer_delay_seconds` ولا `agent_id` متعدد المستأجرين.
- `MobileLinkService.kt` يقرأ إعدادات `device.php` تقريبًا كل 5 ثوانٍ، ويراجع حالة المكالمة كل ثانية، ثم يطلب `CaptureDaemon.linkControl('A')` مباشرة عند `state==1` و`auto_answer=true`. **لا يوجد تأخير 5 ثوانٍ** حاليًا. استقبال وتشغيل صوت AI ثنائي الاتجاه على الهاتف ورد من العميل مثبتان بتقرير المالك، لكن سلوك التأخير غير مختبر.
- الأمر في مساعد shell يستدعي Android `TelecomManager.acceptRingingCall()`، والذي أصبح deprecated منذ API 29. نجاحه مع الجهاز المُجرَّب لا يضمن النجاح في كل إصدار/OEM.
- إعداد `input_channel=mix` نجح في الاختبار الحي للمحادثة؛ لا تغيّر هذا الإعداد أثناء تطوير سياسة الرد.

## 3. نموذج السياسة والخدمة السحابية
- كل سياسة تتبع `tenant_id` + `phone_line_id` + `device_id`، وتُشير إلى `agent_id` موجود ونشط ومصرح للشركة؛ الواجهة تعرض الاسم، وقاعدة البيانات تحفظ المعرف الداخلي لا نص `sales`.
- مصدر السياسة المركزي `CallPolicyService` يحرّر نسخة `revision`، ويُرسل إلى الجهاز إعدادات محدودة موقّعة/موثّقة عبر مسار الربط. قاعدة الأولوية: `Emergency Disable`، تعطيل الخط/الإيجنت، ساعات العمل، الحظر، سياسة الرد، ثم إعدادات الإيجنت.
- `answer_mode`: `immediate`، `after_delay`، `manual`، أو `off_hours`، على أن تُفعَّل في الواجهة فقط الأنماط المثبت دعمها على الجهاز المحدد. `human_no_answer` ميزة مستقلة مشروطة؛ لا تساوي مجرد مرور 5 ثوانٍ.
- ACL: Company Owner/Admin أو دور لديه `phone_policies:update` على الخط، مع Audit Log قبل/بعد التغيير. لا يمكن ربط Agent بموارد Tenant آخر.
- حالة السياسة للوحة: `draft`، `pending_device_ack`، `applied`، `unsupported_on_device`، `failed`؛ لا نعرض «مفعّل على الهاتف» قبل تأكيد الجهاز.

## 4. التنفيذ المقترح على Android — state machine
1. عند تحوّل مكالمة واردة إلى `RINGING`، يسجّل الجهاز `call_session_id` داخليًا ووقت أول رصد `SystemClock.elapsedRealtime()`، ثم يبدأ عدادًا مستقلًا غير محجِب. لا تُستخدم ساعة الحائط لضبط المهلة.
2. يحتفظ الجهاز بلقطة immutable من سياسة الخط للمكالمة الجارية. إذا كانت `auto_answer_enabled` و`after_delay`، يحسب وقت الاستحقاق = أول `RINGING` مرصود + `5000ms`. لا يبدأ العدّ من بداية طلب HTTP أو وقت فتح واجهة الويب.
3. خلال المهلة لا يُجاب على المكالمة. إذا ردّ إنسان، انتهت الرنة، رُفض الاتصال، أصبح الخط مشغولًا، أوقف المسؤول الرد، أو لم يعد الإيجنت جاهزًا، يُلغى مؤقت تلك المكالمة بلا أي إجابة متأخرة. أولوية `manual_answer` للإنسان.
4. عند انتهاء المهلة: أعد قراءة حالة Telecom؛ لا تُرسل `A` إلا إذا كانت المكالمة **نفسها** ما زالت `RINGING`، والسياسة سارية، الجهاز والـAI جاهزان. أرسل الإجابة مرة واحدة فقط (idempotent/call-scoped).
5. عند `OFFHOOK` يبدأ اتصال `sales` الصوتي. لو سقط اتصال AI قبل الرد، **لا تلتقط مكالمة جديدة وتترك العميل بلا صوت**: اتركها ترن أو اتبع fallback صريحًا معتمدًا من الشركة. لو فشل AI بعد الرد، تسجَّل حالة failure ويُطبَّق fallback محدود ومعلن؛ لا إغلاق تلقائي عشوائي.
6. عند `IDLE`، إنهاء/رفض/فقد الاتصال، إزالة جميع timers والمتغيرات الخاصة بالمكالمة. لا ترث مكالمة جديدة عداد السابقة.
7. الجهاز يرسل acknowledgment وحالة التطبيق، مع `policy_revision`، وسبب آخر إجابة/تخطي، وقياس `ring_observed_at` و`answer_sent_at` دون تسجيل محتوى صوت أو كشف بيانات حساسة في السجلات.
8. إذا أصبحت السياسة أو الجهاز غير مدعومين، احفظ السبب وأعطِ المستخدم تنبيهًا، ولا تُطبَّق سياسات مؤقتات صورية.

**دقة التوقيت:** المصدر الحالي يعتمد polling قرابة ثانية؛ بالتالي 5 ثوانٍ هي مدة **من أول رصد الرنين داخل الخدمة**، وليست تعهدًا بأن الشبكة أو جهاز الطرف الآخر سيحسبان خمس ثوانٍ بالضبط. يلزم قياس التفاوت وقيود OEM والبطارية والخلفية تحت حمل حقيقي.

## 5. قيود Android/الشبكة — لا تُعلن ميزة مدعومة قبل الاختبار
- `TelecomManager.acceptRingingCall` deprecated منذ API 29 (رابط Android الرسمي: https://developer.android.com/reference/android/telecom/TelecomManager). يعمل في سياق أذونات/امتيازات مناسبة؛ مساعدنا الحالي shell عبر ADB المحلي، وليس امتيازًا مضمونًا لكل هاتف.
- `InCallService` يرتبط عادة بدور Default Dialer (https://developer.android.com/reference/android/telecom/InCallService). الانتقال إليه قد يتطلب تغيير تطبيق الهاتف الافتراضي ودراسة التوافق؛ لا نفترض إتاحته تلقائيًا.
- مهلة **5 ثوانٍ** المفروضة على `CallScreeningService` هي مهلة للرد على طلب *screening* من النظام، **وليست آلية تأخير الرد على المكالمة**؛ لا تستخدمها كتايمر الإجابة (https://developer.android.com/reference/android/telecom/CallScreeningService).
- مكالمات VoLTE، Wi-Fi Calling، ازدواج SIM، رفض المكالمة، المكالمة الثانية، أو قيود OEM على خدمات الخلفية قد تغيّر توقيت الحالة أو قدرات الإجابة.
- الفرق بين «بعد 5 ثوانٍ» و«بعد عدم رد الإنسان» مهم: الثانية تتطلب معرفة حالة إجابة الإنسان وسياسة اتصالات قابلة للتنفيذ، ولا تعتبر مدعومة لمجرد أن الأولى نجحت.
- الأداء الصوتي والـAgent assignment منفصلان عن سياسة الرد؛ نجاح greeting والصوت الثنائي لا يثبت توقيت الإجابة.

## 6. واجهة Phone Control Center
- حالة **AI Auto Answer**: ON / OFF مع إظهار حالة التطبيق الفعلية مقابل المطلوب.
- سياسة **Answer After**: `5 seconds` قابلة للتعديل ضمن حدود الجهاز؛ وعند عدم دعمها نعرض `Experimental / Unsupported` وسببًا واضحًا.
- **Assigned Agent**: `Sales Agent`، مع تحقق tenant/agent status، وربط بنسخة agent/flow المنشورة.
- Live indicators: الهاتف Online/Offline، المكالمة Ringing/Answered/AI Connected/Failed، Policy Revision Applied، التاريخ وآخر خطأ، وزر Emergency Stop.
- لا يعادل النقر على Save تفعيل السياسة: Save → Validate → Publish → Device Ack → Test → Supported.
- اللغة والأصوات والسياسات التجارية تعالج منفصلة في Agent Builder؛ التعيين هنا يحدد من يتولى المكالمة فقط.

## 7. البيانات والواجهات المقترحة
- `phone_call_policies(id, tenant_id, line_id, enabled, answer_mode, answer_delay_ms, agent_id, schedule_id, revision, status, created_by, updated_at)`.
- `device_policy_ack(device_id, line_id, revision, applied_at, capabilities, failure_reason)`.
- `call_events(call_id, line_id, agent_id, event, monotonic_ms, occurred_at, reason_code)`، مع سياسة خصوصية/احتفاظ واضحة.
- `PATCH /v1/phone-lines/{id}/call-policy` صلاحية `phone_policies:update`.
- `GET /v1/phone-lines/{id}/policy-status` يعرض desired / applied / supported، مع عزل الشركة وrate-limit وتسجيل Audit.

## 8. اختبار القبول قبل الدعم الرسمي
1. 20–30 مكالمة واردة فعلية على جهاز/شبكة الاختبار؛ قِس الفارق بين أول `RINGING` مرصود وأمر الإجابة، ومعدل نجاح الإجابة. ابدأ بهدف اختبار ±1 ثانية (هدف جودة، **ليس ضمانًا**)، ثم عدّل حسب القياس.
2. الرد البشري خلال أول 5 ثوانٍ: **لا يجيب الـAI بعد ذلك**، حتى لو فُصل التطبيق/عاد.
3. المتصل يغلق عند 2–4 ثوانٍ: يُلغى العداد ولا تنفذ إجابة لمكالمة أخرى.
4. تعطيل السياسة وAgent offline / API down / device disconnected: no-answer fail-safe، مع log/alert.
5. تكرار رنين متتالٍ، مكالمتان متداخلتان، استعادة التطبيق، تحديث سياسة أثناء الرنين، VoLTE وWi-Fi Calling وSIM1/SIM2 حيثما ينطبق.
6. أثناء المكالمة: يبدأ `sales` وليس Agent آخر، يستمع للعميل ويرد بالعربية المحددة، دون التأثير على قناة الالتقاط `mix`.
7. لا يتحول status إلى `Supported` إلا بمرور الاختبارات على نوع/إصدار الجهاز وشبكة الاتصال المعنيين، وتسجيل نتائج E2E.

## 9. القرار التشغيلي
**اعتماد المواصفات فقط، دون نشر تغيير حي على الهاتف أو لوحة الربط القديمة.**
القيم المطلوبة `ON`, `5 seconds`, `sales` هي هدف بناء Phone Control Center؛ **الحالة الحالية**: الرد الفوري عند الرنين (حال التفعيل)، وإيجنت تجريبي ضمن الإعدادات القديمة، ولا يوجد `answer_delay` مطبّق ولا تعيين Agent مستقل. لا تغيّر مَن يسمع ومَن يرد قبل تنفيذ واختبار وتأكيد دعم السياسة.
