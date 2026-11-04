# بحث Dograh وOskot — مرجع محرك الصوت لمشروع OnTrack Voice

**آخر تحديث:** 4 نوفمبر 2026 — Africa/Cairo  
**الحالة:** بحث تقني موثق، وليس دمجًا أو نشرًا أو اختبار مكالمة جديدة.  
**طريقة بدء التجارب:** [دليل الاختبار المحلي Dograh + Habibi EGY](DOGRAH_FIRST_LOCAL_TEST_2026_11_04_AR.md) — لم تُنفَّذ التجارب بعد.  
**ثريد GitHub للمتابعة:** [Issue #37 — Dograh/Oskot](https://github.com/OnTrack-Development/OnTrack-voice/issues/37)  
**المصادر:** تحليل APK السابق من هذه المحادثة، وكود Dograh العام وملفات المشروع وروابطها أدناه.

> **قرار المالك الملزم:** لا نغيّر مسار OnTrack Voice الحالي. المكالمة تتم من شريحة Android الحقيقية، ويستقبل النظام صوت المتصل ويعيد صوت الوكيل إلى نفس المكالمة. **لا Call Forwarding، ولا استبدال لمسار الشريحة بـ SIP/WebRTC/Twilio/LiveKit، ولا إعادة ربط الشريحة أو حذف بيانات الاقتران.** الهدف دراسة تقنيات صوت/Workflow قابلة للإضافة إلى المعمارية الحالية فقط.

## 1. ملخص تنفيذي

- **Dograh** منصة تنظيم Voice AI مفتوحة المصدر فوق **Pipecat** وليست نموذج تدريب أصوات مستقلًا؛ تدعم مسار **STT → LLM → TTS**، ومسارات صوت حيّ speech-to-speech ومنها Gemini Live، وWorkflow Graph وتنفيذ انتقالات الوكلاء. [README الرسمي](https://github.com/dograh-hq/dograh/blob/main/README.md).
- **Oskot v1.1.4** الذي فُحص سابقًا يستقبل مكالمات **محوّلة** إلى أرقام تديرها خدمته، ويستخدم WebRTC لاختبارات الميكروفون داخل التطبيق؛ لذلك ليس إثباتًا لحل التقاط صوت مكالمات SIM داخل Android كما يفعل OnTrack. ملاحظة: هذا يلخّص الفحص السابق، وليس إعادة فحص ملف APK في جلسة التوثيق.
- في الفحص السابق وُجد **تشابه/تطابق في مسارات WebRTC وworkflow IDs** بين نصوص اختبار Oskot و[واجهة Dograh التجريبية](https://github.com/dograh-hq/dograh/blob/main/ui/public/embed/dograh-widget.js). **لا يثبت ذلك** اعتماد خدمة Oskot الإنتاجية الكاملة على Dograh أو مصدر أصواتها أو طريقة تدريبها.
- الأكثر فائدة لـOnTrack هو **فصل transport عن workflow + تعقب التشغيل الحقيقي + المقاطعات + turn detection + إدارة ذاكرة/جلسة الـAgent + TTS cache**؛ وليس أخذ طبقة telephony الخاصة بـDograh.
- نريد **أصواتًا مصرية مملوكة/مستضافة لدينا** بلا رسوم TTS لكل دقيقة عند Google أو ElevenLabs؛ Dograh يسهّل تبديل الخدمات، لكنه **لا يزوّد تلقائيًا صوتًا مصريًا مخصصًا مدربًا ولا يجعل كلفة الاستضافة صفرًا**.

## 2. الموجود في كود Dograh — أدلة مباشرة

| المكوّن | ما تؤكده ملفات الكود | قيمة عملية في OnTrack |
|---|---|---|
| Pipeline | `pipeline_builder.py` يربط `transport.input → STT → user context → generation stage → transport.output → recording → assistant context → metrics`. ويجعل توليد الرد Pipeline مستقلة لكل زيارة Agent. | فصل صوت الشريحة عن توليد الرد مع الاحتفاظ بقناة المكالمة. |
| Agent Workers | `agent_bridge.py` و`agent_runtime_factory.py` يفصلان worker الإيجنت عن الـcall worker. | تقييم تبديل الإيجنت/العقدة دون إعادة ربط الخط؛ يحتاج اختبار إدارة state الفعلية عندنا. |
| الصوت المباشر | `realtime/gemini_live.py` يدمج Gemini Live مع workflow، ويؤخر الاتصال إلى وصول `system_instruction`، ويعيد الاتصال عند تغيير تعليمة العقدة مع حفظ السياق. | الانتباه لتوقيت تهيئة الجلسة ونقل prompt/context عند تغيير Flow. **لا نتبنى إعادة الاتصال إذا عطّلت مكالمة الهاتف.** |
| كشف الكلام والمقاطعة | `run_pipeline.py` يستخدم Silero VAD (`stop_secs=0.2` في إعداداته الحالية) وSmart Turn V3 وسياسات توقف/بدء مختلفة لكل provider، ويفصل signal الكلام عن قرار المقاطعة. | تقليل التقطيع والمقاطعة الكاذبة: لا نشغّل VADين متنافسين فوق Gemini Live. |
| حدود تشغيل الكلام | `speech_playback.py` يتتبع audio queued/played، ويعرف outcome `PLAYED/INTERRUPTED/TIMED_OUT/CLOSED` ويحسب الجزء المسموع بالفعل. | لا نسجّل كلامًا لم يسمعه العميل عند تدخل المتصل أو تحويل المكالمة لموظف. |
| مراقبة المكالمة | `call_monitor_processor.py` يراقب صمت المستخدم واستجابة الوكيل وسقف المكالمة بمعزل عن تعطل عملية توليد الرد. | Watchdog حقيقي مستقل عن الصمت العادي. |
| عينات الصوت | `audio_config.py` يضبط معدلات transport وVAD/pipeline ومزامنة إعادة التعيين؛ مسار pipeline محدد داخليًا عند 16 kHz كحد أعلى لهذا الإعداد. | تقليل أخطاء resampling التي تؤدي لتقطّع أو تسارع/تباطؤ الصوت. |
| WebRTC | `transport_setup.py` يعرّف SmallWebRTC مع دخل/خرج صوت؛ كود الهاتف منفصل في `telephony/providers/`. | تجربة WebRTC متاحة للـbenchmark **فقط**، وليس بدل شريحة Android. |
| TTS cache | `tts_cache/` يستخدم Redis اختياريًا: TTL افتراضي 86400 ثانية، حد 1 MiB للعنصر، مهلة عملية 20 ms، ومبدأ fail-open عند فشل الكاش. | كاش للتحيات والعبارات المتكررة فقط، دون تجميد المحادثة إذا فشل Redis، مع عزل كل tenant/voice. |
| الصوت المحلي | `api/native/rnnoise/` يحتوي مكتبات RNNoise، وكود platform يشير إلى providers مستقلين لـSTT/TTS وLLM. | دراسة تنقية الضوضاء والتبديل بين محركات محلية/مدفوعة؛ وجود ملفات المكتبة **لا يثبت تفعيلها تلقائيًا في كل مكالمة**. |

**روابط الملفات التي روجعت (المسار على `main` وقت الفحص، وقد يتغير):**
- [pipeline_builder.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/pipeline_builder.py)
- [run_pipeline.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/run_pipeline.py)
- [audio_config.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/audio_config.py)
- [gemini_live.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/realtime/gemini_live.py)
- [speech_playback.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/speech_playback.py)
- [call_monitor_processor.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/call_monitor_processor.py)
- [tts_cache/cache.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/tts_cache/cache.py) · [tts_cache/models.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/tts_cache/models.py)
- [transport_setup.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/transport_setup.py)
- [service_factory.py](https://github.com/dograh-hq/dograh/blob/main/api/services/pipecat/service_factory.py)
- [workflow/pipecat_engine.py](https://github.com/dograh-hq/dograh/blob/main/api/services/workflow/pipecat_engine.py)

## 3. Oskot v1.1.4 — مقارنة دون خلط طرق المكالمات

**نتائج مسجلة من تدقيق APK السابق:** تطبيق Kotlin/Jetpack Compose، HTTPS/OkHttp، Firebase Messaging، Google Play Billing، Media3 لتشغيل التسجيلات، واختبار صوت WebRTC؛ ظهر عنوان API لخدمة التطبيق `https://oskot-production.up.railway.app/api`. لم يظهر في الحزمة ما يؤكد وجود نماذج AI صوت محلية أو مسار التقاط SIM مشابه لمسار OnTrack. شروط التطبيق التي فُحصت تشير إلى أرقام Call Forwarding ومكالمات واردة محوّلة.

**حدّ الدليل:** API على Railway لا يعني أن الـAI نفسه على Railway؛ التطابق مع WebRTC widget في Dograh **مؤشر تقني** وليس دليل ملكية نظامهم أو المصدر الحقيقي للأصوات. لا نفحص/ننشر مفاتيح أو أسرار أو نعيد استخدام كود مغلق من Oskot.

## 4. حدّ الدمج — المعمارية المطلوبة

```text
REAL ANDROID SIM CALL
  └─ existing OnTrack Android audio path (RX/TX; preserve pairing and mix channel)
      ├─ input frames + timestamp / sequence
      └─ [adapter boundary] → speech / turn detection / AI orchestration
                                   ├─ Existing Gemini Live path (default, unchanged)
                                   ├─ Optional experimental STT→LLM→TTS chain
                                   ├─ Agent flow state and tool results
                                   └─ voice and prompt settings
                             ← output audio chunks + playback ownership
      └─ [existing adapter] → SAME active SIM call
```

**قواعد مهمة:**
1. **Transport adapter** لا يجيب عن المكالمة ولا يفعّل تحويلًا ولا يغيّر التوجيه؛ يحوّل إطارات الصوت فقط.
2. عند agent handoff أو تغيير node، يجب أن يكون واضحًا أي جزء من الرد وصل فعلًا للطرف الآخر، ولا يرسل صوت الوكيل القديم بعد handoff.
3. Gemini Live سيظل المسار الافتراضي الذي يُقارن به أي تجريب جديد. الـVAD والـinterrupt handling حسب provider لا بالتعميم.
4. لا دمج في Native Android أو الـbackend الإنتاجي قبل اختبار منفصل ثم E2E حقيقي على جهاز المالك؛ لا تغييرات API keys أو الاقتران أو OTA من أجل البحث.

## 5. المحرك والصوت المصري الذي نملكه

**التمييز بين الطبقات:**
- Workflow / orchestration: Dograh/Pipecat **مرجع محتمل** لإدارة الفلو والتوقيت والمقاطعة والتسليم.
- LLM: نموذج نص أو speech-to-speech منفصل؛ الأوزان محلية أو مزود خارجي، حسب الاختبارات.
- STT: تحويل كلام العميل المصري إلى نص، وقد نحتاج تقييم نموذج محلي وتعامل مع الأسماء والأرقام والضوضاء.
- TTS: **الصوت المصري نفسه** يحتاج اختيار نموذج مسموح تجاريًا، بيانات صوت بموافقة أصحابها، وتحسين/تكييف لهجة مصرية، وتجربة latency وحِمل CPU/GPU.
- Kimi K3 in C/AirLLM: درس في `mmap`/disk streaming وذاكرة قليلة، **ليس إثباتًا لزمن صوت مباشر قصير**؛ تقليل RAM عن طريق القرص قد يزيد latency. لا نستورد نموذجًا ضخمًا للمكالمات.

**ليس لدينا حاليًا من هذا البحث دليل على وجود صوت مصري مملوك لنا ومدرّب ومختبر، أو أنه سيعطي جودة واتصالًا لحظيًا على 8 GB RAM CPU فقط.** تكاليف Google/ElevenLabs الممكن تفاديها باستضافة الصوت محليًا تُستبدل بتكلفة hardware/تخزين/كهرباء وتشغيل، لا تختفي.

## 6. تجربة جهاز محلي وVPS

- حسب [دليل Dograh الرسمي لإعداد Docker](https://www.dograh.com/hub/blogs/self-host-voice-agent-docker) (راجع 2026-11-04): تقريبيًا **8GB RAM إجماليًا، منها 4GB متاحة لـDocker، و2 CPU cores و10GB مساحة خالية** لتشغيل الـplatform التجريبية؛ Windows 10/11 عبر WSL2، وLinux أو macOS مدعومان. متطلبات الإصدارات قد تتغير.
- **Linux VPS** أقرب لنشر المستقبلي وأقل تعقيدًا من Windows VPS عند نفس الموارد. **Windows PC** مناسب للتجربة محليًا عبر WSL2+Docker، مع تمكين virtualization.
- **VPS ~$10** قد يصلح لتجريب الواجهة/الـworkflow بموفر AI خارجي، **ولا يثبت تشغيل Dograh + نموذج LLM محلي + STT + صوت مصري TTS بجودة مكالمة وlatency منخفضة على نفس الـVPS**؛ مواصفات CPU، المساحة، bandwidth وتزامن المكالمات تؤثر.
- لا شراء VPS أو نشر/تعديل DNS من أجل هذا البحث دون طلب تنفيذ لاحق. تقييم أي مزود محلي مجاني/مفتوح يجب أن يراجع الترخيص التجاري وترخيص بيانات الأصوات والأوزان بصورة مستقلة عن ترخيص Dograh.

## 7. خطة الاختبار القادمة ومعايير القياس المقترحة

| المرحلة | التجربة | الدليل الذي نحتاجه |
|---|---|---|
| 1. Standalone | Dograh/Pipecat على PC أو بيئة اختبار منفصلة بملفات WAV تجريبية وبدون SIM | نجاح pipeline وstack من غير تعديل OnTrack؛ لوج خاص بالتجربة. |
| 2. Turn-taking | جمل مصرية قصيرة، سكوت، مقاطعة، ضوضاء، تداخل متحدثين | false interruptions، زمن stop/playback، فقدان آخر كلمة، جودة التفريغ. |
| 3. Agent transitions | Flow متعدد العقد وإيجنت بديل + وظيفة تحويل للموظف | ثبات call/session/context، توقف الرد القديم، عدم تسجيل نص لم يُسمع. |
| 4. Local voice | نموذج TTS عربي/مصري مرخص على CPU أولًا ثم GPU عند الحاجة | زمن أول chunk، real-time factor، جودة لهجة/نطق، RAM/CPU/cost. |
| 5. OnTrack integration | adapter تجريبي يحافظ على SIM RX/TX الحالي؛ اختبار على جهاز المالك فقط | مكالمة حقيقية ثنائية الاتجاه، تأخر p50/p95، فجوات الصوت، الاسترداد، تسجيل ملخص صحيح. |

**المقاييس المطلوبة**: `speech_end→first_audible_audio`، `interruption_detected→bot_audio_stopped`، `TTS real-time factor`، packet/chunk gaps، `STT word error rate` للعربية المصرية، memory RSS، CPU%، تزامن 1 و2 مكالمة، وتكلفة دقيقة تشغيل فعلية. الأهداف الرقمية لا تُعتمد قبل قياس baseline من OnTrack الحالي.

## 8. الترخيص والتنبيه التشغيلي

- [Dograh LICENSE](https://github.com/dograh-hq/dograh/blob/main/LICENSE) **BSD 2-Clause**؛ يسمح عمومًا بالتعديل والاستخدام التجاري وإعادة التوزيع مع إبقاء notices/disclaimers المطلوبة. راجع أيضًا تراخيص Pipecat والمكتبات والنماذج المستعملة مستقلًا.
- هذا المستند **مرجع بحث** ولا يغير شروط المالك الأحدث في `AGENTS.md` و`docs/discovery/`، ولا يثبت أن OnTrack تبنّى Dograh أو نشر محركًا جديدًا.
- استخدم [الثريد #37](https://github.com/OnTrack-Development/OnTrack-voice/issues/37) لإضافة نتائج تحليل لاحقة، روابط بنشمارك، وتقارير إثبات، ثم حدّث المستند بإصدارات واضحة بدل الخلط بين الافتراضات والتجارب.

## 9. جدول درجات الثقة

| الاستنتاج | الحالة |
|---|---|
| Dograh مبني على Pipecat ويفصل transport عن pipeline ويملك Gemini Live وVAD/Smart Turn وكاش Redis | **مؤكد مباشرة من كود GitHub وقت الفحص** |
| Oskot APK v1.1.4 يستخدم تحويل المكالمة واختبار WebRTC | **من التدقيق السابق للحزمة** |
| تطابق مسارات اختبار Oskot مع widget Dograh | **من مقارنة التدقيق السابق؛ لا يثبت محرك الإنتاج** |
| Dograh أو Pipecat سيحل تلقائيًا التقاط صوت الشريحة على Android | **غير مثبت ولا نفترضه** |
| VPS 8GB/$10 سيشغّل توليد صوت مصري محلي بجودة مكالمة فورية | **غير مثبت؛ يحتاج benchmark** |
| تم دمج Dograh أو تغيير APK/سيرفر OnTrack | **لم يحدث ضمن هذا التوثيق** |
