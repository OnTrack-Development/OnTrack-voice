# V66 — استحقاق الصوت عند تعديل باقة ريسيلر (8 نوفمبر 2026)

## العطل
`plan_catalog_save_reseller()` يحافظ على التاريخ المالي الصحيح عبر أرشفة نسخة الباقة المستخدمة وإنشاء نسخة جديدة عند تعديلها. لكن `tenant_voice_entitled()` كان يشترط `p.status='active'` في كل طلب جلسة صوتية، فيرفض اشتراك عميل ما زال `active` وداخل فترته المدفوعة لمجرد أن نسخة الباقة أصبحت `archived`.

## الإصلاح المنشور
- أزيل شرط حالة **كتالوج** الباقة من استعلام استحقاق **الاشتراك** في `saas/console/_core/TenantVoiceSession.php` فقط.
- بقيت شروط `accounts.status='active'` و`subscriptions.status='active'` و`starts_at<=now()<ends_at` إلزامية، مع الحفاظ على عزل الحساب.
- لم يتغير شرط أن **المبيعات الجديدة** تستخدم الباقات النشطة فقط؛ تعديل الباقة لا يلغي حق مشتركي النسخة القديمة.
- اختبار `tools/test_reseller_subscription_catalog.php` يتحقق بعد تعديل الباقة من سماح الصوت والحصة التاريخية، ورفض انتهاء المدة والإلغاء والتعليق، ثم استعادة الحق للحساب السليم.

## الإثبات
- GitHub [run 42449682572](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42449682572) — **SUCCESS**، SHA `410dddeacebca22e515e482d0fe4dd8052e8cfd2`. ستة اختبارات معزولة شملت استحقاقات الخطط وCheckout وPairing وGoogle Live failover.
- نشر SSH مباشر من Windows runner `BEDIR` إلى الجذر `/home/appontrack/public_html/` عبر Patch لملف PHP واحد فقط: `V66_LIVE_ARCHIVED_PLAN_ENTITLEMENT_PATCH_PASS`، و`changed=true`.
- PHP 8.3 lint على الاستضافة: `V66_LIVE_PHP83_VOICE_ENTITLEMENT_SYNTAX_PASS`. فحص تسجيل دخول HTTPS: `V66_LIVE_SINGLE_FILE_PATCH_LOGIN_SMOKE_PASS`.
- نسخة استرجاع خاصة: `/home/appontrack/.ontrack-mobile/saas-entitlements-v66/backup-1794114636-2382504/TenantVoiceSession.php`.
- **لا تغيير** في صفوف العملاء/الاشتراكات أو بيانات الدفع أو مفاتيح المزودين أو APK/OTA/SIM. لم يُعد تشغيل Host-Link أو أي Cron قديم.

## الحدود والباقي
- تثبت اختبارات CI السلوك بقاعدة معزولة. نشر الكود وفحصه مثبتان، لكن مكالمة SIM فعلية لم تُستخدم كاختبار لهذه الدفعة.
- ما زالت بنود المرحلة الأولى الأخرى مفتوحة: التجديد المدفوع واحتساب المدة، سياسة تعليق الريسيلر وأثرها على العملاء، وموثوقية استرداد المهام والإرسال المتكرر.
