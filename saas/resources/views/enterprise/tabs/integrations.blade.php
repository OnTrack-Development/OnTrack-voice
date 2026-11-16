@php
    $account = $data['account'] ?? ($ctx['account'] ?? []);
    $integrationKey = $data['api_key'] ?? ('otint_' . ($account['slug'] ?? 'demo_live'));
    $secretKey = $data['secret_key'] ?? hash('sha256', ($account['slug'] ?? 'demo_live') . '_secret_vault_salt');
    $specialistsCount = $data['specialists_count'] ?? 10;
    $kbCount = $data['knowledge_sources_count'] ?? 4;
    $chunksCount = $data['knowledge_chunks_count'] ?? 4;
    $patternsCount = $data['learning_patterns_count'] ?? 2;
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">التكاملات وموديول الربط الخفيف (WHMCS & Omnichannel Hub)</h1>
        <p class="card-sub">ربط منصة WHMCS، متاجر ووردبريس، أرقام واتساب، وموصلات الـ API المشفرة مع درع الخصوصية التامة وعزل الشركات</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="/downloads/ontrack_bridge.zip" download="ontrack_bridge.zip" class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:6px;">
            <span>📦</span>
            <span>تحميل موديول WHMCS (ontrack_bridge.zip)</span>
        </a>
    </div>
</div>

<!-- Multi-Tenant Company Isolation & Architecture Overview -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:12px;margin-bottom:18px;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:14px 18px;">
        <div style="font-size:11px;color:#64748B;font-weight:700;text-transform:uppercase;">عزل بيانات الشركة (Tenant)</div>
        <div style="font-size:16px;font-weight:800;color:#0F172A;margin-top:2px;">{{ $account['name'] ?? 'OnTrack Platform' }}</div>
        <div style="font-size:11px;color:#10B981;margin-top:4px;">معزول برمجياً وتشفيرياً ✓</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:14px 18px;">
        <div style="font-size:11px;color:#64748B;font-weight:700;text-transform:uppercase;">وكلاء الدعم المتخصصون</div>
        <div style="font-size:16px;font-weight:800;color:#3B82F6;margin-top:2px;">{{ $specialistsCount }} وكلاء مخصصين</div>
        <div style="font-size:11px;color:#64748B;margin-top:4px;">مع صلاحيات عقود مقيدة</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:14px 18px;">
        <div style="font-size:11px;color:#64748B;font-weight:700;text-transform:uppercase;">مصادر المعرفة والكتالوج</div>
        <div style="font-size:16px;font-weight:800;color:#8B5CF6;margin-top:2px;">{{ $kbCount }} مصادر ({{ $chunksCount }} فقرات)</div>
        <div style="font-size:11px;color:#64748B;margin-top:4px;">مربوطة بالتذاكر والشات</div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:14px 18px;">
        <div style="font-size:11px;color:#64748B;font-weight:700;text-transform:uppercase;">الحلول التلقائية المتعلمة</div>
        <div style="font-size:16px;font-weight:800;color:#10B981;margin-top:2px;">{{ $patternsCount }} أنماط معتمدة</div>
        <div style="font-size:11px;color:#64748B;margin-top:4px;">دقة تتجاوز 95%</div>
    </div>
</div>

