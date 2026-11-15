<div class="card-head" style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700;display:flex;align-items:center;gap:8px;">
            <span>🗺️</span>
            <span>خريطة المعمارية والتحقق البصري التفاعلي (Architecture Floorplan)</span>
        </h1>
        <p class="card-sub" style="margin-top:4px;">
            خريطة برمجية تفاعلية حية ترسم كافة شاشات لوحة التحكم، المتحكمات، النواة متعددة الوكلاء، نفق الصوت، وجداول البيانات (Zero-Orphans Codebase Map).
        </p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <button onclick="document.getElementById('floorplanIframe').contentWindow.location.reload();" class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:6px;">
            <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span>تحديث الخريطة</span>
        </button>
        <a href="/admin/floorplan" target="_blank" class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:6px;">
            <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            <span>فتح في نافذة مستقلة (Fullscreen)</span>
        </a>
    </div>
</div>

<!-- Architecture Health & Metric Badges -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-bottom:14px;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:8px;background:#F0FDF4;color:#16A34A;display:flex;align-items:center;justify-content:center;font-size:18px;">✓</div>
        <div>
            <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;">Code Coverage</div>
            <div style="font-size:14px;font-weight:700;color:#0F172A;">795 Files (100% Assigned)</div>
        </div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:8px;background:#F0FDF4;color:#16A34A;display:flex;align-items:center;justify-content:center;font-size:18px;">🛡️</div>
        <div>
            <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;">Health & Secrets</div>
            <div style="font-size:14px;font-weight:700;color:#0F172A;">0 Critical / 0 High Findings</div>
        </div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:8px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:18px;">⚡</div>
        <div>
            <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;">Voice Tunnel</div>
            <div style="font-size:14px;font-weight:700;color:#0F172A;">Binary WSS 16kHz PCM</div>
        </div>
    </div>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:8px;background:#FAF5FF;color:#9333EA;display:flex;align-items:center;justify-content:center;font-size:18px;">🔒</div>
        <div>
            <div style="font-size:11px;color:#64748B;font-weight:600;text-transform:uppercase;">Privacy Mode</div>
            <div style="font-size:14px;font-weight:700;color:#0F172A;">Zero-Knowledge PII Shield</div>
        </div>
    </div>
</div>

<!-- Embedded Interactive Floorplan Viewer -->
<div style="background:#0F172A;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);">
    <iframe id="floorplanIframe" src="/admin/floorplan" style="width:100%;height:calc(100vh - 270px);border:none;display:block;" title="Architecture Floorplan" allowfullscreen></iframe>
</div>
