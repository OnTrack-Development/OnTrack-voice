# OnTrack Voice — TODO التنفيذي المرتب (5 نوفمبر 2026)

**Owner instruction:** نفّذ المهام بالترتيب أدناه، بدءًا من **01**. لا تُعلِن DONE بدون اختبار فعلي وأدلة. لا جدولة دورية أو تأجيل. هذه قائمة تنفيذ، وليست إثباتًا لأي ميزة.

**Production snapshot قبل البدء:** SaaS host confirmed deploy `ecefdfbe488f` (74 files), [run 42068745250](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42068745250), host HTTP 200 for /login and /register. هذا لا يثبت نجاح اتصال صوتي. GitHub Actions artifact quota كان ممتلئًا؛ لا تجعل نشر الصوت يعتمد على رفع Artifact جديد بلا داع. فرع العمل النشط: `feat/voice-production-gap-20261103`.

## قواعد التنفيذ
- [ ] لا تبدأ مهمة 02 قبل إنهاء 01 أو تسجيل BLOCKED بسبب مانع خارجي محدد بالأدلة. لا تخلط مسار SaaS مع Android APK.
- [ ] لكل مهمة: تعديل كود + اختبار CI + إثبات host/deploy عند اللزوم + تجربة جهاز حقيقي عندما تتعلق بالصوت/SIM، مع روابط SHA وrun.
- [ ] حافظ على `app.ontrackegy.com/` ومسارات Android legacy/OTA والبيانات الحالية؛ لا تمسح إعدادات أو مفاتيح العملاء ولا تطبع أسرارًا في السجلات.
- [ ] عزل الحسابات: منصة ≠ ريسيلر ≠ عميل، لا تُرسل Google master API key للهاتف.
- [ ] أي claim من نوع "تم" يتطلب evidence؛ CODED وCI_PASS وHOST_DEPLOYED وDEVICE_E2E_VERIFIED حالات مختلفة.

## 01 — P0 — Google Live: التبديل بين المفاتيح أثناء المكالمة [IN_PROGRESS]
- [ ] ضبط اختيار pool الوحيد لحساب الشريحة: BYOK العميل إن وُجد، وإلا ريسيلر، وإلا منصة؛ التزام حدود الخطة.
- [ ] عند فشل Google Live 429/RESOURCE_EXHAUSTED/503/timeout أثناء المكالمة: تبريد المفتاح المحدد **900 ثانية** ومنع اختياره لمكالمات أخرى؛ اختيار مفتاح آخر **من نفس pool فقط**.
- [ ] تجديد ephemeral token للمفتاح البديل بطريقة آمنة من الجهاز المرتبط، بدون إعادة ربط الشريحة أو كشف المفتاح الرئيسي.
- [ ] الحفاظ على `call_id` واسم المتصل و`agent_id` و`published revision` والتعليمات والملخص/التاريخ المتاح دون تحية جديدة أو تكرار أسئلة.
- [ ] عدم تشغيل جلستَي AI متزامنتين في نفس المكالمة، وعدم الادعاء بأن Google تنقل سياق websocket قديم تلقائيًا؛ تطبيق استعادة السياق المصرّح به فقط.
- [ ] حصر محاولات الفشل والتبديل داخل المكالمة؛ عند انتهاء المفاتيح يكون فشل واضح وآمن دون تسريب.
- [ ] CI: اختبار 2–4 مفاتيح، 429/503/timeout، Cooldown 900s، no cross-tenant fallback، 0/1 مفتاح، ثبات context، تكرار الأحداث، وإلغاء/إنهاء المكالمة.
- [ ] DEVICE_E2E: مكالمة واردة فعلية + افتعال اختناق مفتاح + سماع استمرار الرد بعد التبديل + سجل timestamps/call_id (دون مفاتيح).

**الوضع عند فتح TODO:** أكواد العزل وإصدار token عند بداية الجلسة موجودة، لكن منطق التبديل داخل مكالمة Android لم يُثبت؛ `AiRelay::ai_relay_mark` يبرد عند فشل إصدار token فقط، وهو ليس E2E mid-call failover.

## 02 — P0 — اختبار ضغط 10–20 مكالمة متزامنة [TODO]
- [ ] لوحة Admin لمحاكاة 10 ثم 20 جلسة، latency start/first audio/interruption، connect/fail/drop per-call، مقارنة Wi-Fi/4G، ومخرجات CSV بدون بيانات حساسة.
- [ ] اختبارات تزامن وعزل موارد ومفاتيح؛ لا تُجرى اتصالات لأرقام حقيقية دون موافقة.

## 03 — P0 — Android SIM auto-answer / bidirectional audio [TODO]
- [ ] Auto-answer بعد 5 ثوانٍ، الاستماع قبل كلام AI، الربط على الشريحة الصحيحة، PCM/mute/unmute، inbound/outbound، عدم ادعاء الرد لو مفيش صوت.
- [ ] مقارنة السلوك المعروف 1.9/3.15 والنسخ 3.17/3.18، مع test فعلي على Xiaomi Note 12 Pro 5G.

