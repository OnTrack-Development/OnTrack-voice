# SaaS Phase 3 — الإدارة والباقات والريسيلرز
**تاريخ العمل:** 2026-11-09
**الحالة:** UI CODE + OFFLINE CI PASSED · HOST/BUYER FLOW NOT VERIFIED · NOT DEPLOYED

### أساس العمل
- فرع Stage 3: `fix/saas-phase3-reseller-plan-ui-20261109` مشتق من Phase 2 PR #80، ولا يتضمن Phase 1 PR #79 غير المعتمد.
- لا تعديل Android أو OTA أو SIM أو APIs مستخدمة من الهاتف أو بيانات اشتراكات أو مفاتيح.
- شرط المالك: أي خيار أثبت عمله يبقى ويختبر، وتغييرات التحكم في الباقات لا تمنح اشتراكات مدفوعة دون إثبات الدفع.

### تنفيذ الكود (WEB-UX-02: جزئي)
- [x] **Admin Platform UI:** إضافة إدارة اشتراك الريسيلر إلى صفحة `Plans`. يعرض كل ريسيلر وحالته/باقة الاشتراك النشطة حسب الزمن وليس أحدث صف تاريخي. أضاف Form لتعيين الباقة عبر action `assign_plan` الموجود بالفعل؛ لم ينشأ endpoint جديد يلتف على صلاحيات الحساب.
- [x] **Payment protection:** خيارات التعيين المباشر متاحة فقط للباقات المجانية خارج `ONTRACK_DEMO=1`، بينما الباقات المدفوعة يجب أن تمر عبر Kashier/Manual Payment الموثق. في وضع Demo المقصود يمكن للأدمن تجربة تغيير الباقات المدفوعة دون تحصيل حقيقي، كما كان مسموحًا في Action القديم.
- [x] **Reseller Workspace:** صفحة Plans تعرض باقة الريسيلر نفسه وحصته ومساعديه والاشتراكات المتاحة، مع رابط واضح لصفحة `Billing` حيث مسار شراء وتجديد الباقة الموجود؛ **لا تتحول الخيارات إلى أزرار غير عاملة أو اشتراك تلقائي**.
- [x] **Visibility/RBAC:** إذا أغلق Owner ميزة الريسيلرز من إعدادات المنصة، لا يظهر تحكم الريسيلرز في Admin. الريسيلر لا يرى أداة تعيين باقته لنفسه خارج Checkout ولا يمكنه تغيير سعر الباقة.
- [x] **Regression:** `tools/test_saas_phase3_reseller_plan_controls.php` مع اختبارات Plans/Checkout/Kashier/SSO/Flow/Device/Handoff القائمة.

### نتيجة CI
- [GitHub Actions #42556706732](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/42556706732) — **SUCCESS** لجميع الفحوص بما فيها Plan controls وSubscription transitions وCheckout وKashier Webhook والـTenant.
- لا تمثل الاختبارات عملية دفع حقيقية أو اختبار صفحة على حساب مسجل في هوست الإنتاج.

### الباقي قبل إعلان الإغلاق
- [ ] تسجيل دخول فعلي لحساب Platform وReseller وCustomer، رؤية اختيار خطة الريسيلر وتغيير Free/Demo ضمن الصلاحيات، وفحص مسار Kashier sandbox وعودة الحالة.
- [ ] مراجعة History/Quotas/Overage قبل وبعد ترقية فعلية، وعدم تغيير سعر اشتراك قائم أو تفعيل اشتراك مدفوع بلا دليل.
- [ ] White-label DNS/TLS وPassword Reset وSupport RBAC وVoice browser mic وCampaigns/Contacts/Notifications E2E.
- [ ] Stage 4: Full Regression + MySQL QA + Snapshot/Restore + Host SHA + Rollback + Deployment approval.
- [ ] مراجعة تجميع PR #79 و#80 وStage 3 على فرع تكامل جديد، حل التعارضات، وتأكيد عدم تراجع Google Live وSIM.

**تعريف [x] هنا:** كتابة كود نجح على CI مع fixtures؛ **ليس DONE في الإنتاج**. الـPR يظل Draft ولا ينشر شيئًا.
