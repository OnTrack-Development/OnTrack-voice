# OnTrack Voice — خارطة الإصلاح والتطوير الموحّدة (المصدر التنفيذي المعتمد)

**الإصدار:** 1.0 — 3 نوفمبر 2026 — Africa/Cairo  
**الوضع:** Internal development / Owner-only testing. لا يوجد عملاء إنتاج حقيقيون حتى الآن.  
**المستودع:** OnTrack-Development/OnTrack-voice  
**Canonical web:** https://app.ontrackegy.com/  
**الكود المعمول به عند المراجعة:** main = d298b7d759f9a30b1ef24e58232f63c2edf34bc9؛ أحدث نسخة Root منشورة = فرع feat/voice-production-gap-20261103 عند 5d0de134925a4703c78ca47da06601ff7ddd7b29؛ يلزم دمجهما وتحقيق النشر والـCI.
**تنبيه:** هذه الوثيقة خطة متابعة ومتطلبات لا تعني أن مهام الإصلاح نُفذت أو أن المكالمات E2E نجحت. لم ننشئ جدولة تطوير جديدة؛ تم إيقاف جدولة العمل كل ساعة بطلب المالك في 2 نوفمبر، ولا تُعاد دون طلب جديد.

## 1. عقد المصدر الوحيد
عند تعارض الوثائق: **آخر طلب صريح من المالك** → قرارات Q01–Q24 وOV في docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md → هذه الخارطة والمصفوفة المصاحبة → الأدلة الفعلية من GitHub/host/Android → الخطط القديمة كمراجع تاريخية. في حالة تعارض خطة قديمة تقول انتظر الـSaaS أو اشتر Gateway أو انشر داخل /saas/console، تُعتبر متجاوزة. المصدر في GitHub ما زال saas/console لكن **النشر المباشر في document root**؛ تُحفظ مسارات Android والتوافق القديمة عند الضرورة، ولا ترجع لوحة ثانية ولا تهدم الإعدادات الحية.

متطلبات غير قابلة للتغيير ضمن هذا الإصدار: الهاتف وشريحته وباقته الحاليان، بدون شراء Gateway/عتاد/اشتراك اتصالات جديد، بدون LiveKit/Phone Link/Voicemeeter/Shizuku/Accessibility/Root كشرط؛ لا تجربة دفع LIVE بلا sandbox/تفويض؛ لا كشف مفاتيح AI في عميل Android، ولا نخلط مفاتيح Google للـVoice بمفاتيح Assistant؛ شريحتان قد تتبعان شركتين مختلفتين؛ Agent واحد لكل خط ويمكن تغييره بدون إعادة ربط؛ لا ادعاء Handoff خارجي أو تسجيل أو تحويل مالي من حدث لم ينفذ.

**تمييز الإثبات الإلزامي:** SPEC_APPROVED، CODED، CI_PASS، HOST_DEPLOYED، AUTH_UI_VERIFIED، DEVICE_E2E_VERIFIED، PAYMENT_E2E_VERIFIED، BLOCKED. حالة CODED أو ظهور زر أو HTTPS 200 لا تكفي وحدها لـDONE. الملفات القديمة docs/project/project-state.json وdocs/project/acceptance-matrix.json ومقاطع الخطة المؤرخة قبل 3 نوفمبر لا تُستعمل كحالة حالية بلا تجديد.

