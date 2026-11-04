# أول تجربة محلية — Dograh + صوت مصري (OnTrack Voice)
**التاريخ:** 2026-11-04 | **الحالة:** تعليمات تجربة مقترحة ومراجَعة من المصادر، **لم تُشغَّل بعد على جهاز المالك**.

> **حد ثابت:** لا تعديل لتطبيق Android أو قناة `mix` أو الصوت ثنائي الاتجاه عبر SIM أو مفاتيح الربط أو إعدادات الإنتاج. لا تحويل للمكالمات ولا VPS مدفوع في هذا الاختبار. نبدأ بالمتصفح وملفات صوت افتراضية فقط.

## A. المتطلبات قبل التشغيل

- كمبيوتر Linux أو Windows 10/11 مع Docker Desktop + WSL2. دليل Dograh الحالي: RAM إجمالي **8GB**، يُفضّل إتاحة **4GB لـDocker**، ومساحة حرة **10GB على الأقل**، ونواتان CPU (هذه أرقام لتشغيل منصة Dograh لا لـTTS المحلي الثقيل).
- اتصال إنترنت لتنزيل Docker images وأوزان النماذج؛ ميكروفون وسماعة. لا تُستخدم تسجيلات عملاء أو بيانات حسابات حقيقية.
- أنشئ مجلدًا جديدًا خارج مستودع OnTrack؛ لا تشغّل التجربة على سيرفر إنتاج `app.ontrackegy.com`.

## B. تجربة Dograh مستقلة أولًا (بدون SIM)

**Linux / WSL2** (راجع السكربت والـCompose قبل التنفيذ):
```bash
mkdir -p ~/ontrack-dograh-lab && cd ~/ontrack-dograh-lab
curl -fsSLo docker-compose.yaml https://raw.githubusercontent.com/dograh-hq/dograh/main/docker-compose.yaml
curl -fsSLo start_docker.sh https://raw.githubusercontent.com/dograh-hq/dograh/main/scripts/start_docker.sh
chmod +x start_docker.sh
./start_docker.sh
```
**Windows PowerShell** (في مجلد تجريبي وبوجود Docker Desktop/WSL2):
```powershell
New-Item -ItemType Directory -Force ontrack-dograh-lab | Out-Null
Set-Location ontrack-dograh-lab
Invoke-WebRequest -OutFile docker-compose.yaml https://raw.githubusercontent.com/dograh-hq/dograh/main/docker-compose.yaml
Invoke-WebRequest -OutFile start_docker.ps1 https://raw.githubusercontent.com/dograh-hq/dograh/main/scripts/start_docker.ps1
.\start_docker.ps1
```
**تنبيه أمني:** سكربت البدء الرسمي يفتح signup وتانل Cloudflare عامًا مبدئيًا، ويُفعّل telemetry افتراضيًا. هذه مناسبة للتجربة فقط دون بيانات عملاء؛ لا تترك الخدمة مكشوفة. لتشغيل محلي فقط بعد أن تُنشئ `.env`: أوقف مجموعة التجربة بملف compose (بما فيها `--profile tunnel`) ثم شغّل `docker compose up -d` **دون** profile tunnel، وأغلق التسجيل العام بعد إنشاء حساب الاختبار. راجع تعليمات Dograh الرسمية الكاملة لتفاصيل إعداد telemetry والـtunnel.

- افتح `http://localhost:3010`، أنشئ Agent بدور خدمة عملاء، ثم `Test Agent → Test Audio → Run Test → Start Call`.
- جرب 10 جمل صناعية مصرية: «ألو، حد سامعني؟»، «عايز أستفسر عن استضافتي»، «ممكن تحوّلني لموظف؟»، «اسمي أحمد»، «متستعجلش عليّا»، «ممكن تعيد آخر معلومة؟»، «واحد اتنين تلاتة»، وجمل أرقام/صمت/مقاطعة متدرجة.
- سجّل: هل الصوت رايح جاي في المتصفح، هل التفريغ مفهوم، هل توقف الرد عند المقاطعة، هل Agent flow انتقل من عقدة لأخرى، وأي تأخير واضح.
- اختبار المتصفح هنا يختبر **Dograh/المحرك فقط**، وليس SIM أو قدرتنا على التقاط صوت Android.

