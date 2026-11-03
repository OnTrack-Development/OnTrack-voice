# Provider منافس — Olimi AI | ملف مقارنة تقني وتجاري
**تاريخ البحث:** 2026-11-03 | **المنافس:** https://www.olimi.ai/ | **المنتج المقارن:** OnTrack Voice
**نوع الوثيقة:** بحث مستقل خارجي في المنافس، لا مواصفات أو قرارات تنفيذ ملزمة، ولا بيانات دخول أو أسرار.

## الملخص التنفيذي
Olimi AI منصة عربية لإيجنتات المكالمات الواردة والصادرة، Agent Builder، حملات، تحليل المكالمات، TTS، STT، Speech-to-Speech، Web Agents صوت/نص/فيديو، وتشغيل Enterprise داخل بنية العميل. تتفوق رسالتها التنافسية في اللهجات العربية، طبيعية الكلام والتعامل مع المقاطعات.
**نتيجة تقنية موثقة ومهمة:** أعلنت Olimi رسميًا اعتماد **Soniox كمزود أساسي لتحويل الصوت إلى نص (STT)**، بالتوازي مع تصريحات مؤسسيها عن تدريب **نماذج صوت ولهجات خاصة** على بيانات وتسجيلات وGPU داخلية. لذلك عبارة «كل الأصوات Google» أو «كل مكوّناتهم من تصنيعهم» غير مثبتة وخاطئة كتوصيف عام.

## 1. المنتجات المعلنة
- **Phone Agents:** استقبال/إرسال، دعم العملاء والمبيعات، متابعة Leads، تحويل لموظف، Agent Builder، Prompt وشخصية ورسالة أولى، Test Call، حملات، معلومات CRM.
- **Call Intelligence:** Summary، تصنيف Intent، Sentiment، قياس زمن الرد، تحليلات.
- **Text-to-Speech (TTS):** توليد أصوات بلهجات عربية، أمثلة منشورة Farah (مصري)، Abdullah (سعودي)، Ahlam (إماراتي)، Layla (لبناني)، Ali (سوري)، Khaled (قطري)، وأصوات لغات أخرى؛ الأسماء المعروضة لا تكشف أسماء النماذج الداخلية.
- **Speech-to-Text (STT):** تفريغ متعدد اللغات، Diarization، تنظيف filler words، صوت المكالمات.
- **Speech-to-Speech:** محادثة منخفضة الكمون ومقاطعات.
- **Web Agents:** Widget للمواقع والمتاجر بالصوت والنص والفيديو/الأفاتار.
- **On-Premise:** إعلان قابلية تشغيل مكونات Speech/LLM على GPU Servers داخل بيئة العميل.
- **قطاعات:** عقارات، عيادات، بنوك، تجزئة، خدمات، تعليم.
**ملاحظة:** الموقع يعلن 20+ لغة و<750ms و24/7؛ لا توجد لدينا معايرة مخبرية مستقلة للأرقام أو تحقق حي من جميع الوظائف.

## 2. المصادر الفعلية للصوت: موثوق مقابل غير مثبت
| الطبقة | النتيجة | الثقة والدليل |
|---|---|---|
| STT/ASR | **Soniox** هو المزود الأساسي المعلن | مرتفعة: إعلان صريح صادر عن Olimi نفسها على LinkedIn |
| TTS عربي | أصوات عربية مدرّبة محليًا وفق موقعها وتصريحات المؤسسين | مرتفعة لوجود التطوير المعلن، لكن لا نملك أوزان النماذج أو فحص كل عينة |
| Voice activity / Turn taking | المنصة تعرض التوقف عند المقاطعة والاستماع قبل الرد | مزية معلنة؛ الآلية الداخلية الدقيقة غير مفصح عنها |
| LLM/Reasoning | غير معلن أي نموذج ثابت لكل عميل أو جلسة | غير مؤكد |
| Google TTS / Gemini | **لا يوجد دليل موثوق على أنها المزوّد الأساسي للصوت** | غير مثبت؛ لا نفترض النفي الكامل أو الاستخدام |
| ElevenLabs / Cartesia | لا يوجد إثبات علني باستخدامهما في الإنتاج | غير مثبت |
| Twilio | ورد صراحة ضمن تكاملات متوقعة في إعلان وظيفة Backend | دليل على إدراج Twilio في نطاق العمل، **لا يثبت** أنه ناقل كل المكالمات فعليًا |
| بيانات تدريب خاصة | مؤسِّسة Olimi تصف تسجيل لهجات مصرية وسعودية وتدريب نماذج على GPUs داخلية | تصريح مباشر، لا تدقيق مستقل لأصول النماذج |

