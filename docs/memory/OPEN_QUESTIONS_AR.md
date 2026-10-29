# أسئلة مؤجلة تحتاج قرار المالك

> تُجمع أثناء التنفيذ دون إيقاف العمل، وتُعرض على المالك في نهاية الجلسة.

## 1. عاجل — استعادة قناة التحكم (BLOCKED_EXTERNAL)
توقف العامل عن سحب المهام بعد تعديل الـcron. التفاصيل في `docs/memory/INCIDENT_2026-10-29_CONTROL_CHANNEL_AR.md`.
**القرار المطلوب:** تنفيذ سطر الاستعادة من cPanel Terminal، أو تزويد جلسة التنفيذ بمفتاح SSH صالح لـ`appontrack@server11.hhost.host` (port 7195).

```sh
(crontab -l 2>/dev/null | grep -v 'ontrack-voice-link'; echo '* * * * * /bin/python3 /home/appontrack/ontrack-voice-link/worker.py >/dev/null 2>&1 # ontrack-voice-link'; echo '* * * * * /bin/bash /home/appontrack/bin/run-queue.sh >/dev/null 2>&1 # ontrack-saas-build') | crontab - && crontab -l
```

## 2. قاعدة البيانات
- الحساب `appontrack` لا يملك بيانات اعتماد MySQL (`Access denied` بدون كلمة مرور، ولا `~/.my.cnf`).
- **القرار المطلوب:** إنشاء قاعدة MySQL مستقلة للتطبيق (اسم + مستخدم + كلمة مرور) عبر cPanel، أم البدء بـ SQLite مؤقتًا داخل `saas` مع التوثيق أنه غير مخصص للإنتاج؟

## 3. النطاق العام للمنصة
- `app.ontrackegy.com` يستضيف ديمو Voice الحالي (dev_bridge يعمل عليه).
- **القرار المطلوب:** النطاق النهائي للمنصة (مثال: `saas.ontrackegy.com`)؟ وهل نُبقي الديمو في `app.ontrackegy.com` بلا تغيير؟

## 4. مفاتيح AI للمنصة
- حسب القرار الحاكم: OnTrack فقط تضيف AI providers من `/control/*`.
- **القرار المطلوب:** هل نملك الآن مفاتيح Google/Custom لتفعيل اختبار حقيقي، أم نبني الواجهة والتشفير ونترك الحالة `PENDING_CREDENTIALS` حتى تُدخل من `/control` فقط؟