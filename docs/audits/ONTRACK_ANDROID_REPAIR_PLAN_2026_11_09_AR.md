# OnTrack Voice — خطة إصلاح تطبيق Android فقط مع ضمان الحفاظ على الصوت والخصائص العاملة
**التاريخ:** 2026-11-09 | **النوع:** خطة تنفيذ مستقلة / PLANNING ONLY | **الحالة:** TODO / NOT IMPLEMENTED  
**المستودع:** `OnTrack-Development/OnTrack-voice`  
**قاعدة المقارنة:** `release/voice-v1-20261106` + أحدث APK وUI OTA مثبتين فعليًا على هاتف المالك (يُتحقق من SHA/Version قبل كل تعديل).  
**المرجع الأساسي:** [التقرير المجمّع](ONTRACK_CONSOLIDATED_ZERO_ASSUMPTION_AUDIT_2026_11_09_AR.md)؛ ويُنسق مع [خطة الـSaaS](ONTRACK_SAAS_REPAIR_PLAN_2026_11_09_AR.md) في عقود API فقط.  
**النطاق المسموح:** Native Android Kotlin/Java + Audio/Telephony/PCM + Foreground services + UI Bridge + Permissions + OTA/APK + diagnostics/CI الخاصة بالجوال.  
**خارج النطاق:** تغيير منطق الباقات/المستخدمين/قاعدة SaaS أو API عام بصورة كاسرة، النشر على الهوست أو تثبيت APK ضمن مهمة كتابة هذه الخطة، مراجعة/فتح/تعديل `VS/`.

> ## شرط مالك المشروع الحاكم — NO REGRESSION
> **ممنوع قطع أي مسار صوت أو مكالمة أو إخفاء أي زر/ميزة/خيار قائم ومؤكد، أو تغيير الشريحة/الإيجنت/المفتاح/التوقيع/التوكن أثناء العلاج من غير اختبار كامل وتخطيط رجوع.** الإصلاحات تكون **داخل البنية العاملة وبأقل تغيير**؛ يُقارن كل تعديل بحالة الهاتف التي أثبتها المالك، لا بالتقرير القديم الذي لم يختبر الجهاز. أي ميزة محسّنة يجب ألا تُسقط خاصية سبق نجاحها على Wi-Fi أو على SIM أو داخل OTA.

---

## 0. خط أساس الهاتف — حافظ على ما ثبت قبل فتح أي Bug

### 0.1 السلوكيات المحمية بناءً على توثيق المالك
- **الربط الحالي:** شريحة المالك المربوطة بـAdmin ما زالت مربوطة؛ **لا Unpair ولا حذف بيانات التطبيق ولا إعادة توليد installation_id/token** من أجل إصلاحات جانبية.
- **المكالمات الواردة:** Auto-answer وكتم الميكروفون سبق أن عملا على هاتف المالك؛ **لا نعيد كتابة المسار من الصفر** ولا نفترض أنهما FAIL دون تجربة حديثة.
- **صوت المكالمة عبر Wi-Fi:** كان يعمل بجودة جيدة ويستقبل/يرسل صوت الطرفين؛ **ممنوع تغيير channel MIX أو تعطيل CaptureDaemon/PCM working path** لمجرد تحسين 4G.
- **الموبايل داتا LTE/4G:** توجد بلاغات latency وتراكم صوت وأحيانًا صوت غير مسموع تاريخيًا؛ المطلوب عزل سبب الشبكة وتخفيف البافرات، **لا كسر Wi-Fi**.
- **تبديل الإيجنت/الصوت والسياسة:** يظل SIM المربوط يقرأ إعداداته من حساب SaaS؛ لا تسقط `tenant enrollment` أو تستبدله بمفتاح Pilot.
- **الواجهة/التحكم:** Overlay الخاص بالنظام، أزرار تشغيل/إيقاف/استلام بشري/Speaker، شاشة الربط بكود 6 أرقام، SIM1/2، تحديثات UI OTA المنفصلة عن Native APK، اختيار الوضع Light/Dark، سجل المكالمات/التقارير، Contacts/CSV والحملات حيث تكون مصرحًا بها — **كل زر مستخدم يجب أن يستمر في تنفيذ فعل حقيقي**.
- **التجارب الداخلية فقط:** لا مكالمات صادرة على جهات حقيقية أو حملات جماعية بسبب اختبار، ولا تعطيل موافقة المستخدم، ولا تشغيل صلاحيات Debug في الخلفية دون علمه.
- **مهم:** هذه وقائع **مالك/توثيق تاريخية** لا تشكل شهادة نجاح جديدة على جميع الأجهزة. كل شيء يبقى `OWNER_CONFIRMED_HISTORIC` حتى اختبار الهاتف بعد الإصلاح؛ تمييز `UNVERIFIED` عن `FAIL` إلزامي.

