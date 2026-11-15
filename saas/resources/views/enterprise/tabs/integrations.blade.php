@php
    $account = $ctx['account'] ?? [];
    $integrationKey = 'otint_' . ($account['slug'] ?? 'demo_live');
    $secretKey = hash('sha256', ($account['slug'] ?? 'demo_live') . '_secret_vault_salt');
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">التكاملات وموديول الربط الخفيف (Integrations & Connectors)</h1>
        <p class="card-sub">ربط منصة WHMCS، متاجر ووردبريس، أرقام واتساب، وموصلات الـ API المشفرة مع درع الخصوصية التامة</p>
    </div>
    <a href="/downloads/ontrack_bridge.zip" download="ontrack_bridge.zip" class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:6px;">
        <span>📦</span>
        <span>تحميل موديول WHMCS (ontrack_bridge.zip)</span>
    </a>
</div>

<div class="grid-2">
    <!-- WHMCS Connector Details -->
    <div class="card">
        <div class="card-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:40px;height:40px;border-radius:10px;background:#1E293B;display:flex;align-items:center;justify-content:center;font-size:20px;">🛡️</div>
                <div>
                    <h2 class="card-title">إعدادات ربط موديول WHMCS (ontrack_bridge)</h2>
                    <p class="card-sub">موديول خفيف جداً، مفتوح المصدر (No IonCube) مع تعمية PII محلية</p>
                </div>
            </div>
            <span class="pill pill-green">Zero-Knowledge Tokenized ✅</span>
        </div>

        <div class="form-grid" style="margin-top:14px;">
            <div class="field" style="grid-column:1/-1;">
                <span class="field-label">SaaS Endpoint URL</span>
                <input type="text" class="mono" value="https://app.ontrackegy.com" readonly>
            </div>
            <div class="field" style="grid-column:1/-1;">
                <span class="field-label">مفتاح التكامل (Integration Key)</span>
                <input type="text" class="mono" value="{{ $integrationKey }}" readonly>
            </div>
            <div class="field" style="grid-column:1/-1;">
                <span class="field-label">المفتاح السري المشترك (HMAC Secret Key)</span>
                <div style="display:flex;gap:8px;">
                    <input type="password" id="secretKeyField" class="mono" value="{{ $secretKey }}" style="flex:1;" readonly>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="toggleSecretKey()">عرض/إخفاء 👁️</button>
                </div>
            </div>
        </div>

        <div style="margin-top:16px;background:rgba(255,255,255,0.04);border-radius:10px;padding:14px;font-size:12.5px;color:var(--muted);line-height:1.6;">
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