## 2. الفجوات التي غابت أو لم تُغلق في الخطة السابقة
هذه ليست طلبات جديدة من المساعد؛ معظمها طلبات المالك الأصلية أو قرارات 1–3 نوفمبر، لكنها لم تكن ضمن بوابة تنفيذ مكتملة:
- **المكالمات/Android:** مكالمة SIM صادرة **تبدأ تلقائيًا من جدول الحملة** لا مجرد بدء مكالمة يدويًا ثم إقحام AI؛ التقاط الرد والحالة على الخط؛ 5 ثوانٍ للرد الوارد، استلام الحالية فقط، toggle تشغيل موحد جهاز/سيرفر، اسم ورقم ومدة mm:ss وFloating؛ التهيئة بزر واحد وبدون Wi-Fi بعد إعداد آمن، وقدرة العمل بعد reboot وخارج المتصفح.
- **الأرقام والحملات:** استيراد كلي/محدد للهاتف مرة واحدة + CSV/XLS/XLSX/TXT/لصق؛ تعيين الأعمدة، عدم تلف تنسيق Excel، personalization بالاسم، توقيت محلي/عطلات/دوام متعدد، delay بعد نهاية فعلية، حد محاولات/رد مشغول/عدم رد، وقف واستئناف، أولوية الوارد **لكل خط**، DNC وConsent قبل كل Dial، نتائج وتقارير لكل رقم، واستيعاب قوائم كبيرة ببنية Streaming.
- **AI:** Gemini 3.8 Live Extended Thinking **كنص في مساعد Flow Builder**، Async tool-call حقيقي؛ Prompt يعدّل graph ويعرض diff ثم يطبق بعد موافقة؛ Graph N8N-like منظم، لون أحمر للوَصلات وتفاعل hover وشاشة كاملة؛ تدفق حي يجيب سؤالًا جانبيًا ويعود للعقدة؛ Draft/Test/Publish/Rollback؛ Provider Relay داخلي مفاتيح غير محدودة وأولوية وتبريد 15 دقيقة **لمجموعتين مستقلتين Voice وAssistant**؛ اختبار موديل حقيقي، موديلات مكتوبة يدويًا وCustom HTTPS Endpoint.
- **البيانات والتقارير:** Call History بلا تكرار مع caller name/number وحالة فعلية، تفريغ/ملخص/ملاحظات ورضا/DNC، نوت تحويل موظف، مسار Handoff مثبت، **تسجيل صوت خام إن أتيح وموافقة وحفظ**، تصدير وفلترة تاريخ، إظهار تقدم الحملة والنص/المكالمة الحالية؛ عدم التظاهر بوجود تسجيلات لأن PCM/Transcript موجودان.
- **الحسابات:** Admin/Reseller/User بواجهات مستقلة ولكن **نفس تصميم Desktop على الهاتف مع Sidebar**، رموز Dark/Light، White-label داخل APK والويب، Custom domains دون رفع ملفات، إتاحة تنزيل APK من كل دور، الدخول للدعم كصاحب الحساب باستثناء الأسرار وبموافقة قابلة للتحديد وAudit، صلاحيات دقيقة، 2FA إلزامي، حذف Recycle → Permanent retention قابل للضبط، API copy/reveal/rotate/revoke دون طلب كلمة مرور غير مطلوب.
- **التسعير/الدفع:** إعادة استخدام رقم كل عميل؛ باقات Reseller/User، Premium، عملات متعددة وشهري/سنوي/Trial، connected_calls ledger + overage وحصة الريسيلر، انتهاء/تجديد؛ Kashier TEST ثم LIVE، Manual/Instapay/Wallet بمراجعة، reseller-direct collection أو OnTrack/commission، Refund/Chargeback/Invoice؛ Order claim دون إلحاق عشوائي بالإيميل، Verified SSO من WHMCS/Laravel، outbox signed.
- **المعرفة والقنوات:** تحميل TXT/PDF/Word/URLs وGoogle Drive OAuth وsync، live read-only WHMCS/WordPress/Laravel وREST ثم Dynamic Actions بموافقة؛ WhatsApp Cloud API manual/embedded/coexist/موجود من قبل، Inbox أصلي + إنسان + توقف AI 15 دقيقة + WHMCS AI Support Agent وربط العميل، Web chat، Telegram والسوشيال الرسمي حسب صلاحيات API.
- **الاعتماد:** سياسة Demo داخلية لا عملاء حقيقيين، اختبار جهاز Xiaomi حقيقي و2 SIM/2 tenants، صور وسجلات E2E، رفض اختراق DNC/IDOR/CSRF، خروج أسرار/DB عن public_html، فحص Linux ADB 5555 الأمني، النسخ والـRollback وحراسة النشر ضد اختبارات CI الفاشلة، وعدم تشغيل جدولة مهام توقفها المالك.

## 3. ترتيب التشغيل وقاعدة التفرع
الترتيب التنفيذي يبدأ بـ **G0** لحراسة النشر، ثم **G1** إثبات المكالمة والعزل، ثم **G2/G3** Flow والحملات (يمكن فروع مستقلة دون إطلاق), ثم **G4** التقارير، **G5/G6** الحسابات والدفع، **G7** التكاملات، و**G8** إطلاق تجريبي مضبوط. **G9** مؤجل ولا يسقط من النطاق النهائي. نطاق الإطلاق المكتمل Q01 يشمل الجميع ما عدا البنود المعلنة مؤجلة بعلم المالك.
إذا علقت مهمة لا تستبدلها بشاشة شكلية؛ سجل السبب واختبار الفشل، واشتغل على مهمة مستقلة دون تغيير قرار المالك. لا تغيّر الصوت العامل أو الإيجنت الجاري في مكالمة لتجربة إصدار آخر. بعد كل تعديل: إثبات CI، ثم نشر محمي بإمكانية الرجوع، ثم Authentication UI وdevice/payment E2E حسب النوع، ثم ربط Evidence بـID.

## 4. مراحل التنفيذ والمعايير التفصيلية