## 3. كيف يمكن تقديم صوت طبيعي — تفريق الهندسة المعيارية عن تنفيذهم
معمارية قياسية لبناء تجربة مشابهة، **ليست reverse-engineering مثبتًا**:
PSTN/SIP/media -> audio cleanup -> VAD/endpointing -> streaming STT (Soniox معلن عندهم) -> LLM + state/CRM + memory -> streaming TTS بلهجة مناسبة -> jitter/playout -> caller.
العوامل الحساسة: بداية الكلام فقط بعد دور المتصل، مقاطعة البث وإيقاف الجملة فورًا، ثبات نبرة اللهجة، أرقام/أسماء/كيانات دقيقة، تأخير منخفض، ضبط echo/noise، تدفق صوت مستمر ومزامنة transcript مع call_id.
الأصوات المدربة عربيًا وحدها لا تكفي دون Turn Detection وStreaming واستقرار طبقة الاتصالات.

## 4. Stack وبنية الاستضافة — أين الدليل؟
- إعلان توظيف Olimi بعنوان Senior Backend Engineer (Laravel + Node.js / TypeScript) ذكر **ترحيل Backend من Laravel 10 إلى Node.js/TypeScript/Express**، واستخدام MySQL وRedis وBullMQ وPrisma/TypeORM وJWT وWebSockets وAWS S3 presigned URLs ضمن التقنيات المطلوبة. **وصف وظيفة ليس جردًا مؤكدًا للإنتاج الحالي**.
- إعلان Node.js آخر ذكر WebSocket/REST وmicroservices.
- مؤسسوها ذكروا تطوير نماذج وتدريب على GPU داخلية، والمنتج يعرض On-Premise.
- **لم يُثبَت** مزود استضافة www.olimi.ai أو app.olimi.ai في هذا البحث: CDN/hosting الموقع ≠ استضافة Backend ≠ GPU inference، ولا يصح اختلاق عنوان IP أو منطقة AWS/GCP/Hetzner.
- لم يُثبت نوع منصة PBX/SIP/Carrier الرئيسية، أو أسعار الدقيقة المؤكدة، أو هوية كل موديل LLM، أو الملكية الحصرية لكامل أوزان TTS.

## 5. استراتيجية المنتج
- اتصال وارد وصادر، تخصص قطاعات بAgents تجريبية، تحويل حي عند الحاجة، SMS/WhatsApp/CRM بعد المكالمة.
- حزمة Enterprise ذات نشر خاص ودعم تكامل.
- الموقع يعرض Web Agent متعدد الوسائط (صوت/شات/أفاتار).
- الأسعار التفصيلية للمكالمات ليست قائمة علنية موثقة؛ لا تنقل أسعار تطبيقات أخرى باعتبارها Voice minutes.

