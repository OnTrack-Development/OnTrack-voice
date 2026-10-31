## 31 أكتوبر 2026 — OnTrack Voice 0.2.4 / OTA10 / اتصال بدون Wi-Fi تجريبي / سجل مكالمات أولي
- أصل طلب المستخدم: لماذا سجل المكالمات/الحملات/رفع جهات الاتصال/التقارير/التسجيل/مراقبة الصوت/سيناريوهات AI/Agent Builder لم تظهر في التطبيق والموقع؟ هي **ليست مكتملة**: Laravel `saas/` يحوي CRM/VoiceCampaign workflow/Flow Builder محليًا، لكن ليس ربط SIM telephony/outbound/report/recording ميدانيًا. التفاصيل في `docs/mobile/VOICE_CALL_FEATURE_GAP_AUDIT_2026_10_31_AR.md`.
- حُددت مشكلة تكرار تحميل APK: النسخة القديمة تكرر طلب PackageInstaller بدل انتظار نتيجة الجلسة. أُضيف `NativeUpdates.installPending()` مع SharedPreferences، reuse cached APK، فحص إذن التثبيت قبل التنزيل، status callbacks وتمييز UI. Android 0.2.4 code15 و UI OTA revision10.
- مصدر JemRec pinned يدعم `StartWithoutWifi` عبر ADB 5555 بعد إعداد أولي على Wi-Fi؛ أُضيف opt-in واضح مع تحذير خطر منفذ شبكة عام وقياس ready + disabled في النسخ القديمة. **لم يُثبت على Xiaomi بعد**، وقد يتعطل بعد reboot أو سياسات OEM. لا يُفعّل تلقائيًا.
- في المصدر أضيف `CallEventOutbox.kt` و`call_event.php`, `call_ledger.php`, `calls.php` لتسجيل رنين/رد/نهاية/مدة داخل تخزين خاص، مع حماية device/admin وidempotency؛ لا اسم/رقم/ملخص/صوت حتى الآن. ملفات PHP ونقطة رابط الأدمن نُشرت على `app.ontrackegy.com/saas/mobile` عبر job `mobile-call-ledger-pilot-20261031` (success + PHP lint PASS).
- GitHub Actions run `41167848892`: build=success، Android WebView=success، publish_ota=success، publish_native=success. استضافة OnTrack: UI10 job `mobile-ota-41167848892` success SHA256 `6f28fb74e9e40dc651f9dc09ce01aeabe4cae7b4b1ef132611f6f1f393959f76`؛ native15 `mobile-native-41167848892` success APK SHA256 `2046fb2e46dffbcb01108c882f1ec6e9486001d0c69bec1ce084d573dd07f8c7`, bytes 52424257.
- فحص HTTPS العام والأمان المُجدول `mobile-024-live-security-20261031`: لا تعتبره PASS قبل قراءة تقرير host.
- **مهم:** current installed phone version غير مؤكد، ولا يمكن نقل إصلاح native Kotlin القديم من مجرد HTML OTA. لإصلاح تثبيت Android على الجهاز مرة واحدة يحتاج APK أحدث مع شهادة التوقيع ذاتها؛ قد يطلب Android موافقة/إذن تثبيت من المصدر. لا تمسح التطبيق أو مفتاح الربط. الصوت `mix` لا يُعدل.
- الاختبار المتبقي: Xiaomi update 0.2.4 وتحقق local install result، مكالمة عبر بيانات 4G/5G دون Wi-Fi بعد opt-in أمنية، أول حدث مكالمة يصل للسجل، والاتصال بعد reboot. لا تدّع نجاحها قبل handset evidence.

## OnTrack Voice 0.2.3 — تثبيت Android الجديد وSplash OTA9 (31 أكتوبر 2026)

- المستخدم شارك صورة من جهاز Xiaomi تظهر `There was a problem parsing the package` بعد تحديث 0.2.2 عبر النسخة المثبتة 0.2.1. المستخدم يريد تحديث View بصمت دون إعادة تثبيت وتحديث Native من داخل التطبيق قدر الإمكان، ويريد شاشة OnTrack متحركة فاخرة، مع عدم فصل Cloud عن Android.
- نفذنا `NativeUpdates.kt` بـ Android `PackageInstaller.Session` والتحقق من توقيع manifest + SHA/size/package/version/cert، بدل `ACTION_VIEW` الذي قد يفشل على OEM؛ `NativeInstallResultReceiver.kt` يسجل سبب خطأ التثبيت ويعالج `STATUS_PENDING_USER_ACTION`. `MobileLinkActivity.kt` يُظهر سبب التثبيت عند العودة ويدير شاشة إقلاع Android أصلية `OnTrackLaunchScreen.kt`. `saas/mobile/ota/ui.html` واجهة UI9 تتحرك عند بداية التحميل، تدعم Reduced Motion، وتُحدّث WebView بعد المكالمة لا أثناءها. `saas/mobile/audio-proof/overlay/app/src/main/assets/ontrack-ui.html` محدثة أيضًا.
- CI النهائي GitHub Actions run `41161367116`: build=success، Android WebView instrumentation=success، publish_ota=success، publish_native=success. أصدر `0.2.3-ota` code14 مع نفس شهادة التوقيع السابقة.
- استضافة app.ontrackegy.com: host report `mobile-ota-41161367116` success revision9؛ `mobile-native-41161367116` success، APK bytes 52407873، SHA256 `0eedd40db8d2c69b6e5fab2dcccccc2883f20880e52733237e784ed5b3501fbe`. فحص مستقل `mobile-023-public-verify-20261031` success: `latest.json` HTTP200 وHTML HTTP200 13185 bytes، `native/latest.json` HTTP200 وAPK HEAD HTTP200 بالحجم المطابق؛ content type application/vnd.android.package-archive.
- رابط APK المصحح: `https://app.ontrackegy.com/saas/mobile/updates/native/ontrack-voice-v14-0eedd40db8d2c69b6e5fab2dcccccc2883f20880e52733237e784ed5b3501fbe.apk`.
- **قيود لا يجوز تجاهلها:** UI OTA9 متوافق مع native 12–14 ويفعل الـSplash بالـWebView من غير APK. أما إصلاح Native installer فلا ينتقل إلى 0.2.1 كـHTML OTA؛ يلزم تثبيت 0.2.3 مرة فوق التطبيق من Android Installer. جهاز Android غير مدار قد يطلب تثبيت من هذا المصدر وموافقة المستخدم. لا تدّعي أن 0.2.3 حل parse على Xiaomi إلا بعد real-device test؛ الاختبارات الآلية لا تتحقق من ROM الخاص بالمستخدم. لا Uninstall ولا Clear Data. لم يتم تنفيذ السجلات التجارية/الحملات/Agent Builder/التسجيل الحي في هذه الدفعة؛ Laravel repo لديه أساس CRM/Campaigns/Flow وليس تشغيل SIM end-to-end مثبت.
- توثيق مفصل: `docs/mobile/ONTRACK_V023_INSTALLER_SPLASH_FIX_AR.md`.