### G0 — تثبيت مصدر الحقيقة والنشر والبيانات (P0)
لا يُسمح بالتقدم إلى تجربة مستخدمين خارجية إذا فشل CI أو لم يُعرف الإصدار المثبت

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R001** | P0 | Ops | جزئي/الكود موجود | توحيد main وفرع root المنشور وتحديد SHA واحد قانوني للإصدار؛ منع فرعين يشيران لمنتجين مختلفين. **القبول:** Branch/release manifest متطابق مع هوست وأصول Android وrollback. |
| **R002** | P0 | Ops | فجوة مؤكدة بالكود | إصلاح CI الأحمر واختبار خطة الأسعار بعد إزالة نموذج Google القديم، وربط Deployment بجميع gates الإلزامية. **القبول:** لا نشر على أحمر؛ test_console_pricing_ui وWhatsApp/WHMCS وAndroid gate ناجحة. |
| **R003** | P0 | Ops | جزئي/الكود موجود | تأكيد canonical root app.ontrackegy.com ونقل كل روابط API/SSO/webhooks/mobile والـCron من المسارات التاريخية. **القبول:** مسارات root + legacy smoke مع authenticated flows؛ لا تكرار console. |
| **R004** | P0 | Ops | جزئي/الكود موجود | مراجعة نسخة الباك أب الخاصة، خطة restore لكل نسخة، SQLite/MySQL migrations، تشفير الأسرار والأذونات. **القبول:** restore معزول DB+files، integrity، no plaintext، زمن تعافٍ مقاس. |
| **R005** | P0 | Ops | غير متحقق | إنشاء بيئة اختبار منفصلة ومعايرة Dev/Demo/Internal Prod؛ إبقاء بيانات العملاء التجريبية منفصلة وعدم مسح حالة الهاتف. **القبول:** DB/keys/callbacks معزولة، migrations/rollback على نسخة لا الإنتاج. |
| **R006** | P1 | Ops | غير مكتمل | مراقبة صحة Queue/Cron وerror traces وAI quotas وHTTP وevent outbox والتنبيه والتشخيص. **القبول:** health dashboard/alerts مع test failure وmanual recovery وتعقيم السجلات. |
| **R007** | P1 | Ops | جزئي/الكود موجود | توحيد الحالة الآلية: المتطلبات ↔ الكود ↔ CI ↔ host receipt ↔ AUTH_UI ↔ DEVICE_E2E في ملف واحد. **القبول:** لا انتقال إلى DONE بلا دليل link/commit/run وجهاز/حساب. |

### G1 — الخطوط والتطبيق والمكالمات الحقيقية (P0)
إثبات صوت الوارد والصادر على Xiaomi، SIM1/SIM2 وعزل الحسابات، ثم مصفوفة أجهزة تدريجية

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R010** | P0 | Android/User | بانتظار اختبار هاتف حقيقي | إثبات GSM inbound تلقائي: رنين → delay 5s → answer → صوت AI والمُتصل ثنائي الاتجاه → end. **القبول:** مكالمات فعلية مع call_id وAudio A/B وحالة واضحة، no silent answered call. |
| **R011** | P0 | Android/User | بانتظار اختبار هاتف حقيقي | إثبات GSM outbound من الشريحة مع استشعار رد الطرف الآخر ومنع التحية قبل الإجابة. **القبول:** 20 مكالمة اختبار مصرح بها busy/no-answer/answered ثم تقرير صحيح. |
| **R012** | P0 | Android/User | جزئي/الكود موجود | شريحتان بحسابين، QR + كود مؤقت، إلغاء الربط، تبديل الإيجنت على نفس SIM بلا إعادة ربط. **القبول:** 2 tenants/2 SIM، عدم تسرب token/KB/reports، إعادة الربط بعد revoke ممنوعة. |
| **R013** | P0 | Android/Ops | بانتظار اختبار هاتف حقيقي | إقلاع واستعادة تلقائية بعد reboot/OTA/انقطاع شبكة، دون تشغيل خط أوقفه المستخدم. **القبول:** reboot وfirst-unlock وbattery saver و4G/5G وWi-Fi transitions دون auto-enable غير مقصود. |
| **R014** | P0 | Android/Ops | مخاطرة غير مقاسة | تقييم وتعطيل فتح ADB/TCP 5555 غير الآمن افتراضيًا، وفحص امتيازات OEM والتوافق بدون root/Shizuku/Accessibility. **القبول:** security scan لمنافذ الشبكة؛ دعم فقط للأجهزة المعتمدة؛ لا تغيير لهوية الحزمة. |
| **R015** | P1 | Android/User | جزئي/الكود موجود | Phone Control Center: start/stop sync، الرد الآلي 5 ثوانٍ، agent per line، Emergency Stop، أولوية الوارد أثناء الحملة. **القبول:** save → publish → device ACK، cancel ringing إذا انتهى أو ردّ بشري، لكل SIM سياسة. |
| **R016** | P1 | Android/User | جزئي/الكود موجود | Floating Overlay فوق المكالمة: مدة mm:ss، اسم/رقم، mute، AI/إنسان takeover للحالية فقط، ألوان وأيقونات واضحة. **القبول:** اختبار على Xiaomi مع foreground/background وقفل الشاشة دون Accessibility. |
| **R017** | P1 | Android/User | جزئي/الكود موجود | زر تفعيل الإعدادات المطلوبة وتشخيص الأذونات والـSIM ودعم بيانات الهاتف ومواءمة الجهاز. **القبول:** تشخيص Ready/Degraded/Unsupported لكل ميزة مع أخطاء قابلة للعمل. |
| **R018** | P1 | Android/Ops | جزئي/الكود موجود | OTA الواجهة الموقّع بلا APK، وNative APK بسند توقيع واحد وحفظ ربط الهاتف وموافقة Android. **القبول:** signed manifest/hash، retry، rollback، لا تكرار install، لا silent-native claim. |
| **R019** | P2 | Android/Ops | مؤجل معتمد | توسيع توافق Android تدريجيًا Xiaomi ثم Samsung/OPPO/Realme، وiOS فقط كمشروع مستقل لاحق. **القبول:** device/OEM/carrier capability matrix + accepted audio evidence per class. |

