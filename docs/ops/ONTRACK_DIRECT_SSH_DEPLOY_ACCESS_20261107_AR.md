# OnTrack Voice — وصول SSH مباشر للنشر (تأكيد 7 نوفمبر 2026)

> **مصدر حقيقة للوصول المتاح للـAgent/CI:** القناة المباشرة من Windows GitHub Actions self-hosted runner؛ لا تخلطها مع قناة Host-Link القديمة. هذه وثيقة صلاحيات واتصال **وليست كلمة مرور أو مفتاحًا خاصًا**.

## تحديث مرجع الصلاحيات والماب — 10 نوفمبر 2026

**كل Coding Agent جديد لازم يبدأ** من [Gap Map Current Handoff](../audits/ARCHITECTURE_GAP_MAP_CURRENT_HANDOFF_AR.md)، ثم [SaaS 21 TODO](../audits/SAAS_21_PARTIAL_CLOSURE_TODO_2026_11_10_AR.md)، و`ops/gap-map/status.seed.json`. عند كل Commit ناجح/فاشل، لازم تحديث حالة البنود والفجوات وربط SHA وCI وHost receipt، وعدم اعتماد COMPLETE بمجرد مصدر كود أو اختبار CI.

**مجلد النشر المستقل للماب:** `/home/appontrack/public_html/architecture-map/`؛ **ملفات الحالة والأسرار الخاصة بها:** `/home/appontrack/.ontrack-gap-map/` خارج webroot. لا تستنتج أن هذا المسار منشور من وجود ملف Git؛ الرابط المقترح `https://app.ontrackegy.com/architecture-map/` **UNVERIFIED حتى نجاح SSH upload + HTTP auth/200 مع PIN + SHA receipt**. لا تستخدم المسارات القديمة `/saas/console` للماب.

**طريقة المصادقة للماب:** PIN ستة أرقام اختاره المالك في المحادثة؛ خزّن `password_hash` فقط في ملف خاص `pin.hash` بصلاحية 0600، لا تكتب PIN كنص في source/commit/docs/CI/log. لكل تأكيد فجوة خزّن fingerprint المصدر وتاريخ التأكيد في `reviews.json`، وارجع إلى RECHECK_REQUIRED إذا اختلف. احمِ كل نقاط API بنفس جلسة PIN/CSRF/rate limits؛ امنع نشر `status.json` أو audit ledger للويب مباشرة.

**قناة النشر:** شغّل Job نشر الماب **فقط** من BEDIR Windows Runner عبر SSH المباشر المثبت في هذا الملف؛ لا تستخدم Host-Link (STOP)، لا تطبع مفاتيح أو بيئة runner؛ تحقّق هوية المضيف وSHA بعد upload، ورفض unauthorized HTTPS للزوار. لا ترفع APK ولا تعدل SaaS runtime أو Android أو OTA في Job الماب. صلاحيات الكتابة في `architecture-map/` لم يتم إثباتها مستقلاً بعد، لذلك لا تدعِ النشر حتى تُختبر. `VS/` محذوف وخارج المشروع.

**GAP-7 (Canonical Login URL):** المالك طلب إخفاء `/saas/console` من دخول المستخدم النهائي؛ **تسجيل فقط دون تعديل Routing الآن**. **GAP-8:** تجنب Web Login والطلبات المتسلسلة غير الضرورية في Voice critical path؛ Device Bearer مع تحقق العزل والحصص وDNC ما زالت إلزامية.


## إعدادات الاتصال المثبتة بالتجربة

| البند | القيمة |
|---|---|
| مضيف SSH | `65.21.208.232` |
| المنفذ | `7195` (وليس 22) |
| مستخدم الاستضافة | `appontrack` (UID **2379**) |
| آلة بناء/نشر Windows | Self-hosted runner باسم `BEDIR`، labels: `self-hosted, Windows, X64, ontrack-signing-recovery` |
| هوية SSH | `ed25519`، تعليق `ontrack-deploy-windows-runner` |
| المفتاح **الخاص** | محفوظ فقط لدى مستخدم الـRunner في `%USERPROFILE%\.ssh\ontrack_publish_ed25519`، **لا ترفعه إلى GitHub أو تطبعه في logs** |
| المفتاح العام | `%USERPROFILE%\.ssh\ontrack_publish_ed25519.pub` |
| بصمة المفتاح العام | `SHA256:TSUenA2wPMbtCo6Bd1NMGsQrNIuT6Jb7mKTxgYbHm5M` |
| بصمة توقيع APK المستقلّة | `d25378b9c6d8486749fa64cf1f85a8a2d0b24a599588611392135f54c720ba3f` |
| وضع Host-Link | **STOP** فعّل بطلب المالك، وليس شرطًا للنشر عبر SSH؛ لا تستأنفه من تلقاء نفسك |

المفتاح **العام فقط** المصرّح به على السيرفر:

```text
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHVcg6e/n5lYY0sEdN8kJozmy84RkhHXx+nf3ntFIwgc ontrack-deploy-windows-runner
```

## إثبات اتصال مستقل فعلي

