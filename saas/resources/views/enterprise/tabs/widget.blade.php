@php
    $agentUuid = $data['agent_uuid'] ?? 'demo-agent-1';
    $embedSnippet = $data['embed_snippet'] ?? '<script src="https://app.ontrackegy.com/widget/ontrack-widget.js" data-agent="' . $agentUuid . '" async></script>';
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">مُنشئ ومُخصص ودجت الموقع (Website Widget Builder)</h1>
        <p class="card-sub">تخصيص ويدجت المكالمات الصوتية والشات العائمة لموقعك أو متجرك بنظام Zoie.ai Luxury Experience</p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="copyWidgetCode()">📋 نسخ كود التضمين</button>
</div>

<div class="grid-2">
    <!-- Left: Customization Form -->
    <div class="card" style="display:flex;flex-direction:column;gap:18px;">
        <h2 class="card-title">إعدادات المظهر والتشغيل</h2>

        <div class="form-grid">
            <div class="field">
                <span class="field-label">اللون الرئيسي للويدجت (Brand Primary Color)</span>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="color" id="pickerColor" value="#8B5CF6" style="width:44px;height:40px;border:none;cursor:pointer;border-radius:8px;">
                    <input type="text" id="hexColor" value="#8B5CF6" style="width:120px;" class="mono">
                </div>
            </div>

            <div class="field">
                <span class="field-label">موقع الزر العائم في الصفحة</span>
                <select id="widgetPosition">
                    <option value="right" selected>أسفل اليمين (Bottom Right)</option>
                    <option value="left">أسفل اليسار (Bottom Left)</option>
                </select>
            </div>

            <div class="field" style="grid-column:1/-1;">
                <span class="field-label">رسالة الفقاعة الترحيبية (Greeting Prompt)</span>
                <input type="text" id="greetingText" value="أهلاً بك! 👋 تحب تتكلم معايا صوت ولا شات؟ 🎙️💬">
            </div>

            <div class="field" style="grid-column:1/-1;">
                <span class="field-label">أوضاع التشغيل المسموحة في الويدجت</span>
                <div style="display:flex;gap:20px;margin-top:6px;">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" checked id="chkVoice"> <strong>🎙️ المكالمة الصوتية المباشرة</strong>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" checked id="chkChat"> <strong>💬 الشات النصي والأزرار</strong>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" checked id="chkWhitelabel"> <strong>🛡️ وضع العلامة البيضاء (إخفاء الشعار)</strong>
                    </label>
                </div>
            </div>
        </div>

        <div style="border-top:1px solid var(--border,#E2E8F0);padding-top:16px;">
            <span class="field-label" style="margin-bottom:6px;">كود التضمين لموقعك (Embed Script Tag):</span>
            <div style="position:relative;">
                <textarea id="widgetCodeSnippet" style="width:100%;height:75px;font-family:monospace;font-size:12px;background:#0F172A;color:#38BDF8;padding:12px;border-radius:10px;border:none;" readonly>{{ $embedSnippet }}</textarea>
                <button type="button" onclick="copyWidgetCode()" class="btn btn-sm btn-primary" style="position:absolute;bottom:10px;left:10px;font-size:11px;">نسخ الكود</button>
            </div>
            <p style="font-size:12px;color:var(--muted);margin-top:6px;">
                ضَع هذا السطر في أي مكان داخل موقعك (قبل إغلاق وسَم <code>&lt;/body&gt;</code>) ليعمل المساعد الصوتي والشات فوراً!
            </p>
        </div>
    </div>

    <!-- Right: Live Interactive Preview Card -->
    <div class="card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0B0F19;border-color:#1E293B;min-height:480px;position:relative;overflow:hidden;">
        <div style="position:absolute;top:16px;right:20px;font-size:12px;color:#94A3B8;font-weight:bold;">
            معاينة حية تفاعلية (Live Widget Preview)
        </div>

        <!-- Rendered Mini Launcher Mockup -->
        <div style="position:absolute;bottom:30px;right:30px;display:flex;align-items:center;gap:12px;">
            <div style="background:#0F172A;color:#FFF;padding:12px 16px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.1);font-size:12.5px;max-width:240px;" id="previewBubble">
                أهلاً بك! 👋 تحب تتكلم معايا صوت ولا شات؟ 🎙️💬
                <div style="display:flex;gap:6px;margin-top:8px;">
                    <span style="background:rgba(255,255,255,0.12);padding:4px 8px;border-radius:12px;font-size:10.5px;font-weight:bold;">🎙️ مكالمة</span>
                    <span style="background:rgba(255,255,255,0.12);padding:4px 8px;border-radius:12px;font-size:10.5px;font-weight:bold;">💬 شات</span>
                </div>
            </div>
            <div style="width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg, #8B5CF6, #06B6D4);box-shadow:0 0 25px rgba(139,92,246,0.6);display:flex;align-items:center;justify-content:center;font-size:26px;color:#FFF;cursor:pointer;position:relative;" id="previewLauncherBtn">
                🎙️
                <div style="position:absolute;bottom:2px;right:2px;width:14px;height:14px;border-radius:50%;background:#10B981;border:2px solid #0B0F19;"></div>
            </div>
        </div>

        <div style="text-align:center;color:rgba(255,255,255,0.3);font-size:13px;pointer-events:none;">
            [مساحة تمثيلية لصفحة موقع العميل]<br>
            الودجت تظهر في الركن الأيمن أو الأيسر فوق محتوى الصفحة
        </div>
    </div>
</div>

<script>
function copyWidgetCode() {
    var copyText = document.getElementById("widgetCodeSnippet");
    copyText.select();
    navigator.clipboard.writeText(copyText.value);
    alert("تم نسخ كود التضمين بنجاح!");
}
document.getElementById('pickerColor').addEventListener('input', function(e) {
    document.getElementById('hexColor').value = e.target.value;
    document.getElementById('previewLauncherBtn').style.background = e.target.value;
});
document.getElementById('greetingText').addEventListener('input', function(e) {
    document.getElementById('previewBubble').childNodes[0].nodeValue = e.target.value;
});
</script>