### G2 — AI Runtime وFlow Builder وProvider Relay (P0)
مسودة مبنية بالذكاء الاصطناعي ومراجَعة ونسخة منشورة تعمل في مكالمة حقيقية

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R020** | P0 | Admin/User | جزئي/الكود موجود | Flow Builder فعلي بمخطط n8n-like مرتب وروابط مستقيمة/منحنية، وصلات حمراء افتراضيًا وhover موضعي وشاشة كاملة. **القبول:** graph editing + validation + undo/delete + responsive visual browser check. |
| **R021** | P0 | Admin/User | فشل E2E حقيقي | إصلاح Gemini 3.8 Live Extended Thinking في Assistant text proposal بالـasync jobs، واحترام tool calls وfallback. **القبول:** إنشاء مخطط صالح في Live E2E وليس مجرد WS connected؛ fallback عند 429/503/timeout. |
| **R022** | P0 | Admin | جزئي/الكود موجود | Relay صوت وAssistant منفصلان داخل Admin، مفاتيح وموديلات Google/Custom غير محدودة وأولوية و15m cooldown. **القبول:** 2 keys + 2 providers real test، تحريك الدور وتبريد 900s، عدم تسرب secret، اختبار model توليد حقيقي. |
| **R023** | P0 | User/Android | غير متحقق | محرك Flow أثناء المكالمة: شروط/خطوات/أسئلة ومتغيرات وحالات branch؛ يجيب الجانبي ويعود لنقطة التوقف. **القبول:** call trace node-by-node، مقاطعات 5 مرات، لا يعيد الترحيب ولا يقفز للأسئلة. |
| **R024** | P0 | User | جزئي/الكود موجود | Draft → Test → Publish → Rollback + إصدارات Immutable وAgent per SIM، عدم تبديل مكالمة جارية. **القبول:** rollback/activate verified + pinned revision حتى end + Browser/Android ACK. |
| **R025** | P1 | User/Admin | جزئي/الكود موجود | المساعد داخل Builder يقرأ المخطط ويبنيه ويعدله بالأوامر ويقترح لكن لا ينشر دون تأكيد. **القبول:** chat generates verified graph, diff preview/reject/approve، لا overwrite على stale job. |
| **R026** | P1 | User | جزئي/الكود موجود | قوالب سيناريو مبيعات عقارية وغيرها، متغير اسم العميل، إرشادات مهام متعددة، policy عربي مصري/EN. **القبول:** dynamic placeholders validated + natural barge-in/turn taking + no invented facts. |
| **R027** | P1 | Admin | غير مكتمل | اختيار Provider/Model لكل وظيفة ودور والـreviewer/writer وسياسات fallback/usage/latency/expenses دون كشف مفاتيح AI للمستخدم. **القبول:** real test per model, role routing, cost counters, quota exhausted, audit. |
| **R028** | P2 | Admin/Reseller/User | غير متحقق | كتالوج أصوات Google الكاملة + توزيع الجاهز، وأصوات AYO Premium بعد حقوق ومحرك Chatterbox فعلي. **القبول:** Live voice sample + TTS WAV + Arabic quality E2E قبل ready/grant/billing. |

### G3 — الحملات وقوائم الأرقام والجدولة (P0)
اتصال آلي آمن من شريحة حقيقية مع موافقة وDNC وطابور مسترد لا يكرر الاتصال

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R030** | P0 | Android/User | غير مكتمل | عامل اتصال campaign على SIM من قائمة وlease موثوق، تنفيذ dial الفعلي بعد فحص DNC/consent. **القبول:** حملة 10 أرقام تبدأ آليًا واحدة تلو الأخرى دون طلب تدخل UI أو Accessibility. |
| **R031** | P0 | User | جزئي/الكود موجود | استيراد الهاتف كامل/محدد مرة واحدة + CSV/XLSX/XLS/TXT/ألصق، أسماء وحقول وأرقام موحّدة دون تلف Excel. **القبول:** preview/mapping/errors/dupes/2k batches/resume، importer لا يكرر ولا يفتح DNC. |
| **R032** | P0 | Android/User | جزئي/الكود موجود | DNC ledger عبر المواقع والقنوات وConsent مع opt-out فوري قبل كل Dial ومحاولات لاحقة. **القبول:** DNC from call report stops all future numbers; no silent re-consent/delete bypass. |
| **R033** | P0 | Android/User | غير مكتمل | جدولة حسب المنطقة الزمنية، فترات الدوام المتعددة، الإجازات، العطلات، استثناءات، سرعة وعدد محاولات. **القبول:** before lease/pre-dial gate تحت تعارض، daylight saving، paus/resume، max attempts. |
| **R034** | P0 | Android/User | غير مكتمل | delay بعد نهاية حقيقية، busy/no_answer/retry/followup/backoff/next؛ recovery بلا إعادة اتصال مجهولة. **القبول:** device survives process death; unique idempotency, uncertain call reconcile, no blind redial. |
| **R035** | P1 | Android/User | غير مكتمل | سياسة incoming أثناء campaign لكل خط وتوقف حجز الصادر عند الرنين والعودة بعد فحص. **القبول:** SIM1/SIM2 simultaneous calls isolated؛ cancellation rules documented and tested. |
| **R036** | P1 | User | غير مكتمل | تخصيص الإيجنت والحملة بالاسم والمتغيرات وتقرير نتيجة لكل رقم وجدولة المتابعة المصرّح بها. **القبول:** DNC/conflict consent gating, per-target outcome and optional next followup. |
| **R037** | P2 | Ops/User | غير مكتمل | اختبارات سعة آمنة من 10 حتى قوائم ضخمة (مليون رقم هدف سعة لا تصريح بإجراء مليون مكالمة). **القبول:** import streaming, pagination, job fairness, back-pressure، قيود التكاليف والمشغل. |