### 0.2 بوابة ما قبل التغيير
- [ ] اجمع من الهاتف نفسه Model/OEM/Android version، رقم Native `versionCode`، UI `revision`، package+certificate fingerprint، SHA APK/OTA، SIM-slot mapping (دون طباعة الأرقام)، وحالة Enable/Mute/Takeover والتصاريح.
- [ ] احفظ **نسخة قابلة للاسترجاع** من APK الموقع وMetadata والـUI OTA والتوقيع المعتمد وسجلات محمية، وراجع أن الـRollback يعمل **من دون خفض versionCode أو تعطيل جهاز مقترن**؛ لو نزول الإصدار غير مسموح من Android، جهز forward-fix موقّع بنفس الهوية ورقم إصدار أعلى.
- [ ] وثّق E2E قبل التعديل: مكالمة واردة على Wi-Fi + مكالمة على 4G + اتصال صادر (بموافقة واختبار آمن)، التوقيت، speech uplink/downlink، auto-answer delay، mute/unmute، human takeover، hangup، reconnect، identity scope.
- [ ] اختبر أزرار UI/Bridge الحالية ومقارنة الـHTML مع Kotlin receiver بأوامر `ready/tenantMenu/tenantPairSlot/tenantQrScan/tenantRefresh/tenantTest/start/stop/takeover/speaker/checkUpdates/installNativeUpdate` وغيرها؛ لا تزيل أمرًا قبل تحديد عملائه.
- [ ] اقرأ source وManifest **المُدمج من البناء (Merged Manifest)**، وليس patch النصي وحده؛ التزم بإصدارات Android الحقيقية وأدوار الاتصال المسموحة.
- [ ] قسّم التعديلات إلى مراحل صغيرة وآمنة بFeature Flags وتجنّب تغييرات متزامنة في audio engine + signing + pairing + OTA.

**قاعدة التنفيذ:** `REPRO & BASELINE → ONE SUBSYSTEM PATCH → UNIT/EMULATOR → ANDROID BUILD/SIGNING → INSTALL ON OWNER TEST DEVICE → REAL SIM CALL (CONSENTED) → OWNER ACCEPTANCE → RELEASE`. لا دمج/نشر إذا اختبارات Wi-Fi/Auto-answer/Mute/Pairing الواقعية تراجعت.

---

## Phase APP-1 — تثبيت المحرك العامل واكتشاف أسباب التراجع