## إصدار OnTrack Voice 0.2.1 — مركز تحديثات Android مكتمل النشر (2026-10-31)

نجاح بناء Android وإختبارات JVM وWebView في GitHub Actions run `41157138497`؛ Artifact `13025238406`، `versionCode=12`، `versionName=0.2.1-ota`، SHA256 APK `d78e110aa8bdfab4264f7904af7e0e8cf16af0a9ed847c16161b70f560cefc00`، شهادة APK الأصلية `d25378b9...` نفسها. نُشر UI OTA revision7 عبر مهمة `mobile-ota-41157138497` نجاح. ونُشر Native manifest الموقّع وAPK عبر `mobile-native-41157138497` نجاح. فحص HTTPS نهائي `mobile-native-public-permission-fix-20261031` نجاح: `native/latest.json` HTTP200، APK HEAD HTTP200، حجم 52407873 ونوع application/vnd.android.package-archive. خطأ 404 المؤقت سببُه أن المجلد اتعمل mode0700 بسبب umask العامل، اتصلح إلى 0755، وأُضيف إصلاح ناشر المستقبل في `tools/queue_mobile_native.py` commit `de326fb`. الرابط المباشر الموثق:
`https://app.ontrackegy.com/saas/mobile/updates/native/ontrack-voice-v12-d78e110aa8bdfab4264f7904af7e0e8cf16af0a9ed847c16161b70f560cefc00.apk`.
التطبيق 0.2.1 فيه Native Update Center يتحقق من signed manifest + APK hash/size/package/cert ويفتح موافقة Android بدلاً من تثبيت صامت، بالإضافة إلى UI OTA وإعدادات السيرفر الموجودة. **الانتقال 0.2.0→0.2.1 يحتاج تثبيت APK مرة واحدة فوق النسخة المثبتة؛ لا حذف بيانات.** اختبارات التطوير والبناء والنشر ناجحة؛ لكن تثبيت النسخة الجديدة على هاتف المستخدم، أول تنزيل Native Update مستقبلي، تسليم المكالمة والانتقال بين Wi-Fi لم يُختبَروا ميدانيًا بعد. ملف حقائق التسليم `docs/mobile/ANDROID_UPDATE_0_2_1_DELIVERY.json`، التصميم والاختبارات `docs/mobile/ANDROID_IN_APP_OTA_0_2_1_AR.md`.

## 2026-10-31 • OnTrack Voice 0.2.0 / Server Sync & Auto Reconnect
- طلب المالك: التفعيل/الإيقاف موحّد بين الهاتف والسيرفر، استلام بشري للمكالمة الحالية فقط، فقاعة منظمة بلا Accessibility، إعداد Android بخطوتين، استئناف بعد تبديل شبكة Wi-Fi، هوية OnTrack، ودخول لوحة الإدارة من التطبيق بدون إعادة كتابة Secret Key.
- تم نشر `control.php` و`admin_ticket.php` و`admin_enter.php` و`status_feed.php` على الاستضافة عبر `mobile-020-auth-deploy-20261031` (success + PHP lint PASS). تم نشر تعديل `index.php` عبر `mobile-020-admin-sync-20261031` (success + backup). فحص HTTP `mobile-020-live-security-verify` نجح: صفحة الإدارة 200، وواجهات التحكم والـSSO والحالة غير الموثقة 401. تعديل SameSite Lax للأمان وتوافق SSO على المتصفح عبر `mobile-020-sso-cookie-20261031` (success).
- Android Kotlin: `MobileLinkService` يحتفظ بالربط كـForeground Service حتى لو AI OFF، يسحب desired server state ويدفع تغيير الهاتف للسيرفر، ويرفع device-ack؛ `ConnectivityManager.registerDefaultNetworkCallback` وretry 2–30 ثانية مع محاولة إحياء مساعد الشريحة، دون تجاوز صلاحيات Android. `CallOverlay` بداخلها استلام حالي، سماعة، تشغيل/إيقاف؛ `ProofActivity` معالج إعداد مخفف إلى خطوتين؛ UI OTA6، تطبيق `0.2.0-ota` وversionCode11 وبصمة التوقيع القديمة إلزامية؛ علامة OnTrack الجديدة.
- **CI أحدث حالة عند كتابة السجل:** Run `41151549729` مستمر. Run `41151330827` فشل بسبب Kotlin `fillViewport` في ProofActivity؛ أصلح في commit `a1ad4fc7` إلى `isFillViewport`. لا تدّع نجاح APK قبل إكمال CI والحصول على Artifact موقّع، ولا تدّع نجاح تبديل Wi-Fi/تسليم بشر/SSO في الهاتف قبل Real Device E2E.
- شرح شامل وقائمة الاختبار: `docs/mobile/VOICE_020_RELEASE_AR.md`. آخر نجاح مثبت للمكالمة الصوتية ثنائية الاتجاه كان بالنسخة 0.1.9 وقناة `mix` على هاتف المستخدم؛ لا تغير هذا المسار.

