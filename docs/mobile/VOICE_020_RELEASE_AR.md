# OnTrack Voice v0.2.0 — Device & Server Control
**31 أكتوبر 2026 | مرحلة التنفيذ والتجربة الميدانية**

## قرار المنتج
- OnTrack Development مزوّد الخدمة، والعميل لاحقًا يستخدم لوحة SaaS مركزية دون تثبيت Backend.
- التطبيق على Android مجرد Phone/SIM Connector محلي للأجهزة المستضافة/المُدارة.
- لا Accessibility أو Notification Listener أو كشف مفاتيح في روابط الإدارة أو سجلات المكالمات.
- خطوط SIM الحالية لا تثبت دعم التحكم على كل OEM أو القدرة على التوسع لمليون مكالمة متزامنة.

## تم التنفيذ في GitHub
1. Cloud desired state: `saas/mobile/server/control.php` يستقبل device Bearer authenticated toggles ويحدث `settings.enabled/revision` تحت قفل `control.lock`، لا يغير إعدادات الصوت أو `mix`. لوحة `index.php` تستخدم نفس القفل. `status_feed.php` تعرض desired مقابل latest acknowledged device status.
2. Device implementation: `MobileLinkService.kt` يبقي foreground connector شغالًا حتى لو الإيجنت disabled، ويراقب الحالة كل 3 ثوانٍ، ويرفع applied/phase كل نحو 4 ثوانٍ. أوامر التطبيق والإشعار والفقاعة تتحول إلى cloud control، والـAI يتوقف في الحال محليًا عند OFF مع احتفاظ server-retry pending حتى وصول الشبكة. إذا كان السيرفر OFF فلا يرد تلقائيًا.
3. Recovery: `ConnectivityManager.registerDefaultNetworkCallback` مع callback onAvailable/onLost وإعادة محاولة exponential backoff 2→30 ثانية مع wake على انتقال الشبكة. `CaptureDaemon.revive()` يحاول استعادة اتصال helper بعد انتقال Wi-Fi؛ **لا يستطيع إعادة تفعيل Wireless Debugging أو الاقتران إذا منعتهما Android/OEM أو أعاد النظام ضبطهما**.
4. Current call human handoff: `ACTION_TAKEOVER` يوقف جلسة AI/PCM دون إنهاء Telecom call؛ `takeoverRequested/humanControl` يستمران للمكالمة الحالية حتى IDLE أو رنين جديد موثوق، حتى بعد network exception. المكالمات الجديدة تستعيد سياسة سيرفر OnTrack.
5. OnTrack overlay: `CallOverlay.kt` شريط صغير قابل للسحب والتوسيع، زران جنب بعض للاستلام والسماعة، وزر تشغيل/إيقاف أسفل؛ إذن overlay اختياري `SYSTEM_ALERT_WINDOW` ولا Accessibility. `CallOverlay` له fallback إشعار Android.
6. Onboarding: `ProofActivity.kt` يستبدل الاختبارات القديمة بمعالج تشغيل من مرحلتين (فتح إعدادات Android حسب Developer status/OEM، ثم الاقتران). لا يحاول تجاوز الموافقة على إعدادات Android المحمية.
7. OnTrack identity: شعار OT باللون الأحمر، خلفية داكنة، اسم OnTrack Voice/OnTrack Development، واجهة OTA6 ولوحة الويب.
8. Admin SSO: `admin_ticket.php` يطلبه Android بمفتاح الربط في Authorization Header فقط، ويولّد ticket عشوائيًا عمره 35 ثانية في تخزين خاص. `admin_enter.php` يستهلكه مرة واحدة ويفتح جلسة إدارة HTTPS مع cookies secure/httponly. المفتاح الأصلي لا يدخل URL ولا يُعاد إدخاله في واجهة الإدارة.
9. Android versionCode=11, versionName=0.2.0-ota, OTA revision=6 / min_native=11؛ حُفظت نفس شهادة التوقيع الموجودة على الجهاز حتى لا نخسر الاقتران.

## المثبت فعليًا على الاستضافة
- مهمة `mobile-020-auth-deploy-20261031` نجحت: `control.php`، `admin_ticket.php`، `admin_enter.php`، `status_feed.php`، PHP lint PASS، نسخة احتياطية وعدم قراءة secrets.
- مهمة `mobile-020-admin-sync-20261031` نجحت: تعديل `index.php`، فحص PHP PASS، نسخة احتياطية.
- فحص HTTP غير الموثق يفترض أن يرفض الدخول إلى control/ticket/status feed؛ نتيجة التقرير `mobile-020-live-security-verify` تُراجع لاحقًا.
- **مهم:** تطبيق v0.1.9 القديم لن يدعم sync الجديد أو SSO؛ لا تُعرض هذه الميزات على أنها اختُبرت على الهاتف حتى تثبيت APK v0.2.0.

## اختبار قبول الهاتف (real device)
1. فوق v0.1.9 ثبت v0.2.0 الموقعة بنفس الشهادة دون Uninstall، مع بقاء المفتاح والربط.
2. من اللوحة شغّل AI → شاهد desired=on/device=on/revision متطابق؛ من الهاتف أوقفه → تحقق أن اللوحة تُظهر OFF، وأعد تشغيله من أي طرف.
3. أثناء مكالمة حقيقية اضغط `استلام المكالمة`: صوت AI ينتهي، الميكروفون يرجع للمستخدم؛ المكالمة لا تُغلق. المكالمة التالية يرد عليها AI بعد سياسة الـ5 ثوانٍ ما لم يكن موقوفًا مركزيًا.
4. امنح/ارفض إذن overlay، جرّب ترتيب الأزرار/التحريك، تحكم من الإشعار عند غياب الإذن.
5. جرّب زر السماعة، تحقق من مسار الصوت فعليًا. قد لا يدعمه بعض OEM؛ لا نعده ناجحًا قبل التجربة.
6. وصّل Wi-Fi A ثم افصل ووصل Wi-Fi B، وجرّب انقطاع قصير وأطول ومكالمتين. يجب أن يعود heartbeat والاتصال بالـAI تلقائيًا إن ظل Wireless Debugging والاقتران صالحين.
7. من التطبيق اضغط لوحة الإدارة، تحقق من عدم طلب مفتاح جديد، ومن فشل تكرار نفس ticket بعد استهلاكه.
8. تأكد من تحديث UI OTA6، وأن شاشة الإعداد الجديدة تحتوي خطوات الإعداد ولا تعرض أدوات التسجيل والاختبارات القديمة.
9. لغة/مقاطعات: جمل قصيرة بالمصري، لا يعيد الكلام بعد interruption، ويتابع السياق، مع المقارنة بعد 10 مكالمات وليس مكالمة واحدة.

**معيار الإفراج:** CI signed APK + WebView test pass + HTTP host verification + real handset call acceptance. النجاح الجزئي ليس Production Support لكل الأجهزة.