| ID | المهمة | المصدر المستهدف | طريقة الإصلاح والتحقق دون كسر الميزات |
|---|---|---|---|
| APP-01 (P0) | تثبيت «مسار الصوت الذهبي» على Wi-Fi | `MobileLinkService.kt`، `MobileLiveCall.kt`، `CaptureDaemon`، `LivePcm.java`، `LinkPcm.kt` | اختبارات PCM fixtures وفحص أن الصوت الوارد من الطرف الآخر والنازل إليه باقٍ؛ تجميد قيم MIX وsample layout/format المستعملة إلى حين المقارنة؛ لا استبدال محرك ناجح بAudioRecord افتراضي لا يلتقط SIM. |
| APP-02 (P0) | تشخيص LTE/4G وتراكم البافرات | `MobileLiveCall.kt`، `MobileLinkService.kt`، `LiveAudioQueuePolicy` حيث توجد | Stage timings: carrier answered، mute acknowledged، PCM ready، token ready، WebSocket setup، first user/audio frame، first agent audio، playback backlog؛ قياسات Wi-Fi/4G/4G أثناء المكالمة؛ Drop stale frames فقط، backpressure وحدود jitter؛ لا إسقاط بيانات مكالمة أو قطع socket دون سبب. |
| APP-03 (P0) | تصحيح بدء الحديث وكشف رد الطرف الآخر في المكالمات الصادرة | Telephony monitor + `MobileLiveCall.kt` وCampaign bridge | لا Greeting قبل `active/remote speech confirmed`، التمييز بين ringing/dialing/offhook/active حسب ما يسمح به الـOS؛ failed/no-answer ليس completed؛ **حافظ على auto-answer الوارد الذي ثبت عمله**. |
| APP-04 (P0) | كتم/استلام المكالمة والـHandoff | `MobileLinkService.kt`، `CallOverlay.kt`، `CaptureDaemon` | لا إعلان “استلمت” إلا عند توقف AI/تحقق الاستلام؛ استلام الإنسان ينقل المكالمة الحالية فقط ولا يغير policy التالية؛ عند فشل mute عرض خطأ فعلي، لا fake green؛ اختبارات مكالمات صوتية حقيقية. |
| APP-05 (P1) | تحسين PCM resampling دون تغيير هوية القناة | `LinkPcm.kt`، unit tests | Low-pass وinterpolation/resampler بعد baseline spectrum/latency؛ احتفظ بخيار `mix` العامل، لا تبديل افتراضي إلى right/left؛ A/B tests بقياسات تشويه وزمن، rollback فوري إذا انخفض الفهم أو الصوت. |
| APP-06 (P1) | تحضير Live Session مبكرًا بلا أفعال قبل الرد | `MobileLiveCall.kt`، `MobileLinkService.kt` | Preconnect/Token cache محدود أثناء ringing إن كان آمنًا، لا Talk ولا Audio replay قبل الرد؛ منع صمت 25s دون التضحية بSession tenant أو تكرار التكلفة؛ تجربة فعلية latency وTTL. |
| APP-07 (P1) | Provider failover أثناء مكالمة بدون ازدواج | Android Live session + `TenantVoiceSession.php` contract فقط | الحفاظ على Agent/tenant/SIM/voice/context، limit retry وcooldown على 429/503/timeout، لا تشغيل صوتين متزامنين؛ لا مسح Transcript عند التبديل؛ نجاح CI لا يساوي E2E. |

**DoD APP-1:** لا انخفاض في جودة/صوت Wi-Fi، auto-answer/mute يعملان على نفس جهاز المالك، LTE latency تتحسن بالقياس وتقرير القياسات حقيقي، outgoing لا يبدأ الحديث فوق الرنين، وOverlay لا يكذب عن الاستلام.

---

## Phase APP-2 — الشريحتان، الحالات، استدامة الخلفية وسجل المكالمات

| ID | المهمة | المصدر المستهدف | معايير القبول والـCompatibility |
|---|---|---|---|
| APP-08 (P0) | Tenant/Device pairing بدون فقد الربط القديم | `TenantDeviceEnrollment.kt`، `MobileLinkActivity.kt`، `MobileLinkService.kt` | 6-digit/QR -> Binding لكل SIM -> secure storage -> heartbeat؛ pairing القديم محفوظ، عدم كشف Bearer داخل WebView/Log، لا Pilot fallback يخلط حسابات، disable/revoke يخدم الشريحة المعنية. |
| APP-09 (P1) | SIM1/SIM2 واختيار شريحة الاتصال الصادر | `MobileLinkService.kt`، PhoneAccountHandle/SubscriptionManager APIs | Dual-SIM cross-tenant protected، outgoing يستخدم PhoneAccount فعليًا إن سمح النظام/الدور؛ لا افتراض أن TelephonyManager global Q يعرف SIM، لا خلط Calls/Audio. |
| APP-10 (P1) | حالات Call الدقيقة وCall ID | `MobileLinkService.kt`، `CallEventOutbox.kt` | incoming/outgoing/no-answer/busy/rejected/completed/human، timestamps، SIM-slot، direction، immutable external ID؛ لا حساب مكالمة قبل `Call.STATE_ACTIVE` عند توفر InCallService. |
| APP-11 (P1) | منع ضياع Outbox أو تعطيل monitor | `CallEventOutbox.kt:41,73,94,118,235` | استبدال فشل `check(pending.length()<1000)` بسياسة تخزين/دوران آمنة وقابلة للاستعادة؛ اختبار >1000 حدث، Offline 30 دقيقة، ID matching لمكالمات متقاربة، عدم duplicated reports؛ **عدم حذف أحداث مدققة صامتًا**. |
| APP-12 (P1) | Foreground service/lifecycle/permissions | `prepare.py`، Manifest merged، `MobileLinkService.kt`، `MobileLinkActivity.kt` | Runtime permissions + Foreground Service types مطابقة SDK/role، اختبار screen-off 60s/30min، service recovery بعد Android battery kill، لا إيقاف يدوي بواسطة تحديث UI؛ لا طلب جميع الصلاحيات دفعة واحدة. |
| APP-13 (P1) | Overlay والأزرار الصادقة | `CallOverlay.kt`، `OverlayUiModel.kt`، `MobileLinkActivity.kt` | الحالات Green/Red/Active/Human/Disconnected تعكس الحقيقة؛ Takeover/Speaker/Enable/Disable/Open App تنفذ أو تعرض فشل واضح؛ نفس الأيقونة وتابّات الواجهة لا تختفي من OTA. |
| APP-14 (P1) | فصل Campaign dial عن حلقة الصوت | Campaign modules + Tenant campaign API | network tick في worker مستقل، timeout/cooldown، lease expiry يحل stop بلا double dial، Consent/DNC verified؛ **لا تفعّل حملة محظورة أو auto-enable من خلف المالك**. |
| APP-15 (P2) | تقليل استهلاك البطارية والإشعارات | `MobileLinkService.kt`، notification/overlay | notify عند state change، bounded WakeLock فقط أثناء مكالمة، no tight loops، idle 24h test؛ لا توقف foreground service المهم للمكالمات. |
| APP-16 (P2) | إدخال الملفات والأسماء وسجل المكالمات | `MobileLinkActivity.kt`، `CallEventOutbox.kt`، Contacts import | CSV/TXT/XLSX/Phone picker، preview+column mapping، DNC/Consent، report search/filter؛ لا مسح جهات الاتصال القديمة أو نقلها بين حسابين. |