## Pilot Voice 0.1.9 + Admin/OTA5 — تحقّق 31 أكتوبر 2026

المالك أكد مكالمات SIM حقيقية صوتًا في الاتجاهين بعد `input_channel=mix`. بناء `OnTrack-SIM-Audio-Test-0.1.9.apk` أنهى CI run `41145709185` بنجاح: Kotlin/daemon tests، Android WebView instrumentation، توقيع APK/OTA. شهادة APK `d25378b9...` نفسها القديمة، `versionCode=10`، `SHA256=7bef6a3cc9f4db41096598e0b66d34681ca8e33b601ad2fb9e1ebaf9ae7463e5`. Artifact `13020867281` (موجود للتنزيل). OTA5 نشرته مهمة `mobile-ota-41145709185` على الاستضافة ورجع `success/exit0/revision5`. لوحة التحكم الحالية نُشرت عبر `mobile-admin-backend-20261031a` و`mobile-admin-panel-publish-20261031c`، فحص PHP `PASS`، فحص HTTP أظهر `admin=200` و`status_feed=401` دون جلسة، ومفاتيح الربط لم تُقرأ أو تُطبع. الكود يدعم نافذة عائمة اختيارية دون Accessibility، استلام بشري، إيقاف الوكيل، رد بعد 5 ثوانٍ، زر سماعة تجريبي، وقواعد ردود قصيرة بالمصري مع مقاطعة Gemini. **لم تُختبر هذه الإجراءات الجديدة على هاتف المالك بعد**؛ نجاح CI لا يثبت handoff أو speaker أو latency. الحالة المفصلة: `docs/mobile/VOICE_PILOT_RELEASE_0_1_9.json` وخطة التست `docs/mobile/VOICE_PILOT_0_1_9_CALL_CONTROL_AR.md`. لا تمسح بيانات التطبيق أو الاقتران؛ اختبر فوق النسخة الحالية.

## قرار معتمد — Phone Control Center / سياسة رد بعد 5 ثوانٍ (2026-10-31)

اعتمد المالك مواصفات تجريبية على مستوى الخط/الشركة: `auto_answer=ON`، `answer_mode=after_delay`، `delay_seconds=5`، `agent=sales`، **دون تفعيل إنتاجي حتى الاختبار**. تفاصيل الـstate machine والأمان والقيود الرسمية على Android والـAPI/RBAC واختبارات E2E في [PHONE_CONTROL_CENTER_SPEC_AR.md](../architecture/PHONE_CONTROL_CENTER_SPEC_AR.md) (commit `8ccdd2d74279c4b72b72dd62f90d685fa343b9ac`). المصدر الحالي يرد فورًا عند RINGING، لا يضم مؤقتًا ولا Agent ID متعدد الشركات؛ لا يتم تغيير الربط الصوتي العامل أو قناة `mix` حتى اختبار السياسة الجديدة.

## تحقق ميداني — OTA4 وHeartbeat من هاتف المالك (31 أكتوبر 2026)

صورة الهاتف تؤكد فتح Android 0.1.7-ota وتشغيل UI OTA4 برسالة «الواجهة جاهزة • OTA 4» مع حالة «متصل — جاهز لمكالمة واردة أو اتصال من الهاتف». اختبار هوست حي `mobile-device-heartbeat-20261030T224309` نجح `exit_code=0` الساعة 22:44:02 UTC؛ أحدث heartbeat كان 22:44:01 UTC (العمر 1 ثانية)، `settings_enabled=true`، `auto_answer_enabled=true`، `settings_revision=4`. مصدر التقرير: `ops/host-link/results/appontrack-server11/mobile-device-heartbeat-20261030T224309.json` في الفرع `ontrack-host-link`. هذه أدلة تشغيل UI وطلبات الإعدادات من الهاتف، ولا تثبت اتصال AI/SIM صوتي ثنائي الاتجاه بعد. الخطوة التالية: مكالمة واردة فعلية من رقم ثانٍ وقياس الإجابة والتقاط الصوت والرد.

## أحدث عطل — WebView منع الصفحة المحلية، إصلاحNative0.1.7

