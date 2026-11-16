# OnTrack Voice — فحص الإنتاج عبر SSH وGitHub Actions (قراءة فقط)

**تاريخ التحقق:** 16 نوفمبر 2026. **المستودع الأساسي:** `main` عند `42eb02ad62ff4a68a152a3e7a83a04e5d1fa91ee`. **فرع التقرير:** `audit/live-verify-20261116`.

## الأدلة
- [اتصال SSH + فحص HTTPS — تشغيل 43951932276](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/43951932276): ناجح.
- [مقارنة 281 ملفًا + فحص مؤشرات أمان الإنتاج — تشغيل 43953122205](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/43953122205): ناجح.
- [المحاولة الوسيطة 43952740423](https://github.com/OnTrack-Development/OnTrack-voice/actions/runs/43952740423): فشلت بسبب Git dubious ownership على Windows runner، وأُصلحت باستخدام Get-ChildItem. لا علاقة للفشل باتصال SSH.
- الرانر `BEDIR` دخل عبر SSH لحساب `appontrack` UID `2379` باستخدام المفتاح الخاص الموجود على جهاز الرانر، مع `StrictHostKeyChecking=yes`.
- مجلد Laravel الإنتاجي: `/home/appontrack/iovoice-app/`. توجد `/home/appontrack/public_html/saas/`، لكن لم نجد `saas/index.php` أو `saas/console/index.php` بمسارات الفحص.

## نتائج HTTP المباشر من الرانر
| المسار | الحالة |
|---|---|
| `https://app.ontrackegy.com/` | HTTP 200 |
| `/mobile/updates/latest.json` | HTTP 200 |
| `/mobile/updates/native/latest.json` | HTTP 200 |
| `/api/v1/bridge/status` بلا اعتماد | HTTP 401 (متوقع لمسار محمي) |

ملفا OTA يحتوي كل منهما الحقلين `payload` و`signature`؛ لذلك لم تُستخرج أرقام الإصدارات من المستوى الأعلى. لا نستنتج نسخة الهاتف أو نجاح E2E من ذلك.

## مطابقة SHA-256 للملفات المنشورة
المقارنة تمت **على 281 ملفًا** داخل `saas/app` و`routes` و`resources` و`public` و`config` و`bootstrap` و`database`، مقابل نظائرها تحت `/home/appontrack/iovoice-app`. لم تُقارن أسرار ولا ملفات بيئة العميل ولا كامل ملفات النظام.

| الحالة | العدد |
|---|---:|
| مطابقة تمامًا | 200 |
| مختلفة | 79 |
| مفقودة في موضع المقارنة | 2 |
| غير قابلة للقراءة | 0 |

ملفا `AgentStudioController.php` و`public/widget/ontrack-widget.js` مطابقان لمصدر GitHub، بينما `BridgeController.php` و`routes/web.php` وملفات كثيرة للـBilling/UI مختلفة. **لا يجوز نشر نسخة main فوق الإنتاج عشوائيًا** قبل تحليل تلك الفروقات.

## مؤشرات أمان مثبتة من ملفات الإنتاج نفسها
| المؤشر | نتيجة القراءة |
|---|---|
| دالة مصادقة Bridge موجودة | YES |
| تستخدم `hash_hmac` داخل دالة المصادقة | NO |
| تستخدم `hash_equals` داخل دالة المصادقة | NO |
| تحتوي رجوعًا إلى `Account::first()` | YES |
| مسار إنشاء جلسة عامة للـAgent موجود | YES |
| الجلسة تُعيد `gemini_ws_url` | YES |
| يوجد ذكر `share` في دالة إنشاء الجلسة | NO |
| استخدام `tenant_id=1` داخل WorkspaceController | YES |
| Widget يستخدم `getUserMedia` | NO |
| Widget يستخدم `WebSocket` | NO |

هذه مؤشرات **فحص كود ساكن على السيرفر الحي**، لا اختبار استغلال أو اختبار تجريبي كامل. تجمع بين مخاطر حرجة في هوية/توقيع Bridge، وتسريب محتمل لمفاتيح Gemini من جلسات المتصفح، وعزل بيانات متعددة العملاء. لا تُجري اختبارات تعرض مفاتيح الإنتاج في سجلات عامة. المستودع يظهر حاليًا لدى GitHub `public` رغم بعض الوثائق القديمة التي تصفه بأنه Private.

## جميع الملفات الـ79 المختلفة
- `app/Console/Commands/MigrateLegacySqliteToMysql.php`
- `app/Http/Controllers/Api/BridgeController.php`
- `app/Http/Controllers/Controller.php`
- `app/Http/Controllers/Enterprise/ActionController.php`
- `app/Http/Controllers/Enterprise/AuthController.php`
- `app/Http/Controllers/Enterprise/WorkspaceController.php`
- `app/Models/Account.php`
- `app/Models/Agent.php`
- `app/Models/AgentFlowVersion.php`
- `app/Models/AiRelayEntry.php`
- `app/Models/Appointment.php`
- `app/Models/AutomationRule.php`
- `app/Models/Call.php`
- `app/Models/Contact.php`
- `app/Models/DeviceBinding.php`
- `app/Models/DevicePairing.php`
- `app/Models/Lead.php`
- `app/Models/Membership.php`
- `app/Models/Plan.php`
- `app/Models/Site.php`
- `app/Models/Subscription.php`
- `app/Models/User.php`
- `app/Models/Voice.php`
- `app/Models/WebhookLog.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Automation/PostCallAutomationService.php`
- `app/Services/Billing/BillingService.php`
- `app/Services/Campaigns/CampaignDialerService.php`
- `app/Services/Console/ConsoleContextService.php`
- `app/Services/Mobile/CallLedgerService.php`
- `app/Services/Mobile/DevicePairingService.php`
- `app/Services/Security/VaultService.php`
- `app/Services/Support/MultiAgentOrchestrator.php`
- `app/Services/Voice/VoiceSessionService.php`
- `bootstrap/app.php`
- `bootstrap/providers.php`
- `config/app.php`
- `config/auth.php`
- `config/cache.php`
- `config/database.php`
- `config/filesystems.php`
- `config/logging.php`
- `config/queue.php`
- `config/session.php`
- `database/factories/UserFactory.php`
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/2026_11_12_000001_unify_iovoice_enterprise_tables.php`
- `database/migrations/2026_11_12_000002_add_summary_to_calls_table.php`
- `database/migrations/2026_11_12_000003_add_manager_callback_and_ivr_to_agents.php`
- `database/migrations/2026_11_15_000002_create_appointments_and_leads_tables.php`
- `public/.htaccess`
- `public/assets/css/obsidian.css`
- `public/assets/js/obsidian.js`
- `public/downloads/ontrack_bridge.zip`
- `public/index.php`
- `resources/views/app/agents/web_call.blade.php`
- `resources/views/enterprise/auth_message.blade.php`
- `resources/views/enterprise/invoice_print.blade.php`
- `resources/views/enterprise/layout.blade.php`
- `resources/views/enterprise/login.blade.php`
- `resources/views/enterprise/portal.blade.php`
- `resources/views/enterprise/public_booking.blade.php`
- `resources/views/enterprise/register.blade.php`
- `resources/views/enterprise/tabs/agents.blade.php`
- `resources/views/enterprise/tabs/api_keys.blade.php`
- `resources/views/enterprise/tabs/automations.blade.php`
- `resources/views/enterprise/tabs/billing.blade.php`
- `resources/views/enterprise/tabs/calls.blade.php`
- `resources/views/enterprise/tabs/campaigns.blade.php`
- `resources/views/enterprise/tabs/devices.blade.php`
- `resources/views/enterprise/tabs/floorplan.blade.php`
- `resources/views/enterprise/tabs/integrations.blade.php`
- `resources/views/enterprise/tabs/messages.blade.php`
- `resources/views/enterprise/tabs/overview.blade.php`
- `resources/views/enterprise/tabs/settings.blade.php`
- `resources/views/enterprise/tabs/tickets.blade.php`
- `resources/views/enterprise/tabs/workflows.blade.php`
- `routes/console.php`
- `routes/web.php`

## الملفان غير الموجودين في مسار الإنتاج المقابل
- `bootstrap/cache/.gitkeep`
- `public/assets/voice-samples/fish.0123456789abcdef0123456789abcdef-1-fixed-v2.wav`

## خطة المعالجة المقيدة بعدم كسر الإنتاج
1. توثيق أسباب التعديلات الحية قبل أي Merge أو نشر؛ عالج 79 اختلافًا فرديًا وليس بنسخ شامل.
2. أغلق الرجوع إلى `Account::first()`، وطبّق مصادقة فعلية مرتبطة بحساب وتوقيع HMAC باستخدام أسرار عشوائية محمية، مع اختبارات تكامل WHMCS وعدم تعطيل Widget.
3. لا تُرسل مفاتيح Gemini للمتصفح. صحّح التحقق من صلاحية روابط مشاركة الجلسات واستخدم Relay/جلسات مؤقتة.
4. عالج استعلامات `tenant_id=1` واختبر عزل حسابين.
5. تحقق من بنية الـOTA الموقعة ثم من إصدار APK عبر manifest وتحقيق الجهاز الفعلي.
6. تحقق من فواتير واشتراكات ورسائل ومكالمات/Android بعد إصلاح الكود وقبل نشره. كل نشر يجب أن يكون على نطاق محدد، مع rollback وإيصال هوست.

**لم يُعدل هذا الفحص ملفات الإنتاج، ولم يُشغّل Host-Link، ولم يقرأ مفاتيح خاصة أو .env أو بيانات عملاء.**