## 04 — P0 — تقليل تأخير Mobile Data [TODO]
- [ ] تحليل Wi-Fi مقابل 4G/5G: handshake/SSL/token/Live first audio/jitter، reconnect، لا صوت صامت أو 12s delay.
- [ ] قياس p50/p95 وتوثيق timeline وlogs بمرجع call_id.

## 05 — P0 — Android APK + OTA [TODO]
- [ ] Signed/reproducible APK بنفس هوية الحزمة، اختبار استقبال فعلي، إشعار إصدار وزر تحديث منفصل عن pull-to-refresh، rollback وحماية ربط SIM.
- [ ] نشر OTA ومطابقة SHA/version، إغلاق مشكلة GitHub storage/build قبل claim DONE.

## 06 — P1 — تسجيل المكالمات [TODO]
- [ ] ربط تسجيل محادثة مصرح بها وسجل Call ID، حالة التسجيل ومدة/ملف/تشفير/صلاحيات/سياسة الاحتفاظ.
- [ ] فحص قيود الجهاز/Android/القانون وعدم ادعاء تسجيل صوت GSM بدون إثبات.

## 07 — P0 — ملخص المكالمات والتصعيد البشري [TODO]
- [ ] نتيجة نجاح/رفض/ملاحظات/عدم الاتصال مجددًا، وطلب تدخل بشري واضح.
- [ ] بعد إنهاء المكالمة + 10 ثوانٍ، الاتصال بمدير محدد وتسليم ملخص صحيح؛ منع تكرار اتصالات التصعيد؛ DEVICE_E2E.
- [ ] ربط الأسماء والأرقام الحقيقية بإذن وتقييد بيانات العملاء لكل حساب.

## 08 — P1 — Auth / Reseller / White-label [TODO]
- [ ] تحقق عملي لكل دور (Admin/Reseller/User)، تسجيل مخصص ودومين الريسيلر واللوجو، reset/change password والـRTL/Responsive.
- [ ] أثناء التجربة الداخلية: تسجيل دخول البريد وكلمة المرور دون 2FA، وإعادة الأمان عند الإطلاق التجاري بقرار منفصل.

## 09 — P1 — Agent Studio / Flow Builder [TODO]
- [ ] إصلاح invalid node type، حفظ Ajax/تحميل ومقارنة نسخ/نشر/تراجع، تفعيل Agent لكل SIM دون إعادة الربط.
- [ ] اختبار الفلو داخل مكالمة فعلية مع interruption/return-to-node وتتبّع لكل خطوة.

## 10 — P1 — AYO /io Realtime voice demo [TODO]
- [ ] إذن المايك على Android/mobile، إرسال نص فعلي، streaming يستمر لنهاية الصوت، STT مطابق للكلام، تقليل 12400ms، 403 diagnostics/fallback.
- [ ] lip-sync، listening/speaking، وضوح حالة الاتصال؛ benchmark latency/transcript/audio per provider.

## 11 — P1 — مزودو الصوت ومفاتيحهم [TODO]
- [ ] إدارة مفاتيح متعددة Google/Custom/NVIDIA/Omnira لكل منصة/ريسيلر/عميل بحسب الخطة؛ اختيار وأولوية، بدون كشف أسرار.
- [ ] اختبارات تكامل واقعية للمزودات، cooldown وquotas وتبعات الصوت الحي (لا تخلط مع تجربة text TTS فقط).

## 12 — P1 — WhatsApp cloud integration [TODO]
- [ ] Coexistence/manual/automatic admin-approved webhook، الربط مع WHMCS والصلاحيات والـInbox والتحويل البشري.
- [ ] اختبارات webhook E2E وعدم تعارض النشر مع خدمة WhatsApp الموجودة.

## سجل تنفيذ وتحديث الحالة
| رقم | الحالة | الدليل |
|---|---|---|
| 01 | IN_PROGRESS — CODED, PHP CI_PASS, ANDROID_PENDING | [PR #52](https://github.com/OnTrack-Development/OnTrack-voice/pull/52)؛ [PHP backend PASS run 42079048322](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42079048322)؛ Android compile/device E2E لم يُثبتا بعد |
| 02–12 | TODO | لم تُجتز بوابات القبول الكاملة |

المرجع الأشمل والمتطلبات الأصلية: [MASTER_REPAIR_ROADMAP_2026_11_03_AR.md](./MASTER_REPAIR_ROADMAP_2026_11_03_AR.md). تحديث القائمة مطلوب مع كل commit أو run. لا تحوّل أي checkbox إلى DONE اعتمادًا على نجاح HTTP 200 أو Build فقط.