صورةالمالكبعد0.1.6تظهرتحميلOTAثمWebpage not available علىdata:text/htmlمعHTTP_RESPONSE_CODE_FAILURE. الكودكانيعيد403لكلطلب،بلامساريسمحويخدمHTMLالمحلي؛هذاعيبفيالنسخةالمسلّمةولايتعلقبمفتاحالمكالمات401.0.1.7يستبدلالتحميلبصفحةHTTPSافتراضيةمحددةلكلrender،وتُخدم200منالـHTMLالموقّعفقطللـmainGET؛كلالمواردالأخرىتبقىمحجوبة. أزرارNativeللتشخيصوالاسترجاعتبقىمتاحةإنفشلWebView. نضيفاختبارinstrumentationلـAndroidWebViewAPI34قبلالتسليم؛ البناءوالهاتفغيرمثبتينبعد.

تقريرmobile-ota-41131334617وصلsuccess/exit0عند21:38:30UTCوثبّتنشرrevision2؛ إذًاالعطلالحاليليسغيابملفاتOTA. الإصلاحNativeويحتاجتحديثAPK0.1.7بنفسالشهادةوبلامسحبيانات. راجع docs/mobile/OTA_WEBVIEW_LOAD_FIX_2026_10_30.json.

## أحدث تسليم — APK0.1.6 وOTA موقّعان، نشرالواجهةمنتظرالعامل

run41131334617نجح(build124322733614,publish124324094537).Artifact13013975015نُزّلوتحققناZIPوAPKوبصماتoverlayوالواجهةالمضمنةوشهادةنفسالنسخالأخيرة. اختباراتKotlinلتوقيعRSAالمعدل/المفتاحالمختلفوحدودruntime/path/sizeنجحت،والحارسالنهائيلـmanifestنجح؛لاNotificationListenerأوSMSأوAccessibility. محاولاتالبناءالسابقةمحفوظة؛خطأBuildConfigأُصلحباستخدامPackageInfo. فحصJSsyntaxنجح؛فحصPlaywrightلم يُنفّذ لغيابChromiumالمحلي،ولانصفهناجحًا.

الـOTAهوواجهةمركزالاتصالHTML/CSS/JSضمنbridgeثابتوتوقيعشهادةAPK،نسخةمضمنةواحتفاظبالسابقةومهلةready10ثوانٍومنعreplayوnonceلكلrender. NativeالصوتوالصلاحياتتحتاجAPK. يلزمتثبيت0.1.6تأسيسيًامرةبنفسهويةالتطبيقوبلامسحالاقتران.

النشرالآليأرسلmobile-ota-41131334617(7067بايت،تنفيذ25ثانية،صلاحيةحتى31أكتوبر21:32UTC)عبرالعاملالموجود. آخرالفحص:لاتقريرmatchingوlatest.jsonيرجع404؛إذًاالتحديثغيرمنشوربعد. لا تستبدلأوتكرّرالمهمة. يحتاجالعاملدورةتنفيذ،ثمفحصتوقيعmanifestوبصمةHTMLمنالموقع،ثمفتحالهاتفلإثباتUIOTA2. إعداداتربطالمكالماتالسابقةمنشورة،لكنphone401مازالتغيرمحلولةوAI/SIMduplexغيرمثبت.

الأدلة docs/mobile/MOBILE_OTA_BUILD_0_1_6.json وdocs/mobile/OTA_HOST_PUBLICATION_2026_10_30.json وdocs/mobile/OTA_UPDATES_AR.md.

## أحدث طلب — تنفيذ OTA داخل التطبيق

المالكطلبتحديثًامثلThndr. نُفِّذتطبقةواجهةمركزالاتصالHTML/CSS/JSقابلةللتحديثموقعةبهُويةAPK،وcacheمحليوتراجععندالفشلوتأجيلأثناءالمكالمة،ضمنbridgeمحددبلاshellعامأوملفاتأوشبكةمنWebView. أولنسخة0.1.6تحتاجتثبيتًاتأسيسيًامرة؛تغييراتNativeالصوتوالصلاحياتتحتاجAPKدائمًا. الكودقيدالبناءولانشرOTAمثبتبعد. الهاتف401قضيةمستقلةمفتوحة. راجع docs/mobile/OTA_UPDATES_AR.md.

## أحدث رصد — التطبيق مفتوح، الهاتف401 والمفتاح الصحيح يمر200

صورةالمالك تعرضشاشةالربط تعمل معHTTP401؛ رقمAPKغيرظاهر. اختبرناالمفتاحالمعطى علىdevice.phpفعليًا بطلبين، أحدهماUser-AgentلـOkHttp4.12: كلاهما200/ok/enabledtrue. لااستنتاج بأنالمالكأدخلقيمةخاطئة؛ الحقلمخفيولايمكنفحصه. الخطوةالمباشرة: إيقافالربط، استبدالالحقلبنسخةالمفتاحالمنسوخةبالضبط، إعادةالتشغيل. إن استمر401نضيفتشخيصًامنقحًا، دونمسحاقترانأوتعطيلحماية. لااتصالAI/SIMمثبت. الدليل docs/mobile/MOBILE_AUTH_RECHECK_2026_10_30.json.

## أحدث تسليم — APK0.1.5 جاهز، تجربة التثبيت على الهاتف مطلوبة

حُذفت خدمةقراءةإشعاراتالتطبيقاتالأخرى وتنفيذها ومساراتمنحها وواجهتها منOnTrack. بُنيAPK0.1.5فيrun41127903051، artifact13012375680. تحققنا منالملف المنزّل وبصماتoverlay ومنmanifestالمستخرج منAPKالنهائي: لاNotificationListener/SMS/Accessibility. التوقيع مطابق0.1.1و0.1.4؛ نفسpackage، بلاطلبمسحبياناتالاقتران. SHA256: fbffb166062918ef70715ec43b9b6fb4d78040545c33bf0509a76e7217bf5c04. هذه إزالةصلاحيةغيرلازمة، وليستتعطيلًا للحماية أوتحايلًا بتغييرهويةالحزمة. قبولPlayProtect والتثبيت الفعلي والمكالمة الكاملة غيرمثبتين بعد.