**المصدر:** [Dograh official self-host Docker guide](https://www.dograh.com/hub/blogs/self-host-voice-agent-docker).

## C. تجربة صوت مصري مستقل Habibi-TTS — نسخة EGY المتخصصة

اختيار أول للبحث: [SWivid/Habibi-TTS](https://github.com/SWivid/Habibi-TTS). **مهم جدًا للترخيص:** الكود MIT، ونموذج `Specialized/EGY` تحديدًا مذكور بترخيص Apache 2.0؛ أما `Unified` الافتراضي فمذكور بترخيص **غير تجاري** CC-BY-NC-SA. لا تستخدم `Unified` كبديل إنتاجي تجاري؛ راجع ترخيص أي ملفات أوزان/تسجيلات فعلية.

على Linux أو WSL2 داخل virtualenv مستقلة **بشرط توفّر مساحة وذاكرة تكفي للتجربة**:
```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install habibi-tts
habibi-tts_infer-cli --help
```
أنشئ `egy-test.toml` بتجربة **Specialized** بدل Unified (صوت المرجع يجب أن يكون مسجلًا بموافقة صاحبه، ويطابق `ref_text`):
```toml
model = "Specialized"
dialect = "EGY"
ref_audio = "egyptian_reference.wav"
ref_text = "أهلًا بيك في أون تراك، إزاي أقدر أساعدك؟"
gen_text = "أهلًا بحضرتك، تحب أساعدك في الاستضافة ولا عندك مشكلة في الخدمة؟"
gen_file = ""
remove_silence = false
output_dir = "results"
output_file = "egy_test_01.wav"
```
ثم:
```bash
habibi-tts_infer-cli -c egy-test.toml
```
**لا تزعم أن الأمر نجح** قبل تشغيله؛ يمكن ظهور عوائق CPU/dependency/نفاد RAM. جرّب النموذج **منفصلًا عن حاويات Dograh** أولًا على 8GB حتى لا تتنافس الذاكرة. هذه تجربة تصنيع WAV كامل وليست بعدُ benchmark لزمن أول chunk في المكالمة.

**المصادر:** [README الرسمي والترخيص المميّز لكل نموذج](https://github.com/SWivid/Habibi-TTS) · [Example TOML الأصلي](https://github.com/SWivid/Habibi-TTS/blob/main/src/habibi_tts/infer/example.toml) · [CLI source](https://github.com/SWivid/Habibi-TTS/blob/main/src/habibi_tts/infer/infer_cli.py).

## D. التقييم والانتقال — دون تعديل OnTrack العامل

1. **خلفية:** احصل على قياس baseline من OnTrack Gemini Live الحالي لاحقًا من مكالمة اختبارات المالك المصرّح بها. لا تحوّل تجربة المتصفح إلى دليل SIM.
2. **جودة الصوت:** لكل عينة دوّن وضوح النطق المصري، الأرقام والأسماء، اللكنة، ومقارنة الأصوات.
3. **سرعة وأجهزة:** دوّن توليد WAV بالثواني، وقت الصوت الناتج، realtime factor = زمن التوليد / طول الصوت، وأعلى استخدام RAM/CPU. لاحقًا لو أمكن عمل TTS streaming/API قِس `time-to-first-audio` وp50/p95 لا معدل التوليد فقط.
4. **Workflow:** قارن Dograh في مقاطعة المستخدم، VAD، ترحيل agent، تسجيل ما سُمِع فقط، وإدارة الذاكرة مع OnTrack؛ أي أفكار نافعة توضع خلف feature flag تجريبي.
5. **بعد النجاح فقط:** adapter تجريبي يعمل ببيانات اصطناعية ويُحافظ على مسار `Android SIM RX/TX`. مكالمة حقيقية لاحقًا من جهاز المالك مع موافقته وبلا تغيير اقتران أو حذف بيانات.

## E. بطاقة نتيجة التجربة (تُملأ بالأدلة، لا بالتوقعات)

| البند | القيمة الفعلية |
|---|---|
| نظام التشغيل / CPU / RAM / GPU | لم يُختبر بعد |
| نسخة Dograh / تاريخ Git SHA أو docker image tag | لم يُختبر بعد |
| تشغيل Docker + Web test | لم يُختبر بعد |
| Habibi Specialized EGY: تنزيل وتشغيل | لم يُختبر بعد |
| طول WAV بالثواني / زمن إنشائه / RTF | لم يُختبر بعد |
| أعلى استخدام RAM / CPU | لم يُختبر بعد |
| جودة النطق المصري وأرقام التليفونات | لم يُختبر بعد |
| latency أول chunk / مقاطعة / تبديل Agent | لم يُختبر بعد |
| مقارنة Gemini Live الحالية | لم يُختبر بعد |
| مرور صوت مكالمة SIM الحقيقية بعد أي دمج | **لم يحدث دمج ولم يُختبر** |

**متابعة النتائج:** [GitHub Issue #37](https://github.com/OnTrack-Development/OnTrack-voice/issues/37). لا تُرفع عينات أصوات خاصة أو بيانات عملاء أو مفاتيح إلى Git.