**DoD APP-2:** SIM1 وSIM2 متمايزتان؛ أي Handoff/Agent policy لا يتسرب لغير موقعه؛ كتم/صوت/اتصالات صحيحة بعد background؛ لا crash بسبب Overflow أو campaign network call.

---

## Phase APP-3 — Setup/Permissions Wizard للمستخدم غير التقني

**هدف المرحلة:** استكمال معالج الإعداد الموجود بالفعل في `saas/mobile/ota/ui.html` وشاشات Android، لا إنشاء تجربة بديلة تلغي الربط الحالي. تُحفظ حالة المستخدم وتُفحص مجددًا عند العودة من الإعدادات.

| خطوة | الشاشة | فحص / فعل / حفاظ |
|---|---|---|
| S0 | فحص جهاز ومواصفاته | `Build.MANUFACTURER`، SDK، SIMs، permissions، paired tokens؛ **فحص لا يفصل الحساب**. |
| S1 | تنزيل/تثبيت مؤكد | Version SHA256، Android installer، موافقة المستخدم دون تعطيل Play Protect برمجيًا. |
| S2 | إشعارات | `POST_NOTIFICATIONS` على API 33+؛ فحص الحالة عند `onResume`. |
| S3 | الهاتف | `READ_PHONE_STATE` و`CALL_PHONE` و`ANSWER_PHONE_CALLS` حسب وظيفة الإصدار؛ اختبارات rejected/denied. |
| S4 | الميكروفون | `RECORD_AUDIO` مع توضيح أنه لا يضمن التقاط الطرف الآخر من Carrier Call على كل OEM. |
| S5 | دور المكالمات | `ROLE_DIALER`/`InCallService` إذا كان المسار المطلوب متاحًا؛ **لا تغيّر Default Dialer قبل موافقة صريحة ولا تكسر fallback العامل**. |
| S6 | Overlay | `Settings.canDrawOverlays` وفتح `ACTION_MANAGE_OVERLAY_PERMISSION` مع Recheck. |
| S7 | البطارية/الخلفية | `ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` عند الضرورة، Battery Unrestricted/Auto-start حسب OEM. |
| S8 | إعدادات OEM | Xiaomi/HyperOS، Samsung/OneUI، Oppo/Realme/ColorOS، Huawei؛ probe ثم deep-link اختياري، fallback `ACTION_APPLICATION_DETAILS_SETTINGS`. |
| S9 | ربط Tenant+SIM | QR أو 6 أرقام، SIM1/SIM2، Agent المنشور؛ لا تدوير token أو Unpair في إعادة تشغيل Wizard. |
| S10 | اختبار نهائي | overlay/service/connection/PCM self-test وقراءة `readiness`؛ مكالمة حقيقية **فقط بعد موافقة مالك الخط**؛ `UNVERIFIED` إذا تعذر اختبار carrier. |

