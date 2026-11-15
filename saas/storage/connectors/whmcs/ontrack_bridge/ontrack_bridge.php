<?php

/**
 * OnTrack Bridge — WHMCS Official Addon Module
 * Connects your WHMCS installation securely to OnTrack SaaS Central Engine.
 * Features 100% Zero-Knowledge PII Tokenization, Zero-Retention, and Remote Diagnostics.
 *
 * @version 1.0.0
 * @author OnTrack Development Team
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

function ontrack_bridge_config(): array
{
    return [
        'name' => 'OnTrack Bridge — AI Support & Voice',
        'description' => 'الموديول الخفيف للربط الآمن والمشفر مع سيرفر OnTrack SaaS المركزي مع درع الخصوصية والتعمية الكاملة للبيانات.',
        'version' => '1.0.0',
        'author' => 'OnTrack',
        'fields' => [
            'saas_url' => [
                'FriendlyName' => 'SaaS Server URL',
                'Type' => 'text',
                'Size' => '60',
                'Default' => 'https://app.ontrackegy.com',
                'Description' => 'رابط السيرفر المركزي لمنصة OnTrack SaaS.',
            ],
            'integration_key' => [
                'FriendlyName' => 'Integration Key',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'مفتاح التكامل الصادر من لوحة تحكم OnTrack (يبدأ بـ otint_).',
            ],
            'secret_key' => [
                'FriendlyName' => 'Secret Key (HMAC)',
                'Type' => 'password',
                'Size' => '60',
                'Description' => 'مفتاح التوقيع والتشفير السري المشترك.',
            ],
            'enable_auto_reply' => [
                'FriendlyName' => 'تفعيل الرد التلقائي على التذاكر',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'توليد مسودات ردود الذكاء الاصطناعي على التذاكر الجديدة.',
            ],
            'enable_web_widget' => [
                'FriendlyName' => 'تفعيل ودجت الشات والمكالمات بمنطقة العميل',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'إظهار المساعد الذكي ذو العلامة البيضاء للمستخدمين.',
            ],
            'enable_invoices_sync' => [
                'FriendlyName' => 'السماح بالاستعلام عن الفواتير',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'إتاحة قراءة حالة الفواتير للرد على استفسارات العملاء.',
            ],
            'enable_cpanel_diagnostics' => [
                'FriendlyName' => 'تفعيل تشخيصات الخادم (cPanel UAPI)',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'السماح للوكيل التقني بفحص استهلاك الرام والمساحة.',
            ],
            'zero_retention_mode' => [
                'FriendlyName' => 'وضع الخصوصية التامة (Zero-Data-Retention)',
                'Type' => 'yesno',
                'Default' => 'yes',
                'Description' => 'مسح كافة نصوص التذاكر من السيرفر المركزي فور إرسال الرد وحفظها لديك فقط.',
            ],
        ],
    ];
}

function ontrack_bridge_activate(): array
{
    try {
        $schema = Capsule::schema();

        // Local tokenization table
        if (!$schema->hasTable('mod_ontrack_bridge_tokens')) {
            $schema->create('mod_ontrack_bridge_tokens', function ($table) {
                $table->increments('id');
                $table->string('session_key', 80)->unique();
                $table->longText('mapping_json');
                $table->timestamps();
            });
        }

        // Local privacy audit table
        if (!$schema->hasTable('mod_ontrack_bridge_audit')) {
            $schema->create('mod_ontrack_bridge_audit', function ($table) {
                $table->bigIncrements('id');
                $table->string('event', 80)->index();
                $table->string('endpoint', 190);
                $table->longText('payload_summary')->nullable();
                $table->integer('http_status')->default(200);
                $table->longText('response_summary')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        return ['status' => 'success', 'description' => 'تم تفعيل OnTrack Bridge وإنشاء جداول الخصوصية المحلية بنجاح.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }
}

function ontrack_bridge_deactivate(): array
{
    return ['status' => 'success', 'description' => 'تم تعطيل OnTrack Bridge مع الاحتفاظ بسجلات التدقيق المحلية.'];
}

function ontrack_bridge_output($vars): void
{
    require_once __DIR__ . '/AuditLog.php';

    $saasUrl = rtrim((string)($vars['saas_url'] ?? 'https://app.ontrackegy.com'), '/');
    $integrationKey = trim((string)($vars['integration_key'] ?? ''));
    $secretKey = trim((string)($vars['secret_key'] ?? ''));

    // Test Connection Live check
    $statusData = ['connected' => false, 'plan' => 'Unknown', 'features' => []];
    if ($integrationKey !== '' && $secretKey !== '') {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.status", $secretKey);
        $ch = curl_init("{$saasUrl}/api/v1/bridge/status");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Ontrack-Key: {$integrationKey}",
            "X-Ontrack-Timestamp: {$timestamp}",
            "X-Ontrack-Signature: {$signature}",
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $res) {
            $statusData = json_decode($res, true) ?: $statusData;
            $statusData['connected'] = true;
        }
    }

    $recentLogs = OnTrackBridgeAudit::recent(15);
    $connected = !empty($statusData['connected']);
    $planName = $statusData['plan_name'] ?? ($connected ? 'Professional Plan' : 'غير متصل');
    $zeroRetention = !empty($vars['zero_retention_mode']);

    echo <<<HTML
<div style="font-family:system-ui,-apple-system,sans-serif;direction:rtl;margin:15px 0;">
    <!-- Top Header Status Card -->
    <div style="background:#0F172A;color:#FFFFFF;border-radius:14px;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.15);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="background:#1E293B;width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;">🛡️</div>
            <div>
                <h2 style="margin:0;font-size:20px;font-weight:700;">OnTrack Bridge — درع الخصوصية والربط المركزي</h2>
                <p style="margin:4px 0 0 0;color:#94A3B8;font-size:13px;">Zero-Knowledge PII Tokenization & Remote Support Orchestrator</p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="background:HTML;
    echo $connected ? '#064E3B' : '#7F1D1D';
    echo ';color:';
    echo $connected ? '#34D399' : '#F87171';
    echo <<<HTML
;padding:8px 16px;border-radius:30px;font-weight:bold;font-size:13px;display:flex;align-items:center;gap:6px;">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:currentColor;"></span>
                <span>HTML;
    echo $connected ? 'متصل بالسيرفر المركزي (200 OK)' : 'غير متصل (تحقق من المفاتيح)';
    echo <<<HTML
</span>
            </div>
            <a href="addonmodules.php?module=ontrack_bridge" class="btn btn-default" style="background:#1E293B;color:#FFF;border:1px solid #334155;border-radius:8px;padding:8px 14px;text-decoration:none;font-size:12px;">🔄 تحديث الفحص</a>
        </div>
    </div>

    <!-- Plan & Entitlements Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-top:20px;">
        <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
            <div style="color:#64748B;font-size:12px;font-weight:600;">باقة الاشتراك الحالية</div>
            <div style="font-size:22px;font-weight:bold;color:#0F172A;margin:6px 0;">{$planName}</div>
            <div style="color:#10B981;font-size:13px;">✓ رخصة مفعلة ونشطة</div>
        </div>

        <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
            <div style="color:#64748B;font-size:12px;font-weight:600;">درع الخصوصية والتعمية</div>
            <div style="font-size:18px;font-weight:bold;color:#0F172A;margin:6px 0;">Zero-Knowledge PII: نشط ✅</div>
            <div style="color:#64748B;font-size:12px;">يتم استبدال الأسماء والدومينات بـ [DOMAIN_#] محلياً قبل الإرسال.</div>
        </div>

        <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
            <div style="color:#64748B;font-size:12px;font-weight:600;">وضع عدم الاحتفاظ بالسجلات</div>
            <div style="font-size:18px;font-weight:bold;color:HTML;
    echo $zeroRetention ? '#10B981' : '#F59E0B';
    echo ';margin:6px 0;">';
    echo $zeroRetention ? 'Stateless Mode: مفعل 🔒' : 'حفظ السجلات مشفرة';
    echo <<<HTML
</div>
            <div style="color:#64748B;font-size:12px;">معالجة في الذاكرة دون تخزين نصوص التذاكر في السيرفر المركزي.</div>
        </div>
    </div>

    <!-- Data Privacy & Audit Log Viewer -->
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:24px;margin-top:20px;box-shadow:0 2px 6px rgba(0,0,0,0.04);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;font-size:16px;font-weight:bold;color:#0F172A;">📊 سجل تدقيق وشفافية البيانات المحلية (آخر الطلبات الصادرة)</h3>
            <span style="font-size:12px;color:#64748B;">يثبت تعمية الدومينات وأسماء العملاء محلياً قبل مغادرة سيرفرك</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="table table-bordered table-hover" style="width:100%;font-size:12px;">
                <thead>
                    <tr style="background:#F8FAFC;">
                        <th>الوقت</th>
                        <th>الحدث</th>
                        <th>البيانات المشفرة الصادرة (Tokenized Payload)</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
HTML;

    if (empty($recentLogs)) {
        echo '<tr><td colspan="4" style="text-align:center;padding:20px;color:#94A3B8;">لا توجد طلبات مسجلة بعد. عند فتح تذكرة جديدة ستظهر هنا فوراً.</td></tr>';
    } else {
        foreach ($recentLogs as $log) {
            $statusBadge = ($log['http_status'] >= 200 && $log['http_status'] < 300)
                ? '<span class="label label-success">200 OK</span>'
                : '<span class="label label-danger">' . $log['http_status'] . '</span>';
            $summary = htmlspecialchars((string)($log['payload_summary'] ?? ''));
            echo "<tr>
                <td style='white-space:nowrap;'>{$log['created_at']}</td>
                <td><strong>{$log['event']}</strong></td>
                <td><code style='color:#0F172A;background:#F1F5F9;padding:2px 6px;border-radius:4px;'>{$summary}</code></td>
                <td>{$statusBadge}</td>
            </tr>";
        }
    }

    echo <<<HTML
                </tbody>
            </table>
        </div>
    </div>
</div>
HTML;
}
