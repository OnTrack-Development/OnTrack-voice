# دورة التطوير #8 — نشر PHP الخاص بالهاتف دون APK
**التاريخ:** 2026-11-01  
**المهمة:** إغلاق فجوة HOST_DEPLOYED لإصلاح DNC pre-dial في PR #13.  
**PR:** #15 — https://github.com/OnTrack-Development/OnTrack-voice/pull/15

## المشكلة ودليلها
- كود `saas/mobile/server/pilot_workspace.php` المحمي من الاتصال بأرقام DNC موجود في `main` واختبرته CI في PR #13، لكنه لم يكن جزءًا من workflow نشر SaaS console.
- تشغيل Android `41306044730` نشر native APK رغم فشل UI OTA، وأُغلق خلل gate في PR #14. **نشر APK ليس بديلًا عن نشر PHP**.
- أحدث queue عند بدء الدورة `mobile-native-41306044730` له host receipt SUCCESS، ولا توجد PR أخرى تعدل مسار نشر PHP؛ PR #9 خاص بالـSSO ويظل مفتوحًا.

## التنفيذ الفعلي
- `tools/deploy_mobile_server.py`: تحقق SHA-256 من حزمة immutable، allowlist للـPHP و`.htaccess` فقط، منع path traversal/symlink/duplicate، PHP 8.3 lint قبل النقل، نقل الدليل ككتلة واحدة مع backup خاص وrollback عند الفشل، الحفاظ على `auth.sha256` والملفات المحلية دون طباعتها.
- `tools/queue_mobile_server.py`: يستخدم نفس host-link Cron worker، ويتأكد من إغلاق job السابق قبل الكتابة؛ التقرير يربط run وcommit وSHA.
- `.github/workflows/mobile-server-publish.yml`: PR tests ثم push-to-main نشر PHP-only من artifact ثابت؛ لا Android APK ولا UI OTA.
- `tools/test_mobile_server_deploy.py`: اختبارات من دون شبكة لـchecksum/UID/traversal/symlink/duplicate/invalid PHP/auth/rollback/replay.

## حدود الإثبات
- **CODED:** branch PR #15.
- **CI_PASS:** في انتظار نتيجة workflow النهائية بعد تحديث الوثائق.
- **HOST_DEPLOYED:** في انتظار receipt `pilot-server-<workflow_run_id>` من `ontrack-host-link`؛ نجاح GitHub publish يعني queued فقط.
- **DEVICE_E2E_VERIFIED:** غير مثبت؛ لا مكالمات فعلية في هذه الدورة.
- **NEXT:** تحقق receipt النشر ثم راجع حظر DNC على الجهاز والتوافق متعدد المستأجرين. UI OTA revision collision وSSO PR #9 مفتوحان منفصلين.