**منطق الفحص:** `NOT_ASKED / GRANTED / RATIONALE / PERMANENT_DENIAL / OS_UNSUPPORTED / E2E_UNVERIFIED`. `shouldShowRequestPermissionRationale=false` وحدها ليست دليل رفض دائم؛ تعتمد على سجل طلبات الإذن. `onResume` + Activity Result re-check، وتقدم تلقائي **بعد التأكد من GRANTED** لا بعد الضغط على الزر. خيارات Skip للصور/جهات الاتصال الاختيارية. لا polling مستمر أو فتح تلقائي لشاشات OEM عند المكالمة.

**DoD APP-3:** هاتف جديد يمر بالخطوات دون ADB عام؛ هاتف موجود مربوطة شريحته يفتح المعالج دون أن يفقد الربط أو يتوقف النظام؛ رفض دائم يؤدي لإعدادات واضحة ثم تحديث صادق؛ Xiaomi/Oppo/Samsung لا crash إن تغيّر Intent الخاص بالشركة.

---

## Phase APP-4 — OTA وAPK والأمان وقبول الأجهزة

| ID | المهمة | الملف/النطاق | إغلاق بنمط منع التراجع |
|---|---|---|---|
| APP-17 (P0) | حماية هوية توقيع التطبيق | `android-audio-proof.yml`، `mobile-ui-ota.yml`، `NativeUpdates.kt`، `SignedOta.kt` | فك اعتماد `debug.keystore` تدريجيًا **بخطة انتقال للهواتف المثبتة**، دون توقيع تحديث مختلف فجأة ثم اعتباره قابلًا للتثبيت؛ verify signer+cert+SHA والمفتاح محفوظ آمنًا. |
| APP-18 (P1) | الفصل الصارم بين UI OTA وNative APK | `saas/mobile/ota/ui.html`، `MobileOta.kt`، `NativeUpdates.kt` | تعديل HTML/CSS/JS عبر UI OTA فقط؛ Native Kotlin/CallOverlay/PCM via signed APK؛ `min_native/max_native` وBridge compatible؛ المستخدم يرى الرقم ويثبت Android بموافقته. |
| APP-19 (P1) | اختبارات عقد أوامر WebView | `MobileLinkActivity.kt` + `ui.html` | كل `send('command')` في HTML له handler في Kotlin؛ لا فشل “أمر غير مدعوم”، مع حالات error UI مفهومة؛ test snapshots RTL/Light/Dark وtabs. |
| APP-20 (P1) | تأمين Debug/ADB المحلي | `MobileLinkActivity.kt`، `prepare.py`، `CaptureDaemon` | Port 5555 لا يترك مفتوحًا عن غير قصد؛ محرك ADB مسار متقدم واعٍ **لكن لا تعطله على هاتف يعتمد عليه دون بديل مثبت بالاختبار**؛ اختبار Wi-Fi↔data بعد الإعداد. |
| APP-21 (P1) | Release gate بجهاز حقيقي | Android test suite/CI + owner devices | Unit tests/Emulator/Merged Manifest/Signature/OTA تحقق منفصل؛ ثم SIM E2E على Wi-Fi وLTE، Oppo وXiaomi؛ مسجل بالـCall IDs والأزمنة. |
| APP-22 (P1) | تقارير وتشخيص المستخدم | `CallEventOutbox.kt`، UI reports، diagnostics | Timestamped states and specific error codes؛ لا رسالة نجاح وهمية “تم التشغيل” مع خدمة فاشلة؛ لا تسريب مفاتيح أو محتوى مكالمات في logs عامة. |
| APP-23 (P2) | خطة الترقية/الرجوع دون انقطاع | Release/OTA/installer | احتفظ بنسخة صاحبة التوقيع الحالي، لا تُمسح بيانات التطبيق أو زوج SIM، verify SHA من الجهاز؛ عند فشل تستعيد وظائفه بHotfix موقّع ذي versionCode أعلى أو مسار رجوع متوافق. |

### مصفوفة الاختبار الفيزيائي الإلزامية