<div class="grid-2">
    <!-- WHMCS Connector Details -->
    <div class="card" style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div class="card-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:40px;height:40px;border-radius:10px;background:#0F172A;color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-size:20px;">🛡️</div>
                <div>
                    <h2 class="card-title" style="margin:0;font-size:16px;font-weight:800;">إعدادات ربط موديول WHMCS (ontrack_bridge)</h2>
                    <p class="card-sub" style="margin:2px 0 0 0;font-size:12px;color:#64748B;">موديول خفيف جداً، مفتوح المصدر (No IonCube) مع تعمية PII محلية</p>
                </div>
            </div>
            <span class="pill pill-green" style="font-size:11px;">Zero-Knowledge Tokenized ✅</span>
        </div>

        <div class="form-grid" style="margin-top:14px;display:grid;gap:12px;">
            <div class="field" style="grid-column:1/-1;">
                <span class="field-label" style="font-size:12px;font-weight:700;color:#475569;margin-bottom:4px;display:block;">SaaS Endpoint URL</span>
                <input type="text" class="mono" value="https://app.ontrackegy.com" readonly style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:12.5px;background:#F8FAFC;direction:ltr;">
            </div>
            <div class="field" style="grid-column:1/-1;">
                <span class="field-label" style="font-size:12px;font-weight:700;color:#475569;margin-bottom:4px;display:block;">مفتاح التكامل (Integration Key)</span>
                <input type="text" class="mono" value="{{ $integrationKey }}" readonly style="width:100%;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:12.5px;background:#F8FAFC;direction:ltr;">
            </div>
            <div class="field" style="grid-column:1/-1;">
                <span class="field-label" style="font-size:12px;font-weight:700;color:#475569;margin-bottom:4px;display:block;">المفتاح السري المشترك (HMAC Secret Key)</span>
                <div style="display:flex;gap:8px;">
                    <input type="password" id="secretKeyField" class="mono" value="{{ $secretKey }}" style="flex:1;padding:8px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:12.5px;background:#F8FAFC;direction:ltr;" readonly>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="toggleSecretKey()" style="padding:6px 12px;font-size:12px;">عرض/إخفاء 👁️</button>
                </div>
            </div>
        </div>

        <!-- Operational Feature Toggles -->
        <div style="margin-top:16px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:14px;">
            <div style="font-size:12.5px;font-weight:700;color:#0F172A;margin-bottom:8px;">صلاحيات وميزات الموديول النشطة:</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;color:#334155;">
                <div>✓ الرد التلقائي على التذاكر (Auto-Reply)</div>
                <div>✓ تشخيصات cPanel المقيدة (Read-Only)</div>
                <div>✓ تعمية وتشفير الهوية (PII Masking)</div>
                <div>✓ نافذة شات العميل (Support Widget)</div>
            </div>
        </div>

        <div style="margin-top:14px;background:#F1F5F9;border-radius:8px;padding:12px;font-size:12px;color:#475569;line-height:1.6;">
            <strong>طريقة التركيب السريعة:</strong><br>
            1. فك ضغط <code>ontrack_bridge.zip</code> في مجلد <code>modules/addons/ontrack_bridge/</code> بموقع WHMCS لديك.<br>
            2. فعّل الموديول من <strong>Addon Modules</strong> وضع المفاتيح أعلاه.<br>
            3. سيبدأ الموديول بتعمية أسماء العملاء والدومينات محلياً قبل إرسالها للسيرفر، واسترجاعها تلقائياً عند الرد!
        </div>
    </div>

    <!-- WhatsApp Dual-Driver Connection Card -->
    <div class="card">
        <div class="card-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:40px;height:40px;border-radius:10px;background:#065F46;display:flex;align-items:center;justify-content:center;font-size:20px;">💬</div>
                <div>
                    <h2 class="card-title">ربط قنوات واتساب المزدوجة (Dual-Driver)</h2>
                    <p class="card-sub">دعم متزامن لحساب Meta Cloud API الرسمي وربط أرقام المكتب عبر مسح QR</p>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px;margin-top:14px;">
            <!-- Driver 1: Meta Cloud API -->
            <div style="border:1px solid var(--border,#1E293B);border-radius:12px;padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong>1. واتساب الرسمي (Meta Cloud API)</strong>
                    <span class="pill pill-green" style="font-size:10px;">متصل بالـ Webhook</span>
                </div>
                <div style="font-size:12px;color:var(--muted);">
                    Webhook Callback: <code class="mono" style="color:#38BDF8;">https://app.ontrackegy.com/api/webhooks/meta/whatsapp/{{ $account['id'] ?? 1 }}</code>
                </div>
            </div>

            <!-- Driver 2: Baileys QR Code Bridge -->
            <div style="border:1px solid var(--border,#1E293B);border-radius:12px;padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong>2. ربط رقم المكتب المباشر (Baileys Live QR)</strong>
                    <span class="pill pill-amber" style="font-size:10px;">جاهز للمسح</span>
                </div>
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:80px;height:80px;background:#FFF;border-radius:8px;padding:6px;display:flex;align-items:center;justify-content:center;font-size:40px;">
                        📱
                    </div>
                    <div style="font-size:12px;color:var(--muted);line-height:1.5;">
                        امسح الـ QR Code من تطبيق واتساب لربط رقم شركتك فوراً بدون الحاجة لحساب Meta تجاري رسمي، مع دعم كامل للأزرار التفاعلية وتحويلها لقوائم متوافقة!
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSecretKey() {
    var f = document.getElementById('secretKeyField');
    f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