- Workflow `Verify authorized SSH direct host 7195`، [GitHub Actions run 42404419665](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42404419665)، **success**، مع `StrictHostKeyChecking=yes` و `IdentitiesOnly=yes` و `BatchMode=yes`.
- المخرجات من السيرفر: `id -un = appontrack`، `id -u = 2379`، `PUBLIC_HTML_PRESENT`، `OTA_PRIVATE_DIR_PRESENT`، `OTA_NATIVE_DIR_PRESENT`، `MOBILE_WRITE_ACCESS`، `DIRECT_SSH_AUTHENTICATED=True`.
- [GitHub Actions run 42404220488](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42404220488): `UNATTENDED_KEY_VERIFIED=True`، `PUBLIC_KEY_UNCHANGED=True`. المفتاح يمكن استعماله من الـRunner دون إدخال كلمة مرور تفاعلية.
- الصلاحيات المثبتة **محددة بحساب `appontrack` وملفاته**. لا تفترض `root` أو `sudo`، ولا تدّعي تنفيذ عمليات داخل حاوية ChatGPT مباشرة؛ التنفيذ يتم **من الـWindows Runner نفسه**.
- هذا يثبت **الدخول وصلاحية الكتابة على مسار mobile**، وليس أنه نُشر APK أو نجاح مكالمة صوتية E2E.

## مسارات نشر صحيحة

- واجهة SaaS الفعلية (canonical root): `/home/appontrack/public_html/`.
- مجلد تحديث الواجهة الموقّع: `/home/appontrack/public_html/mobile/updates/`، والمؤشر `latest.json`.
- مجلد APK تحديث النظام الأصلي: `/home/appontrack/public_html/mobile/updates/native/`، والمؤشر `latest.json`.
- بيانات خدمات OTA الخاصة: `/home/appontrack/.ontrack-mobile/`، **خارج** `public_html`.
- التوافق التاريخي `/saas/console` أو `/saas/mobile` لا يعني أنه مكان النسخة الرئيسية؛ ابدأ دائمًا بفحص الـrealpath.
- APK الموقّع `0.3.27` / `versionCode=50`: [GitHub Release](https://github.com/OnTrack-Development/OnTrack-voice/releases/tag/ontrack-io-premium-0.3.27-signed-42399912294)، SHA-256 `9c340d1f2ecef3bf3dbc65a59f177382732f89bd76f283d04410cca8b7639689`، حجمه `11954381` بايت. **وجوده في GitHub ليس دليلًا على تفعيل OTA**.

## كيفية التشغيل للـAgent القادم

من خطوة `pwsh` داخل self-hosted Windows Runner المتاح، وليس من الـContainer أو GitHub-hosted Ubuntu:

```powershell
$key = Join-Path $env:USERPROFILE '.ssh\ontrack_publish_ed25519'
ssh.exe -i $key -p 7195 -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 appontrack@65.21.208.232 'id -un; id -u; test -w /home/appontrack/public_html/mobile && echo MOBILE_WRITE_ACCESS'
```

للنقل: `scp.exe -i $key -P 7195 ... appontrack@65.21.208.232:/home/appontrack/.ontrack-mobile/`، ثم تحقّق SHA-256، التوقيع، manifest وطبّق تغيير pointer `latest.json` **في الآخر**. لا تكتب APK مباشرة دون نسخ/فحص واحتفاظ بالنسخة السابقة؛ لا تنشر أسرار مفاتيح Fish/Google.

إذا ظهر `Permission denied (publickey)`، تحقق من **اسم المستخدم `appontrack` وليس `ai`**، المفتاح المخصص داخل نفس حساب Windows Runner، وتفويض المفتاح العام في cPanel. إذا ظهر `Host key verification failed`، افحص بصمة المضيف مع مسؤول السيرفر ولا تعطّل `StrictHostKeyChecking` للتحايل. عند تغيير Runner أو تهيئته قد يلزم إعادة توفير **المفتاح الخاص عبر قناة سرية موثوقة** أو إصدار زوج جديد وتصريح عام جديد من المالك؛ المستودع لا يحتوي أي private key.

## قواعد التسليم

- اقرأ `AGENTS.md` وملفات release gate قبل النشر.
- اختبر syntax/tests والتوقيع، ثم انشر الموقع/صوت IO Premium عند الموافقة، ثم `signed UI` و`native` للمستخدم، وتأكّد HTTP والمقارنة SHA/manifest، وبعدها فقط أعلن نجاح تحديث OTA.
- لا تفترض أن GitHub Actions green يعني أن التحديث وصل للهاتف؛ لا تدّعي E2E صوت SIM دون تجربة الجهاز.
- المالك أوقف جدولة المونيتورينج وHost-Link؛ **لا تفعّل أيًا منهما تلقائيًا**. استخدم الاتصال المباشر الموثّق.


### مسار نشر الـArchitecture Gap Map — أحدث تنفيذ المصدر
- Source publisher: `tools/publish_gap_map_direct_ssh.py`، Python PowerShell Windows BEDIR، لا يطبع PIN أو verifier ويستهدف `/architecture-map/` فقط.
- Workflow: `.github/workflows/saas-21-closure-regression.yml` job `publish-gap-map` يعتمد نجاح `php-security-and-core`.
- **حماية التشغيل:** `ONTRACK_GAP_MAP_DEPLOY_ENABLED` GitHub Actions variable يجب أن يساوي `true`، والـActions secret `ONTRACK_GAP_MAP_PIN` يجب أن يكون 6 أرقام محفوظًا سرًا. غير ذلك يبقى Job تخطي (SKIPPED) أو يفشل بوضوح، ويظل الربط غير منشور؛ لا تخزن الرقم في أي ملف Repo. المالك حدّد PIN في المحادثة، وليس مُتاحًا للنشر من GitHub بدون Secret مُصرّح.
- بعد النجاح فقط: تحقق GET anonymous status=401، صفحة الدخول PIN=200، authenticated status=200، `commit` يساوي SHA، وPrivate `reviews.json` خارج Webroot. **هذا توثيق طريقة نشر لا إيصال بأن النشر حصل**.
