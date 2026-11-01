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

## تحقيق ما بعد النشر — دليل حقيقي من السيرفر

- **PR #15 MERGED**: `72a600d47a3c5742d9afe04ba925dac81d365a93`؛ CI `41316042137` PASS. لكن أول نشر `41316076748` FAILED قبل إنشاء artifact لأن `auth.sha256` موجود في repo. لم يصل للسيرفر.
- **PR #16 MERGED**: `ed2d75e4d720d81d8ecdd14f0f72c305279b776e`؛ CI `41316217344` PASS؛ نشر `41316251942` نجح في إنشاء artifact واستدعاء Cron، لكن **host receipt FAILED**: `pilot-server-41316251942` exit1 `unsafe_target_directory`. لا PHP نشر.
- **سبب مثبت بقراءة الهوست دون أسرار**: job `pilot-path-probe-20261101142825` = SUCCESS. المسار `/home/appontrack/public_html/saas/mobile/server` غير موجود؛ الملفات PHP منشورة مباشرة في `/home/appontrack/public_html/saas/mobile` وبجانبها `auth.sha256` و`updates/`.
- **PR #17 CANDIDATE**: تصحيح هدف النشر إلى المسار الحقيقي، واستبدال نقل الدليل بالكامل بـPHP-only atomic overlay وbackup/rollback، مع اختبار mid-write rollback. لا تعديل على APK/OTA أو بيانات المكالمات.
- **HOST_DEPLOYED لا يزال OPEN** حتى receipt ناجح لنفس source SHA، و**DEVICE_E2E** لا يزال OPEN.


## النتيجة النهائية — 2026-11-01 14:34 UTC

- **PR #17 MERGED**: `https://github.com/OnTrack-Development/OnTrack-voice/pull/17`, main `cf2988c7052bddc90df017f7ac9773814f11400b`.
- **CI_PASS:** PR `41316597775` وSaaS CI `41316597803` = SUCCESS؛ post-merge publish `41316660497` = SUCCESS.
- **HOST_DEPLOYED VERIFIED:** Cron receipt `ops/host-link/results/appontrack-server11/pilot-server-41316660497.json`: SUCCESS exit0 عند **2026-11-01 14:33:05 UTC**، المصدر `cf2988c7052bddc90df017f7ac9773814f11400b`، SHA256 `fd5e1bca8febfd38e66b36c32ab98d752cf2ee7feabe3df71f84509ba6b5a8ed`، **20 ملف PHP**، `auth_hash_preserved=true`، `unrelated_files_untouched=true`، نسخة rollback خاصة في `/home/appontrack/.ontrack-mobile/pilot-server-backups/cf2988c7052b`.
- **HOST READ-ONLY VERIFY:** Cron receipt `pilot-verify-20261101143317.json`: SUCCESS exit0 عند **2026-11-01 14:34:04 UTC**، 20 ملف PHP بلا أخطاء lint، pre-dial/DNC/campaign endpoint موجودة، `auth.sha256` و`updates/` موجودان؛ لم يُقرأ محتوى أسرار أو يُشغّل اتصال.
- **DEVICE_E2E_VERIFIED: OPEN**؛ وجود كود DNC على الهوست لا يثبت أن Android منع مكالمة SIM فعلية. **MULTI_TENANT_SIM: OPEN**؛ pilot ما زال منفصلًا عن SaaS tenant binding.
- **Security follow-up:** وجود `auth.sha256` في source repository مثبت من metadata، وتم استبعاده تمامًا من حزمة النشر؛ لاحقًا يجب تقييم نقله خارج المستودع وتدوير مفتاح الجهاز وفق خطة ربط الأجهزة دون كسر التطبيق.
- **NEXT:** Android no-dial E2E عند توفر الجهاز، مع قياس lease→verify→dial race، ثم ربط الجهاز والشريحتين بحسابات SaaS. PR #9 SSO لم يُدمج.