### G4 — السجلات والتقارير والتصعيد والتسجيل (P0)
كل مكالمة تُسجَّل كواقعة واحدة، والتفريغ/التقرير/التصعيد/التسجيل له أدلة وصلاحيات

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R040** | P0 | User/Android | جزئي/الكود موجود | Call History: call_id واحد، states/timestamps start/end، answered/missed/busy، مدة ودقة الرقم والاسم. **القبول:** idempotent event retry/ordering، device vs server reconciled، لا duplicate على انقطاع. |
| **R041** | P0 | User | جزئي/الكود موجود | AI call summary + notes + satisfaction/outcome/DNC + request for human, مع تعديل موظف وإثبات المصدر. **القبول:** speech transcript → real model structured report → audit، لا report وهمي، retries بلا إعادة call. |
| **R042** | P0 | Android/User | غير مكتمل | تسجيل صوت المكالمة الفعلي مع consent/notice، حفظ وتشفير وسياسة retention وتصدير وصلاحيات. **القبول:** dual-channel recording E2E، consent proof، encryption and restore/delete policies. |
| **R043** | P1 | User | جزئي/الكود موجود | تصعيد للمسؤول: AI يوقف، الهاتف يُستلم للحالية فقط؛ تحويل خارجي إن أثبتت القدرة؛ لا يدعي تمّ. **القبول:** handoff_requested ≠ completed، UI/timestamp staff acceptance، external transfer gated. |
| **R044** | P1 | Admin/Reseller/User | جزئي/الكود موجود | سجل بحث وفلترة رقم/اسم/حالة/تاريخ/خط/إيجنت وحملات + XLSX/CSV exports مرخصة. **القبول:** correct pagination, filters, privacy masking, same tenant. |
| **R045** | P1 | Admin/User | غير مكتمل | مراقبة جودة الصوت/الكمون والردود المنقطعة واللغة والمقاطعات ورضا العملاء. **القبول:** call QoS metrics with opt-in redacted traces, no recording claim. |

### G5 — حسابات Admin/Reseller/User وواجهة المنتج (P0)
صلاحيات وعزل وواجهة عربية موحّدة وWhite-label ومركز عمليات تعمل لا مجرد روابط

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R050** | P0 | Admin/Reseller/User | جزئي/الكود موجود | مصفوفة RBAC شاملة، دعم كامل عدا الأسرار الحساسة، سياسات موافقة حسب الشركة وaudit actor. **القبول:** negative cross-tenant across admin/reseller/user/support + sensitive ops audit. |
| **R051** | P0 | Admin/Reseller/User | جزئي/الكود موجود | 2FA إلزامي للإدارة، تسجيل ذاتي مؤكد البريد، دعوات الموظفين وإلغاءها. **القبول:** no privileged login before TOTP enrollment، recovery secure, real email delivery. |
| **R052** | P0 | Admin/Reseller/User | غير مكتمل | Recycle Bin لكل العناصر والحذف النهائي والمدة وصلاحيات granular، مع إبقاء المالي والتدقيق. **القبول:** trash→restore→retention purge with legal hold and audit; no direct irreversible UI. |
| **R053** | P0 | Admin/Reseller/User | جزئي/الكود موجود | واجهة RTL عربية Responsive بشكل Desktop مع Sidebar موبايل، Light/Dark icons، بدون القديمة أو شاشات متضاربة. **القبول:** Android phone/desktop 3-role screenshots + functional full-screen flow. |
| **R054** | P1 | Admin | جزئي/الكود موجود | لوحة إحصاءات صحيحة للأجهزة/الريسيلرز/المكالمات/الأرصدة؛ إدارة الحسابات/الخطط/التكاملات/الأصوات/التحديثات. **القبول:** KPIs reconcile ledgers and device evidence; suspend propagates to new sessions. |
| **R055** | P1 | Reseller | جزئي/الكود موجود | White-label في الويب وAndroid: شعار/اسم متعدد السمات، تخصيص هوية البريد والرسائل وروابط التنزيل. **القبول:** linked reseller tenant app shows no OnTrack except legally mandated notices; role scope verified. |
| **R056** | P1 | Reseller | غير مكتمل | Custom-domain mapped DNS TXT + virtual host/TLS + cookies/SSO بدون رفع ملفات لدى الريسيلر. **القبول:** domain active only after TXT + certificate + HTTPS working; tenant isolation. |
| **R057** | P1 | User | جزئي/الكود موجود | إدارة خطوط متعددة ومواقع وإيجنتات وفريق وAPI والحملات/المكالمات من Workspace واحد. **القبول:** line-based permission/agent switch, no pilot/console split, auth E2E. |
| **R058** | P1 | Admin/Reseller/User | جزئي/الكود موجود | API keys قابلة للنسخ المتأخر/الدوران/الإلغاء؛ رسائل واضحة وخالية من طلب كلمة مرور وهمية؛ Audit. **القبول:** verify key cryptography & access after rotation; no session secrets in client logs. |