الهوست منشوربتقريرsuccess/exit0. الصفحة200، deviceبدونمفتاح401، بالمفتاح200، sessionالمحمي200وبهتوكنمؤقت، ملفhashمرفوض403. المصدر docs/mobile/MOBILE_BACKEND_HTTP_2026_10_30.json. لا تكرارلمهمةالنشرالمنفذة، ولاانتظارCronكشرطلبدءالمكالمة.

## أحدث عطل تثبيت — Play Protect، إصلاح0.1.5

صور المالك تظهر حظر0.1.4 برسالة sensitive data / identity theft / financial fraud. وجدنا NotificationListenerService موروثًا من JemRec لميزات إخفاء إشعارdebugging وأسماء متصلي تطبيقات أخرى؛ الجسر الحالي لا يحتاجه. رسالة Google الرسمية تطابق هذا النوع من الحظر؛ السبب الداخلي الدقيق غير متاح. حذفنا الخدمة وتنفيذ قراءة الإشعارات ومسارات منح صلاحيتها وإعداداتها؛ لا تعطيلPlayProtect، ولا تغييرهويةالحزمة للتحايل، ولا مسحبياناتالإعدادالعامل.0.1.5 قيدالبناء والتحقق، والقبول على الهاتف غير مثبت.

تقريرالنشرmobile-link-deploy-1793390868859 وصلsuccess/exit0 بتاريخ20:46:25UTC، وفحصPHPنجح ونسخةاحتياطيةخاصةحُفظت. هذا يحل انتظارالتنفيذ السابق؛ لا يثبت مكالمةAI. الدليل docs/mobile/PLAY_PROTECT_INSTALL_2026_10_30.json وتقريرالهوست.

# أين وصلنا؟ — 29 أكتوبر 2026

## نقطة التسليم الحالية — APK0.1.4 جاهز، العامل مطلوب للنشر

نُزل artifact13009691302 من run41122903309. SHA256للـAPK هو41d32c09d4b8714dfce9d38614ccb322c15553f44a8749ad8a37964762b524cb؛ الحجم52327052بايت. التوقيع مطابق0.1.1، وبصماتoverlayمطابقة، وفُحصDEXلوجود عنوانRPCالمصحح وغيابالخاطئ. GeminiقبلsetupCompleteفعليًا، ونجحlivecheck41122903296.

النشر لم يُثبت بعد: mobile-link-deploy-1793390868859 ما زالت المهمة الحية، وآخرAPIجديد404، ولا تقريرتنفيذ. أمرworkerالذي نفذه المالك سابقًا أكمل الفحصوانتهى؛ طُلبloopقصير للعامل الموجود لمعالجة المهمة التالية، دونتوكنجديدأوقراءةconfigأوإعادةتثبيت. لا تسلّم مفتاحالربطعلىGitHub أو تقاريرالهوست؛ قيمةمفتاحالتجربة الجديد مخزنةمحليًافقط، وhashفقطداخلالكودالخاص.

التطبيق المصحح يمكنتثبيتهكتحديثفوق0.1.1 بنفسالتوقيع؛ لا نطلبمسحبياناتالتشغيلالعامل. لا يزالاختبارAI/SIMالفعليغيرمُنفذ، ولا تُعلَنصفحةالإعداداتمنشورةقبلوصولتقريرالنشر وفحصHTTP.


## أحدث تصحيح — جلسة Gemini قُبلت، APK0.1.4 يُبنى

404كانت بسبب خطأ في العنوان الذي كتبناه: استخدمنا / بين GenerativeService وBidiGenerateContentConstrained بدل النقطة في SDKGoogleالمثبت2.25. لم يكن يلزم تبديل مزود/مفتاح/موديل أو إعدادات الهاتف. صححنا Native والاختبار. فحص فعلي من البيئة أعاد websocket_open=true وsetup_ack=true، وفحصGitHubالمصحح نجح بخطوةالتحقق؛ لم يرسل صوتًا ولم يثبتSIMduplex.

لا تسلّمAPK0.1.3 القديم لمهمة الربط: التوقيع والبناء صحيحان لكنه يحملمسارWSSالخاطئ. الإصدارالمصحح0.1.4 مصدرf163c6bb7dc83f06ba5f65d11e9ee0aa1f31aa77، build41122903309، livecheck41122903296. يظل النشر منتظر مهمةmobile-link-deploy-1793390868859؛ لا تقريرولامدخلموبايلحي بعد عندآخرفحص.


## أحدث نتيجة — APK0.1.3 متحقق منه، النشر منتظر

النسخة النهائية مصدر03e014727ca34d4bbe9459c9a477d39501b02415، run41120377309، artifact13008627580. اختبارات وبناء وتوقيعAPK وفحصPHP اكتملت بنجاح؛ الحزمة المنزلة تطابقSHAوبصماتoverlay، وDEXيحتويالجسر، والتوقيع يطابق0.1.1. لا يستنتج ذلك محادثة SIM.

