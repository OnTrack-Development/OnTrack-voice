# ذاكرة المشروع — على GitHub فقط

هذه الملفات هي الذاكرة المستمرة للمشروع؛ لا تُنسخ إلى `/home/appontrack/public_html/saas` أو أي مجلد تطبيق على الهوست.

| الملف | الوظيفة |
|---|---|
| [PROJECT_MEMORY_AR.md](PROJECT_MEMORY_AR.md) | الحقائق والقيود والقرارات والمراجع التي لا يجوز نسيانها. |
| [CHECKPOINT.md](CHECKPOINT.md) و[checkpoint.json](checkpoint.json) | أين وصلنا، ما الذي ثبت، العوائق، والخطوة التالية ونطاقها. |
| [ISSUE_REGISTER.json](ISSUE_REGISTER.json) | كل مشكلة، حالتها، أدلتها، المحاولات وحلولها وما بقي. |
| [MEMORY_PROTOCOL_AR.md](MEMORY_PROTOCOL_AR.md) | عقد تسجيل كل إجراء وفشل ومحاولة واختبار وتحديث حالة. |
| [ACTION_LOG.jsonl](logs/ACTION_LOG.jsonl) | سجل زمني للإجراءات المنفذة/المصححة وسببها ونتيجتها. |
| [ATTEMPT_LOG.jsonl](logs/ATTEMPT_LOG.jsonl) | فرضيات وحلول ومحاولات، دون مسح محاولات الفشل. |
| [TEST_LOG.jsonl](logs/TEST_LOG.jsonl) | اختبارات وأدلتها وحدود ما تثبته. |
| [SESSION_HANDOFF_AR.md](SESSION_HANDOFF_AR.md) | تعليمات استلام جلسة جديدة دون إعادة ضبط أو تكرار. |
| [templates/](templates/README_AR.md) | قوالب إدخال الإجراءات والمشاكل والمحاولات والاختبارات. |

المناقشة الكاملة المعنوية وسجل الخيارات في `docs/discovery/`، والأدلة الخام في `ops/host-link/results/` و`docs/evidence/`. الميموري تشير إليها ولا تستبدل حقائقها بنسخة مختصرة متناقضة.

المسجل هو الإيجنت العامل وتدفق مهام المشروع؛ ليست خدمة مراقبة خفية تدعي تسجيل كل عملية في نظام التشغيل. يلزم تحديث السجلات في كل خطوة عمل مهمة وتثبيتها في GitHub قبل إنهاء الجلسة.