### G6 — الفوترة والاشتراكات والهوية والتكامل التجاري (P0)
دورة Order → Verified Payment → Claim/SSO → Entitlement → Renewal/Refund بلا تكرار

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R060** | P0 | Admin/Reseller/User | فجوة مؤكدة بالكود | تصحيح إنشاء حساب باشتراك نشط تلقائيًا دون تحقق payment/trial، وفصل pending vs demo vs paid. **القبول:** new account no unverified paid entitlement; explicit trial policy and denial tests. |
| **R061** | P0 | Admin/Reseller/User | جزئي/الكود موجود | محاسبة usage: answered connected calls idempotent per customer + reseller pool، overage/reservations/no_answer policy. **القبول:** reconcile usage_ledger vs calls real, replay, concurrency, period boundary, exhaustion stop. |
| **R062** | P0 | Admin/Reseller/User | غير مكتمل | عملات متعددة، شهري/سنوي/تجريبي، تواريخ بداية/نهاية، renewal grace/expired, suspension. **القبول:** no expired subscription treated active، timezone monthly reset، rollover rules explicit. |
| **R063** | P0 | Admin/Reseller/User | جزئي/الكود موجود | Kashier sandbox → signed paid webhook → idempotent provisioning، اختبار مدفوعات حقيقية في TEST فقط. **القبول:** sandbox charge/timeout/replay/amount mismatch/refund E2E, no success-url activation. |
| **R064** | P0 | Admin/Reseller | غير مكتمل | Manual/Instapay/Wallet proof+review مع مسار Refund/Chargeback/Cancel وتسوية وإشعارات. **القبول:** maker-checker approval, duplicate reference blocked, reversal tested. |
| **R065** | P0 | Admin/Reseller | غير مكتمل | ريسيلر Merchant-of-record OnTrack أو مباشر/عمولة، خططه وأسعاره الخاصة وفواتيره/أرباحه. **القبول:** merchant ownership and settlement immutable per order, approval/accounting E2E. |
| **R066** | P0 | Admin/Reseller/User | جزئي/الكود موجود | Orders من WHMCS/Laravel/Reseller/OnTrack مع pending_claim وemail proof وsigned SSO. **القبول:** existing email collision/forged subject/replayed jti denied; no duplicate account/service. |
| **R067** | P1 | Ops/Reseller | جزئي/الكود موجود | outbox retry والتغذية الراجعة نحو WHMCS/Laravel والاستحقاقات والاشتراكات/الفواتير المتسقة. **القبول:** provider ack + retries/dedup/poison queue، source-of-truth per integration. |
| **R068** | P1 | Admin/Reseller/User | غير مكتمل | التجديدات والإشعارات والفواتير وحالات التأخر والامتثال والضريبة حسب السوق. **القبول:** subscription lifecycle tests, customer visible accurate balance/invoices. |

### G7 — القنوات والتكاملات والمعرفة (P1)
WhatsApp وWHMCS وLaravel وWordPress وGoogle Drive مع تمكين تدريجي وE2E

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R070** | P0 | Admin/User | جزئي/الكود موجود | WhatsApp Meta Platform Admin config: manual, embedded signup, coexistence, existing number relay، صلاحيات وتشخيص. **القبول:** actual inbound webhook + outbound reply + no duplicate callback + owner verified. |
| **R071** | P1 | User | جزئي/الكود موجود | WhatsApp Inbox حقيقي، AI Agent حسب القناة، موظف يرد فتتوقف AI 15 دقيقة، حالات delivery. **القبول:** two real msgs, human lock, official 24h window, visible non-delivery, dedup. |
| **R072** | P1 | Admin/User | جزئي/الكود موجود | WHMCS AI Support Agent reviewed replies، ربط حساب العميل بعد تحقق، human escalation/note. **القبول:** E2E WHMCS ticket context, reviewer approved, outgoing Meta delivery, no secret leak. |
| **R073** | P1 | User | جزئي/الكود موجود | Connector معرفة read-only لـWHMCS/Laravel/WordPress/REST + signed facts وأفعال بعد تحقق/تصريح فقط. **القبول:** tenant permission, real integration test, API timeouts & truthful unavailable responses. |
| **R074** | P1 | User | غير مكتمل | Knowledge Base متعددة المصادر TXT/PDF/DOCX/URLs/Google Drive OAuth, versions/sync/delete. **القبول:** sync/revoke/refresh E2E with cross-site ACL, parse error and source citations. |
| **R075** | P1 | Admin/User | غير مكتمل | Web chat/Website widget وTelegram وFacebook/Instagram/other official-channel connectors حسب القدرات. **القبول:** per-channel connect/ownership/scope/message/handoff/callback E2E؛ لا دعم منصات مزعوم. |
| **R076** | P1 | User | غير مكتمل | Dynamic contextual tool actions CRM lookup/write, booking, ticket, approvals, verified caller identity. **القبول:** never run voice-requested sensitive action without auth/confirmed preview/audit. |