تشغيل المالك للعامل أعاد تقرير mobile-bridge-inspect-1793389599248 ناجحًاUID2379/exit0. أرسلت بعده mobile-link-deploy-1793390868859 بحزمةSHAمحددة، لكن التقرير ما زال منتظرًا والـAPIالجديد404 عند آخر فحص. طلبنا loopمحدود3دقائق للعامل القائم؛ لم نقرأ configأوPATولم نعد تثبيته. لا تستبدل المهمة المعلقة أو تعلن النشر قبل تقريرها.

APIالجلسة الحالي أعادHTTP200وتوكنمؤقت؛ محاولاتWSSالمحلية أظهرتفتحًا مرة و404مرتين قبلsetup، لذلك شُغّل تشخيص مستقل علىGitHubActions run41121648614 دون إرسال صوت أو طباعةتوكن. لا يثبت إصدارالتوكن قبولالجلسة. نحتاجنتيجته ومكالمةالمالكالمباشرة. OFFHOOKليس دليلإجابةالطرفالبعيد؛ هذه بوابة منفصلة قبل حملاتالصادر.


## أحدث توجيه — الربط المباشر بالموقع

المالك طلب الانتقال فورًا لربط تطبيق الهاتف بالسكربت الموجود على app.ontrackegy.com، وإضافة إعدادات الاتصال، وإعادة التقييم عند ظهور عطل بدل إطالة اختبارات منفصلة. هذا يفتح تنفيذ الربط الآن؛ التسجيل ليس بوابة تمنع العمل، ولا يعني أن duplex قد ثبت.

HTTP مباشر أكد OnTrack Live وassets/app.js?v=063 وGemini Live/current ephemeral-token builder. لا يظهر Fish في JS الحالي؛ لا ندّعي أن ملف الصوت المستورد صار صوت كل رد. كود0.1.3 يضيف إعدادات ربط محمية بمفتاح تجريبي، API داخل saas/mobile، foreground service، عميل Gemini WebSocket بالتوكن المؤقت، وجسر PCM محلي authenticated shell. الإصدار0.1.2 نجح بناؤه في run41117754860؛ لا نطلب اختباره منفردًا قبل الربط.

قناة الهوست: أُرسلت مهمة قراءة فقط mobile-bridge-inspect-1793389599248 إلى الفرع الحي؛ لم يصل تقريرها بعد. لا نستنتج أن صلاحية الدخول متاحة حاليًا لمجرد HTTP200، ولا نقرأ config.json أو نعيد تثبيت العامل. النشر الحي غير منفذ حتى يصل مسار التحكم؛ جهز المصدر أولًا.


## أحدث اختبار وإصلاح — 30 أكتوبر2026

المالك أكد أن الطرف البعيد سمع ملف الكلام كاملًا بوضوح، لكن قائمة التسجيلات فارغة والتقرير المحفوظ يظهر فارغًا عند رفعه. سماعه الشخصي للطرف البعيد لا يثبت التقاط التطبيق. وجدنا أن Main.stopRecording في المصدر المثبت يحذف تسجيلات outcome.test عمدًا؛ فات تعديل ذلك في نسخة OnTrack الأولى.

تحديث0.1.2 يحتفظ بالتسجيل كاملًا، ويسلّمه لمسار الحفظ المعتاد، ويضيف زر جمع التسجيل بعد نهاية المكالمة مع عدد الملفات ونتائج الحفظ. تصدير JSON يأخذ لقطة قبل فتح منتقي الملفات، ولا يتجاهل callback بسبب busy، ويقرأ الملف بعد الكتابة للتحقق من البايتات. السبب الدقيق لملف المالك الفارغ غير مثبت، ولا نفترض أنه نفس هذه الاحتمالات. بناء CI واختبار الهاتف مطلوبان. لا تمسح بيانات الإعداد العامل.


## أحدث نتيجة فعلية — 30 أكتوبر 2026

قال المالك: «سمعت صفاره والصوت وصل هناك». سُجل وصول الصوت للطرف البعيد حسب تجربة المالك، وليس مجرد نجاح اقتران أو بناء. هذه نتيجة جزئية إيجابية على Redmi Note12 Pro5G؛ لم يصل تسجيل/تقرير خام، ولم تُحدد نسخة APK أو اتجاه المكالمة أو مسار الصوت. يلزم تأكيد سماع ملف الكلام نفسه بعد الصفارة واستبعاد التسريب الصوتي، ثم مراجعة تسجيل عبارة مميزة يقولها الطرف البعيد.

الحالة الحالية **FAR_END_AUDIO_OWNER_REPORTED_CAPTURE_PENDING**؛ الوصف الأقدم «لم تبدأ أي تجربة صوت» أصبح تاريخيًا. لا نفترض إصلاح صلاحية WRITE_SECURE_SETTINGS أو نجاح0.1.1 دون تقرير. نحافظ على الإعداد العامل ولا نطلب مسح بيانات أو إعادة اقتران. حوار AI مستمر والوارد/الصادر والتعافي ما زالت اختبارات لاحقة. الدليل: [رصد التجربة](../mobile/DEVICE_OBSERVATION_2026_10_30.json).


## أحدث قرار بحثي — 30 أكتوبر 2026

Shizuku وAccessibility وأي شراء هاردوير للعميل وأي عقد/اشتراك اتصالات جديد مستبعدة صراحة. الشريحة والباقة والصوت الموجودون هم الأساس. المرشح الجديد **embedded ADB داخل APK مع مساعد shell**؛ اقتراح مشروط لا يثبت صوت هاتفنا، ولا يزيل قيود المصنع أو الإعداد الأول. بحث المصدر وجد مسار تحية في JemRec، لكنه لا يثبت حوار AI مستمرًا أو استعادة قبل أول unlock.