## 6. المقارنة مع OnTrack Voice — بناء على كود/خطة OnTrack لا وعود
| البند | Olimi | OnTrack Voice |
|---|---|---|
| STT | Soniox معلن كأساسي | Google Live حاليًا؛ يمكن تقييم STT منفصل بعد سلامة خط الهاتف |
| TTS المصري | تدريب محلي معلن | Google Live prebuilt مع إمكانية Premium مستقبلاً |
| اتصال الهاتف | PSTN/تكاملات معلنة دون كشف تفاصيل الناقل | Android SIM للمالك دون شراء Gateway؛ يلزمه Device E2E حقيقي |
| المقاطعة | معلنة | تحتاج قياس حقيقي على Xiaomi |
| الحملات/التقارير | معلنة | أجزاء CODED وغير متحقق إطلاقها بالكامل |
| شريحة لكل Tenant | لا يوجد دليل لآلية Android SIM مماثلة | ميزة تصميمية OnTrack: ربط SIM1/SIM2 بحسابين منفصلين |
| Reseller/White-label/WHMCS | غير مفصح عنها بنفس تفاصيل المشروع | ضمن خطة OnTrack G0–G9 |
**قاعدة القبول:** لا تعتبر شاشة أو Test Mock دليلًا أن المكالمة ثنائية الاتجاه تعمل.

## 7. أفكار قابلة للتقييم (لا تغيّر القرارات المعتمدة)
1. **P0**: أصلح تشغيل Android SaaS SIM بدون إدخال API Key من المستخدم، ثم تحقق من جلسة Live وPCM وارد وصادر على الهاتف.
2. **P0**: قياس time-to-first-audio، VAD false cut، barge-in، packet gaps، لمكالمات مصرية قصيرة على الجهاز.
3. **P1**: Benchmark Soniox STT مقابل Gemini Live على نفس تسجيلات مصرية مرخّص بها (أسماء/أرقام/مقاطعات/ضوضاء) وتقارير WER/latency، **فقط إذا المعمارية تسمح بطبقة STT منفصلة**.
4. **P1**: Benchmark TTS عربي (بما فيه Google وموفر مخصص قانوني) مع وضوح الترخيص والكمون والبث، بدون ادعاء أصوات خاصة قبل إثباتها.
5. **P1**: حفظ transcript وربطه بالتقارير والجودة، مع إذن ومصدر واضح وعدم اختلاق أحداث أو مشاعر.
6. **P2**: On-Premise/Enterprise عند وجود طلب فعلي، وليس شرط تشغيل خط SIM الحالي.

## 8. المصادر وروابط التحقق
1. الموقع والـPhone Agents: https://www.olimi.ai/
2. قائمة TTS والأصوات: https://www.olimi.ai/products/text-to-speech
3. STT: https://www.olimi.ai/products/speech-to-text
4. Speech-to-Speech: https://www.olimi.ai/products/speech-to-speech
5. On-Premise: https://www.olimi.ai/products/on-premise
6. **إعلان Olimi عن اختيار Soniox كأساسي STT:** https://www.linkedin.com/posts/olimi-ai_we-have-selected-soniox-as-our-main-speech-to-text-activity-7504889029818077184-c8Yi
7. تصريح المؤسِّسة عن GPUs/البيانات وتدريب اللهجات: https://www.linkedin.com/posts/shaden-el-olimi-36b023a6_i-never-planned-to-work-in-tech-i-definitely-activity-7396174266251083776-tfyX
8. تصريح المؤسِّسة عن مؤدي أصوات وتدريب مصري/سعودي: https://www.linkedin.com/posts/shaden-el-olimi-36b023a6_i-just-realized-something-about-building-activity-7418729713684299777-nbk4
9. إعلان Backend فيه Laravel→Node.js وTwilio وAWS S3: https://eg.linkedin.com/jobs/view/senior-backend-engineer-laravel-%2B-node-js-typescript-%E2%80%93-olimi-ai-new-cairo-at-olimi-ai-4349503925
10. مقابلة مؤسسي Olimi: https://enterpriseam.com/egypt/weekend/meet-olimi-ais-shaden-el-olimi/

### حالة الإثبات
- FACT/SELF-REPORTED: منتج معلن أو تصريح الشركة — قد يحتاج اختبار مستقل قبل اعتماد الجودة والتكاليف.
- ENGINEERING INFERENCE: تصور معماري عام وليس كشفًا سرّيًا لكود المنافس.
- UNKNOWN: الاستضافة الدقيقة، مواصفات الأجهزة، المزود الصوتي لكل صوت، أسعار المكالمات، كل LLM.