### G8 — الجاهزية والأمن والتحقق والانتشار (P1)
اختبارات تشغيل ومراقبة واحتفاظ ونسخ دورية واعتماد الإصدار قبل أي عملاء حقيقيين

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R080** | P0 | Ops | غير مكتمل | Auth UI E2E لكل Role وصلاحيات ضد IDOR والـCSRF وXSS، browser/tablet/mobile matrix. **القبول:** automated tests with isolated accounts; no privileged action from forged/foreign role. |
| **R081** | P0 | Ops | غير مكتمل | حزمة E2E من التسجيل/الدفع/الربط/الوارد/الصادر/التقرير/الحملة/WhatsApp. **القبول:** checklist with run id / account / SIM / time / result / failure and replay. |
| **R082** | P0 | Ops | غير مكتمل | تدقيق أمان إتاحة الصوت والملفات وأسرار Google والـADB وموافقة الاتصال والاحتفاظ. **القبول:** no public private files, no cross-tenant bearer, consent & OTP, external pentest before release. |
| **R083** | P1 | Ops | غير مكتمل | Metric/alert: success by SIM/provider, duration, network drops, queue lag, payment fails, backup age. **القبول:** alerts tested with injected failure and on-call recovery playbook. |
| **R084** | P1 | Ops | غير مكتمل | اختبار rollout على هوست Production داخليًا ثم Beta خارجي مصرح به فقط ثم Live. **القبول:** signed internal signoff checklist, migration restore, support SOP, abuse protection. |
| **R085** | P1 | Ops | غير مكتمل | الحدود والخصوصية: DNC re-consent approval وTrash retention وaudio/data deletion مع audit. **القبول:** documented policy configuration and negative access tests. |

### G9 — الميزات المؤجلة المعتمدة (P2)
دعم شبكات ضعيفة أكثر، مصادر اجتماعية إضافية، أوامر المدير الصوتية وiOS حسب القيود

| ID | الأولوية | النطاق | الوضع عند المراجعة | المطلوب ومعيار الإغلاق |
|---|---|---|---|---|
| **R090** | P2 | Android/Ops | مؤجل معتمد | شبكات 4G/5G ضعيفة: reconnect/sessionResumption/drop handling وQoS benchmarking أوسع. **القبول:** measure interruption rates and recover without false transcript or re-dial. |
| **R091** | P2 | User | مؤجل معتمد | متصل المدير: تحقق هوية يتجاوز Caller ID، قراءة بيانات مصرح بها وأوامر آمنة مع Audit. **القبول:** verified authenticated voice actions tested; high-risk writes disabled without confirmation. |
| **R092** | P2 | Admin/User | مؤجل معتمد | أصوات Premium AYO Live وiOS وقنوات اجتماعية إضافية بعد فحص الأهلية والحقوق. **القبول:** controlled research/PoC before product listing or pricing. |

## 5. تعقب المتطلبات الأصلية
- **OV-001..OV-013** (القرارات القديمة/المستمرّة): واجهة Responsive وSupport وTrash وFlow وDevice/Multi-SIM والقيود التقنية وOTA وDNC؛ موزعة على G1–G5 وG8؛ كلها **مطلوبة** وغير مكتملة تشغيليًا.
- **Q01–Q24** (قرارات 1 نوفمبر كلها معتمدة؛ لا تُعاد كأسئلة مفتوحة): Q01/Q23/Q24 في G0/G8، Q02–Q04 في G5/G6، Q05–Q11 في G1/G4، Q12–Q13 في G3/G4/G9، Q14–Q16 في G2/G7، Q17–Q18 في G3، Q19–Q22 في G6.
- **V-001..V-008** (متطلبات الصوت الأصلية): الوارد والصادر والعزل والتشغيل المستقل والمصرية/المهام والقناة الحقيقية في G1/G2/G3؛ تأجيل إدارة المدير موثّق في G9.
- **باقي المتطلبات الأصلية:** Phone Control Center في G1؛ Agent Builder/knowledge/whatsapp في G2/G7؛ أسماء/أرقام وتقارير المكالمات في G4؛ الأصول Premium/White-label في G2/G5؛ قنوات social/Telegram في G7/G9. حالة كل بند محفوظة أيضًا بالمصفوفة الآلية في نفس المجلد.
- **تعارضات مع ملفات قديمة:** النص الذي يطلب Laravel Runtime أو Gateway/هاردوير أو نشرًا ضمن /saas/console أو Bottom Navigation للموبايل متجاوز بالقرارات الأحدث. النص الذي يصف Q01–Q24 OPEN متجاوز؛ النص الذي يصف المشروع جاهزًا بسبب CI وحده غير معتمد.
- **الجدولة:** طلب المالك السابق متابعة تنفيذ كل ساعة، ثم أمر صريح **بإيقافها في 2026-11-02**. هذه الخطة لا تُشغّل أو تعيد تفعيل Automations/Cron تطوير تلقائي؛ تشغيل الـCron التشغيلي اللازم للمنتج يُدار كوظيفة مستقلة عند الحاجة للتحقق فقط.