راجع [التقرير الموسع](../research/2026-10-30/SOFTWARE_SIM_VOICE_RESEARCH_AR.md) و[خطة إثبات الصوت](../research/2026-10-30/AUDIO_PROOF_PLAN_AR.md). الحالة PENDING_DEVICE_AUDIO_PROOF؛ لا تنفيذ تطبيق/هوست أو مكالمات في جلسة البحث.

## أحدث توجيه — مساحة SaaS والميموري

المشروع الجديد مخصص للمسار `/home/appontrack/public_html/saas`، ومصدره في `saas/` بالريبو، مع ترك ملفات الرئيسية كما هي. **الميموري والخطة والسجلات والبرومبت على GitHub فقط خارج مجلد التطبيق**. نطاق الجلسة الحالية تجهيز المساحة والملفات ثم الإبلاغ قبل تنفيذ التطبيق.

اقرأ [CHECKPOINT](../memory/CHECKPOINT.md) و[برومبت التنفيذ](../execution/MASTER_EXECUTION_PROMPT_AR.md) لمعرفة أحدث نتيجة التجهيز ودليلها. طلب المالك أضيف له الرد على القنوات الاجتماعية وTelegram حسبAPIرسمي وصلاحيات كل قناة؛ لا تكاملات جديدة متصلة في هذه الجلسة.

## النتيجة المؤكدة

**تم إثبات التحكم في حساب الاستضافة من هذه المحادثة من خلال GitHub. لم يتم إثبات مرور صوت مكالمة شريحة إلى الإيجنت والعودة منها.**

| الجزء | الحالة | الدليل |
|---|---|---|
| ديمو الصوت PHP/browser | كود موجود v0.6.3؛ المستخدم لديه تجربة عربية سابقة | ملفات الجذر وREADME القديم ونسخة المرفق. لا يوجد اختبار جودة حديث مسجل هنا. |
| استيراد handoff | مواصفات وتصميم v1.3، لا Laravel backend | `reference/agent-builder-v1.3/`. |
| دخول SSH من بيئة هذه الجلسة | فشل قبل المصادقة | Network is unreachable على 65.21.208.232:7195 باسمَي appontrack وai. |
| SSH لدى إيجنت آخر | المستخدم يؤكد أنه يعمل؛ لقطة الأمر موجودة | لقطة Bash؛ لا نعتبرها مخرج SSH ناجحًا صادرًا من جلستنا. |
| قناة GitHub ← الهوست ← GitHub | **VERIFIED_LIVE_CONTROL** | تقرير bootstrap + تقرير exec ناجح. |
| Cron | موجود مرة واحدة، ونفذ أمرنا دون تشغيل يدوي | host_link_cron_count=1، host_link_cron_present=true. |
| قراءة وكتابة ملف | **VERIFIED_LIVE_CONTROL** | connection-check.txt كتب nonce وأعاد نفس النص. |
| SIP/PBX | غير مُجهز/غير مُثبت في هذا المسار | Asterisk غير ظاهر في PATH؛ لم ننشر سنترالًا هنا. |
| Shizuku وVoice SIP Agent على الهاتف | المستخدم جرب إعدادًا ولم ينجح الربط؛ تفاصيل الخطأ غير متاحة | لا نتيجة echo ولا logs ولا إصدارات موثقة حتى الآن. |
| نقل صوت SIM ثنائي الاتجاه | **PENDING_DEVICE_AUDIO_PROOF** | لا اختبار فعلي ناجح حتى الآن. |
| SaaS والجداول والحملات والتقارير | PLANNED | متطلبات وخطة، لا تنفيذ إنتاجي مثبت. |

## بيئة الهوست

- Domain context: `http://app.ontrackegy.com/`، ومسار كود الديمو المؤكد `/home/appontrack/public_html`. لم نتحقق من كامل mapping الدومينات في cPanel.
- Hostname: `server11.hhost.host`، IP: `65.21.208.232`، SSH port supplied by owner: `7195`.
- حساب التحكم الفعلي: `appontrack`، UID `2379`؛ الحساب `ai` ذُكر لاتصال الإيجنت الآخر وليس حساب عاملنا.
- PHP CLI: `/usr/local/bin/php`، `8.1.34`. Python: `/bin/python3`، `3.9.25`.
- Git: `/usr/local/cpanel/3rdparty/lib/path-bin/git`؛ Cron: `/bin/crontab`.
- node/npm/asterisk/ffmpeg/adb لم تظهر في PATH الخاص بالحساب. لا يعني هذا نفي وجودها على السيرفر كله.
- Linux kernel من مخرجات المستخدم: `5.14.0-687.29.1.el9_8.x86_64`، وGID `2383`؛ ليست معلومات فحص kernel جديد منا.
- لم يتم منح root أو إثبات السماح بخدمات صوت طويلة أو منافذ SIP/RTP عامة. لا ترقِّ PHP ولا تثبّت PBX دون التحقق.

## ربط الإدارة الموجود — لا تعِد إنشاؤه