| التجربة | على Wi-Fi | على 4G/5G | بعد قفل الشاشة | الإغلاق |
|---|---|---|---|---|
| Incoming ring → answer (الإعداد 5s حيث كان محفوظًا) | ✅ اختبار مطلوب | ✅ اختبار مطلوب | ✅ اختبار مطلوب | زمن الرد مطابق للسياسة ولا يبدأ الكلام قبل الإجابة |
| Caller→AI speech recognition | ✅ | ✅ | ✅ | الصوت مفهوم، packet loss والـlatency مقاسان |
| AI→Caller audible playback | ✅ | ✅ | ✅ | صوت الطرفين مسموع بلا تكرار/تراكم |
| Mic mute/human takeover | ✅ | ✅ | ✅ | لا ينسب نجاحًا لعملية فشلت |
| Outgoing SIM1/SIM2 answered/unanswered | ✅ | ✅ | حيث يدعمه النظام | SIM صحيحة وAnswered ≠ Dialing |
| Incoming SIM1/SIM2 Tenant isolation | ✅ | ✅ | ✅ | لا بيانات/إيجنت/KB من حساب آخر |
| Network switch / reconnect / Provider cooldown | ✅ | ✅ | ✅ | Recovery bounded، لا فقد سياق صامت ولا صوت مضاعف |
| Call report, transcript, summary, human request | ✅ | ✅ | ✅ | نفس Call ID، مصدر كل حدث، وعدم اختلاق نتيجة |
| OTA UI + Native install approvals | ✅ | ✅ | N/A | نفس توقيع التحديث، ولا فقد ربط أو تراجع في المزايا |
| Battery/overlay/notifications | ✅ | ✅ | ✅ | استقرار دون سبام أو توقف خدمة |

رمز **✅ هنا يعني «اختبار مطلوب» لا نتيجة نجاح حدثت**. لا يُكتب PASS إلا بعد إجراء الاختبار بالفعل على الجهاز المذكور.

---

## تعليمات تنفيذ غير قابلة للتجاوز
1. **أصلح الجزء المعيب فقط**، لا تطوّر Demo جديدًا بدل Native path العامل ولا تستبدل محرك PCM بالكامل بسبب ملاحظة Audit قديمة.
2. **تثبيت نسخة الهاتف وتحويلها إلى baseline قابل للمقارنة إلزامي** قبل تغيير الصوت أو البرومبت أو تصاريح التليفون أو شهادة التوقيع.
3. **لا إزالة لأي زر أو API أو Option** قبل إعداد توافق وعميل اختبار وثبوت عدم الحاجة. عند ميزة غير آمنة: امنع الاستغلال مع حفظ الوظيفة المشروعة.
4. **لا تغيير Client contract** إلا بإصدار versioned متفق مع خطة SaaS؛ لا تبني APK يطلب Endpoint لم يُنشر أو Token غير مدعوم.
5. **لا تحديث صامت دون موافقة Android** ولا تغيير هوية signer المستخدمة للهاتف الحالي بلا ترحيل مثبت؛ شاشة UI OTA منفصلة عن تحديث Native.
6. **لا تلقِ اللوم على OEM دون اختبار.** الافتراض بأن `ROLE_DIALER` أو Microphone permission يحل صوت GSM على كل هاتف غير صحيح؛ يلزم دليل hardware-specific.
7. **إعدادات ADB وDeveloper Options ليست واجهة المستخدم العادي**، لكن لا تُعزل من هاتف يعتمد عليها حاليًا دون بديل عامل.
8. **لا إعلان إنجاز** من نجاح Build/CI/رفع ملف أو إظهار Notification؛ إثبات المكالمة من الطرفين والـHost/Phone logs شرط.
9. **لا فحص أو تعديل `VS/`**، ولا لمس أسرار التوقيع/API أو أرقام الهاتف في التقرير.

## بطاقة التسليم لكل إصلاح
`APP-XX | Working behavior before | Changed files | Source SHA | APK versionCode+cert SHA | OTA revision | Tests PASS/FAIL | Wi-Fi vs LTE real calls | SIM1/2 | Background/Oppo/Xiaomi | Regression checklist | Publish receipt | Rollback/Forward-fix | UNVERIFIED gaps`.

### الحالة عند إنشاء هذه الخطة
**التخطيط والتوثيق فقط؛ لم يُبنَ APK جديد، ولم يُنشر UI OTA، ولم يُثبَّت إصدار، ولم تجرِ مكالمات هاتف فعلية أو تعديلات Kotlin ضمن هذه المهمة.**  
**لا تعديل ولا اطلاع على مجلد `VS/`.**