## 6. تعريف DONE وقاعدة الموافقة على المرحلة
1. **SPEC:** المهمة تربط إلى Q/OV/V أو طلب المالك بتاريخ محدد؛ لا افتراض قرار مالي/قانوني.
2. **CODE + UNIT:** تنفيذ حقيقي backend/client؛ واجهة بلا API ليست اكتمالًا؛ اختبارات سلبية duplicate/invalid/foreign.
3. **CI:** كل الاختبارات المطلوبة ناجحة، ولا يجوز أن ينشر workflow منفصل نسخة فشلت اختبارات الواجهة أو حماية المستخدم.
4. **HOST:** release hash/receipt وbackups/recovery صالحة؛ migration reversible؛ الدومين canonical والمتوافقة.
5. **AUTH_UI:** تسجيل دخول الدور المحدد مع screenshots+actions؛ ليس مجرد response 200.
6. **DEVICE E2E (الصوت والحملات):** Xiaomi حقيقي، وارد/صادر وSIM1/SIM2، A/B audio، أسماء/أرقام، report IDs، حالات فشل/reboot؛ لا يُستبدل بMock أو Google token.
7. **PAYMENT/CHANNEL E2E:** مزوّد Sandbox أو قناة Meta حقيقية بتصريح وتوقيعات/فواتير/تأكيدات ووصول؛ لا تُعامل مجرد webhook synthetic كمعاملة حقيقية.
8. **RELEASE:** اعتماد مالك داخلي للبيئة، ثم Beta خارجي بموافقة مستقلة، ثم Commercial Live بعد إكمال Q01؛ التوسع غير تلقائي.

## 7. أدوار التنفيذ والمخاطر
- **P0:** قابلية توقف الشغل أو انقطاع الصوت أو دفع خاطئ أو تسرب tenant/secret؛ تُصلح قبل إضافة UI جديد.
- **P1:** نقص جزء مفيد أو ضعف UX أو تكامل يحتاج إثبات؛ يُنجز بعد تثبيت خط التشغيل.
- **P2:** Phase لاحقة مطلوبة لكن لا تفتح Gates غير مثبتة؛ توضع في backlog ولا تضيع.
- لا تتخذ إرسال رسائل واتساب، مكالمة رقم آخر، قبض أموال فعلية، نقل نطاق أو حذف نهائي أو تفعيل صلاحية تقنية خطرة كاختبار تلقائي.
- داخل مشغل الهاتف، وضع TCP 5555 / embedded ADB يحتاج قياس أمان وموافقة الجهاز؛ لا تعتمد أمانًا لم يثبت. لا تفرض حلولًا رفضها المالك لتجاوز قيد Android؛ إذا استحال على جهاز محدد يبقى Unsupported بتقرير فني.
- واجهة Admin قد تعمل لمالكها؛ فشل وصول الأداة إليه لا يساوي فشل تسجيل الدخول الفعلي.

## 8. طريقة تحديث هذه الماب
عند إنجاز أي Rxxx: حدّث status بالمصفوفة، تاريخ الفحص، SHA، CI/run، host receipt، دور/حساب اختبار، جهاز/Carrier إن وجد، مخرجات PASS/FAIL مختصرة وروابط، ثم حدّث سطر القرار هنا فقط إذا تغيّر النطاق. إن لم يوجد دليل غيّرها إلى BLOCKED أو PARTIAL لا DONE. لا تعِد تقريرًا يثبت «جميع المزايا» بالاعتماد على ملف مجمّع. ابدأ أولًا بـR001/R002 (توحيد مصدر ونشر آمن) وR010–R012 (صوت وعزل SIM) بالتوازي مع تصحيح R060/R061 عند بناء التجربة التجارية.

### مراجع المصدر
- docs/discovery/OWNER_APPROVED_MASTER_DECISIONS_2026_11_01_AR.md
- docs/project/REQUIREMENTS_AR.md
- docs/architecture/PHONE_CONTROL_CENTER_SPEC_AR.md
- docs/plan/VOICE_SAAS_CONSOLE_STRUCTURE_AR.md
- docs/plan/VOICE_SAAS_RESELLER_DOMAINS_AGENTS_ACCESS_AR.md
- docs/voice/PREMIUM_ASSETS_AND_BILLING_AR.md
- docs/execution/FLOW_AI_ASYNC_2026_11_03_AR.md (فرع root عند المراجعة)
- docs/execution/ROOT_AI_RELAY_RELEASE_2026_11_03_AR.md (فرع root عند المراجعة)
- GitHub Actions: 41577865384 FAIL (console regression) مقابل 41577865411 SUCCESS (root deploy)