- Repository: `OnTrack-Development/OnTrack-voice`، **Private**، default branch `main`.
- Live control branch: `ontrack-host-link`.
- Local install: `/home/appontrack/ontrack-voice-link`.
- Credential: `config.json` على الهوست فقط، mode 600، لا يُطلب من المستخدم إرساله ولا يُقرأ في تقارير.
- Queue: `ops/host-link/jobs/appontrack-server11.json`، target `appontrack-server11`.
- إثبات التحكم الأساسي: `verify-terminal-20261028234301727`، exit0. أحدث عمليات تجهيزsaas مسجلة مستقلة في CHECKPOINT ولا تلغي هذا الدليل.
- Evidence UTC: `2026-10-28T23:44:02.919046+00:00` إلى `23:44:03.950991+00:00`؛ يقع في 29 أكتوبر بتوقيت القاهرة. زمن التنفيذ 1032ms، ولا يشمل زمن انتظار Cron.
- التقرير: [verify-terminal result](../../ops/host-link/results/appontrack-server11/verify-terminal-20261028234301727.json).
- Worker/installer pinned source: `08363de8125d454e5df3180cab86f0113c947ba6`. نشر bootstrap: `7886478da65ae4d140a295dae70cedd8c0cc9bfa`.
- التوكن اختير بمدة مقترحة 7 أيام؛ **تاريخ انتهاء التوكن الفعلي غير معروف ولم نقرأه**. إذا توقف الربط لاحقًا، تشخيص الصلاحية أولًا، لا إعادة الطلب تلقائيًا.

## الهاتف

المالك حدد Xiaomi/Redmi Note 12 Pro 5G. لا نملك إصدار Android أو MIUI/HyperOS أو إصدار Voice SIP Agent/Shizuku الفعليين. لا يوجد وصول تحكم إلى الهاتف من هوست الحساب. Shizuku مستبعد بتوجيه المالك؛ embedded ADB/shell مرشح بحثي لم نختبره على الجهاز.

## الخطوة التالية الخاصة بإثبات الصوت بعد أمر البدء

1. تثبيت بيانات الهاتف/التطبيقات الفعلية، ثم اختبار ملف الصوت والتقاط الطرف البعيد وفق بروتوكول بحث30أكتوبر، دون Shizuku أو PBX مفروض.
2. حفظ النتيجة الواردة من الهاتف، مع auto-answer والتقاط صوت المتصل وإرجاع الصوت عبر المكالمة؛ اختبار ON/OFF وسجلات لمنع الخلط مع sidetone أو صدى السماعة.
3. إن نجح، نختبر قناة هاتف↔سيرفر↔Gemini كاملة. نحدد خدمة الصوت/PBX وبيئة تشغيلها بناء على الاختبار، ولا نفرض Node/Asterisk أو نبدّل Gemini مقدمًا.
4. إن فشل، نراجع logs والإصدارات وسبب downlink/uplink، ونسجل فشل الجهاز؛ لا نعود لبناء داشبورد pairing ونعتبره حل المكالمة.

تجهيز المستودع في هذا التحديث عمل توثيق وتنظيم فقط، ولا يتضمن نشر تعديل على الموقع أو إنشاء مكالمة أو تغيير إعدادات الهاتف.

## تعديل30أكتوبر2026 — تطبيق الموبايل ومنع Accessibility

المتطلب الحالي أن التطبيق يستقبل الحزمة من الداشبورد ويدير المكالمات والفاصل والدورة محليًا، دون AccessibilityService. [العقد الحالي](../architecture/MOBILE_EXECUTOR_CONTRACT_AR.md) يحدد توزيع المسؤوليات. هذه مواصفة جديدة فقط؛ APK وصوت SIM لا يزالان غير مثبتين. تجهيز المستودع والهوست لا يعني بدء التنفيذ.

## بدء تنفيذ تطبيق الاختبار — 30 أكتوبر 2026

المالك قال: «هات التطبيق ... الريبو ... الصلاحيات ... اتعامل». هذا أمر بدء لتطبيق إثبات صوت Android وAPK، ولا يُطلب تأكيد مكرر. المصدر في saas/mobile/audio-proof/؛ بناء معزول عن هوست PHP. نعيد استخدام JemRec عند commit مثبت مع overlay خاص بـOnTrack، ونحتفظ بالرخصة والإشعارات. لا Laravel أو AI/campaign integration ضمن أول APK. تظل PENDING_DEVICE_AUDIO_PROOF حتى اختبار الهاتف الحقيقي.

## النتيجة الحالية — APK جاهز للاختبار على الهاتف

بُني OnTrack SIM Audio Test v0.1.0 على GitHub بالفعل، ونجحت اختبارات استيراد الصوت ومنطق المساعد بما فيها إلغاء الاختبار، وتجميع APK وفحص التوقيع. نُزلت الحزمة وطابقت SHA-256 ومصدر overlay وmanifest. **لم يحدث تثبيت أو اختبار مكالمة على شاومي بعد**؛ الحالة PENDING_DEVICE_AUDIO_PROOF.

- المصدر: saas/mobile/audio-proof/، commit 9b3120ec1d73ddb8f9d2f835624f3a93fbc54e2c.
- الدليل: docs/mobile/BUILD_STATUS.json، ودليل الاستخدام docs/mobile/AUDIO_PROOF_APP_AR.md.
- البناء: https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/41082229515
- APK داخل artifact OnTrack-SIM-Audio-Test-0.1.0. النسخة debug تجريبية، لا AI/campaign/server binding.
- الخطوة التالية: تثبيت النسخة، إعداد الاقتران المحلي مرة واحدة، استيراد ملف الصوت، ثم وارد وصادر تجريبيان وتصدير النتيجة. أول unlock بعد reboot ما زال قيدًا.
- لم يتغير الهوست أو موقع PHP أو قناته الحية. لا شراء أجهزة أو خدمات ولا Shizuku/Accessibility.
