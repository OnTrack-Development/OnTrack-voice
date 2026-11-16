<!doctype html>
<html lang="ar" dir="rtl" data-theme="{{ $ctx['theme'] ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $ctx['brand']['name'] ?? 'OnTrack Voice' }} — لوحة التحكم</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/obsidian.css?v=69">
    <style>
        /* OnTrack Dual-Contrast UI Enforcement (Pure Arabic RTL) */
        body {
            background: #0B0F19 !important;
            font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif !important;
            direction: rtl !important;
        }
        #ioSidebar {
            background: #0B0F19 !important;
            border-left: 1px solid #1E293B !important;
            border-right: none !important;
            width: 250px !important;
        }
        @media (max-width: 960px) {
            .sidebar {
                right: 0 !important;
                left: auto !important;
                transform: translateX(100%) !important;
            }
            .sidebar.open {
                transform: translateX(0) !important;
            }
        }
        @media (max-width: 768px) {
            .topbar {
                height: 56px !important;
                min-height: 56px !important;
                max-height: 56px !important;
                padding: 0 12px !important;
                flex-wrap: nowrap !important;
                overflow: hidden !important;
                justify-content: space-between !important;
            }
            .topbar-desktop-only {
                display: none !important;
            }
            .mobile-only-brand {
                display: inline-block !important;
            }
            .topbar-left, .topbar-right {
                flex-wrap: nowrap !important;
                gap: 8px !important;
            }
            .page-body {
                padding: 12px !important;
            }
            .kpi-grid {
                grid-template-columns: 1fr !important;
            }
        }
        #ioSidebar .brand-bar {
            border-bottom: 1px solid #1E293B !important;
            padding: 16px 18px !important;
        }
        #ioSidebar .brand-name {
            color: #FFFFFF !important;
            font-weight: 800 !important;
            font-size: 16px !important;
            letter-spacing: -0.5px;
        }
        #ioSidebar .brand-sub {
            color: #64748B !important;
            font-size: 11px !important;
        }
        #ioSidebar .nav-group {
            color: #475569 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 18px 4px !important;
        }
        #ioSidebar .nav-item {
            color: #94A3B8 !important;
            border-radius: 8px !important;
            margin: 2px 10px !important;
            padding: 7px 12px !important;
            font-weight: 500 !important;
            font-size: 13px !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            text-decoration: none !important;
            transition: all 0.15s ease !important;
        }
        #ioSidebar .nav-item:hover {
            background: #1E293B !important;
            color: #FFFFFF !important;
        }
        #ioSidebar .nav-item.active {
            background: #1E293B !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
        }
        #ioSidebar .nav-item svg {
            width: 17px !important;
            height: 17px !important;
            stroke-width: 2 !important;
            flex-shrink: 0 !important;
        }
        #ioSidebar .nav-item.active svg {
            stroke: #38BDF8 !important;
        }
        #ioSidebar .sidebar-foot {
            border-top: 1px solid #1E293B !important;
            background: #0B0F19 !important;
            padding: 14px 16px !important;
        }

        /* Topbar Dark Navy */
        .topbar {
            background: #0B0F19 !important;
            border-bottom: 1px solid #1E293B !important;
            height: 56px !important;
            padding: 0 20px !important;
        }

        /* Main Content Canvas Crisp White & Slate */
        .main-area {
            background: #F8FAFC !important;
            color: #0F172A !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 100vh !important;
        }
        .page-body {
            background: #F8FAFC !important;
            color: #0F172A !important;
            padding: 24px !important;
            flex: 1 !important;
        }
        .card {
            background: #FFFFFF !important;
            border: 1px solid #E2E8F0 !important;
            border-radius: 14px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
            color: #0F172A !important;
        }
        .card-title, h1, h2, h3, h4, strong {
            color: #0F172A !important;
        }
        .card-sub, .text-muted {
            color: #64748B !important;
        }
        .data-table, table {
            background: #FFFFFF !important;
            color: #0F172A !important;
        }
        .data-table thead tr, table thead tr {
            background: #F8FAFC !important;
            color: #64748B !important;
            border-bottom: 1px solid #E2E8F0 !important;
        }
        .data-table th, table th {
            color: #64748B !important;
            font-weight: 600 !important;
        }
        .data-table td, table td {
            border-bottom: 1px solid #F1F5F9 !important;
            color: #0F172A !important;
        }
        .data-table tr:hover td, table tr:hover td {
            background: #F8FAFC !important;
        }
        .kpi-card {
            background: #FFFFFF !important;
            border: 1px solid #E2E8F0 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
        }
        .kpi-label {
            color: #64748B !important;
        }
        .kpi-value {
            color: #0F172A !important;
        }
        .kpi-meta {
            color: #94A3B8 !important;
        }
        /* Mandatory Input Styling Rules (Enforced with !important) */
        input:not([type="checkbox"]):not([type="radio"]), textarea, select {
            background-color: #FFFFFF !important;
            color: #0F172A !important;
            border: 1px solid #CBD5E1 !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            font-size: 13.5px !important;
            box-sizing: border-box !important;
        }
        input::placeholder, textarea::placeholder {
            color: #64748B !important;
            opacity: 1 !important;
        }
        input:not([type="checkbox"]):not([type="radio"]):focus, textarea:focus, select:focus {
            background-color: #FFFFFF !important;
            color: #0F172A !important;
            border-color: #4F46E5 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important;
        }
        .topbar input:not([type="checkbox"]):not([type="radio"]) {
            background-color: #1E293B !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            color: #FFFFFF !important;
        }
        .topbar input::placeholder {
            color: #94A3B8 !important;
        }
        .btn-primary {
            background: #0F172A !important;
            color: #FFFFFF !important;
            border: none !important;
            border-radius: 8px !important;
        }
        .btn-primary:hover {
            background: #1E293B !important;
        }
        .btn-ghost {
            background: #FFFFFF !important;
            border: 1px solid #E2E8F0 !important;
            color: #0F172A !important;
            border-radius: 8px !important;
        }
        .btn-ghost:hover {
            background: #F8FAFC !important;
        }
    </style>
</head>
<body>
@if (!empty($ctx['support']))
    <div class="support-bar" style="background:#4338CA;color:#FFF;padding:8px 16px;display:flex;justify-content:space-between;align-items:center;font-size:13px;">
        <span>مساحة العمل النشطة: <strong>{{ $ctx['account']['name'] ?? '' }}</strong> (الدور: {{ $ctx['role'] }})</span>
        <form method="POST" action="/console/action" style="margin:0">
            @csrf
            <input type="hidden" name="action" value="support_leave">
            <button type="submit" class="btn btn-sm" style="background:#FFF;color:#4338CA;border:none;font-weight:700;">العودة للحساب الرئيسي</button>
        </form>
    </div>
@endif

@if (($ctx['is_platform'] ?? false) === false && ($data['credits'] ?? 100) <= 0)
<!-- Out of credits alert banner (100% Arabic RTL) -->
<div style="background:#FFFFFF;color:#EF4444;border-bottom:1px solid #F1F5F9;padding:8px 16px;text-align:center;font-size:12.5px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
    <span style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;border:1.5px solid #EF4444;font-size:10px;font-weight:800;">!</span>
    <span>تنبيه الرصيد:</span>
    <span style="color:#64748B;font-weight:400;">المكالمات، الرسائل، الحملات والذكاء الاصطناعي متوقفة مؤقتاً حتى إعادة الشحن.</span>
    <a href="/?tab=billing" style="background:#0F172A;color:#FFFFFF;padding:3px 12px;border-radius:6px;font-size:11.5px;text-decoration:none;font-weight:700;margin-right:6px;">شحن الرصيد</a>
</div>
@endif

<div class="app-shell">
    <div id="ioSidebarScrim" class="sidebar-scrim" onclick="toggleSidebar(false)"></div>

    <aside id="ioSidebar" class="sidebar">
        <!-- Brand Mark: OnTrack / OnTrack -->
        <div class="brand-bar">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg, #EC4899, #8B5CF6);display:grid;place-items:center;font-weight:900;color:#FFF;font-size:18px;">
                    //
                </div>
                <div style="min-width:0">
                    <div class="brand-name">{{ $ctx['brand']['name'] ?? 'OnTrack' }}</div>
                    <div class="brand-sub mono">OnTrack Voice · {{ $ctx['account']['name'] ?? '' }}</div>
                </div>
            </div>
        </div>

        <nav class="nav-scroll">
            <!-- 1. Top Core -->
            <a class="nav-item {{ $activeTab === 'overview' ? 'active' : '' }}" href="/?tab=overview">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>لوحة التحكم</span>
            </a>
            <a class="nav-item {{ $activeTab === 'leads' ? 'active' : '' }}" href="/?tab=leads">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>العملاء المحتملون</span>
            </a>
            <a class="nav-item {{ $activeTab === 'appointments' ? 'active' : '' }}" href="/?tab=appointments">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>المواعيد والتقويم</span>
            </a>
            <a class="nav-item {{ $activeTab === 'reports' ? 'active' : '' }}" href="/?tab=reports">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                <span>التقارير والإحصائيات</span>
            </a>

            <!-- 2. Inbox Group -->
            <div class="nav-group">صندوق الوارد</div>
            <a class="nav-item {{ $activeTab === 'calls' ? 'active' : '' }}" href="/?tab=calls">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <span>سجل المكالمات</span>
            </a>
            <a class="nav-item {{ $activeTab === 'messages' ? 'active' : '' }}" href="/?tab=messages">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <span>الرسائل والمحادثات</span>
            </a>
            <a class="nav-item {{ $activeTab === 'tickets' ? 'active' : '' }}" href="/?tab=tickets">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span>البريد والتذاكر</span>
            </a>

            <!-- 3. Outreach Group -->
            <div class="nav-group">الوصول والحملات</div>
            <a class="nav-item {{ $activeTab === 'widget' ? 'active' : '' }}" href="/?tab=widget">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/></svg>
                <span>ودجت الموقع</span>
            </a>
            <a class="nav-item {{ $activeTab === 'campaigns' ? 'active' : '' }}" href="/?tab=campaigns">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                <span>الحملات الهاتفية</span>
            </a>
            <a class="nav-item {{ $activeTab === 'devices' ? 'active' : '' }}" href="/?tab=devices">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18.01"/></svg>
                <span>شرائح الهاتف (SIM)</span>
                @if (($data['online_sims'] ?? 0) > 0)
                    <span class="pill pill-green" style="margin-right:auto;padding:1px 7px;font-size:10.5px;">{{ $data['online_sims'] }}</span>
                @endif
            </a>

            <!-- 4. Intelligence Group -->
            <div class="nav-group">الذكاء الاصطناعي</div>
            <a class="nav-item {{ in_array($activeTab, ['agents', 'voices'], true) ? 'active' : '' }}" href="/?tab=agents">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                <span>استوديو الوكلاء</span>
            </a>
            <a class="nav-item {{ in_array($activeTab, ['api_keys', 'api-keys', 'providers'], true) ? 'active' : '' }}" href="/?tab=api_keys">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21 2-2 2m-6 6 2-2m-8 8 2-2m-6 6 2-2"/><circle cx="7.5" cy="16.5" r="4.5"/><path d="m10.5 13.5 8-8a2.121 2.121 0 0 1 3 3l-8 8"/></svg>
                <span>مزودي ومفاتيح AI</span>
            </a>
            <a class="nav-item {{ $activeTab === 'activity' ? 'active' : '' }}" href="/?tab=activity">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>سجل النشاط والتعلم</span>
            </a>

            <!-- 5. Automate Group -->
            <div class="nav-group">الأتمتة</div>
            <a class="nav-item {{ $activeTab === 'power_dialer' ? 'active' : '' }}" href="/?tab=power_dialer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>المتصل الآلي</span>
            </a>
            <a class="nav-item {{ in_array($activeTab, ['workflows', 'automations'], true) ? 'active' : '' }}" href="/?tab=workflows">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.77 1.01 1.51 1H21a2 2 0 1 1 0 4h-.09c-.74 0-1.25.41-1.51 1z"/></svg>
                <span>مسارات العمل</span>
            </a>
            <a class="nav-item {{ $activeTab === 'integrations' ? 'active' : '' }}" href="/?tab=integrations">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                <span>الربط الخارجي (WHMCS)</span>
            </a>

            <!-- 6. Billing & Settings -->
            <div class="nav-group">الفوترة والإعدادات</div>
            <a class="nav-item {{ in_array($activeTab, ['billing', 'invoices'], true) ? 'active' : '' }}" href="/?tab=billing">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>الباقات والفوترة</span>
            </a>
            <a class="nav-item {{ $activeTab === 'settings' ? 'active' : '' }}" href="/?tab=settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.77 1.01 1.51 1H21a2 2 0 1 1 0 4h-.09c-.74 0-1.25.41-1.51 1z"/></svg>
                <span>الإعدادات والأمان</span>
            </a>

            @if ($ctx['is_reseller'] || $ctx['is_platform'])
                <div class="nav-group">إدارة الموزعين (Resellers)</div>
                <a class="nav-item {{ $activeTab === 'reseller-tenants' ? 'active' : '' }}" href="/?tab=reseller-tenants">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>العملاء التابعون</span>
                </a>
                <a class="nav-item {{ $activeTab === 'reseller-quotas' ? 'active' : '' }}" href="/?tab=reseller-quotas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    <span>الحصص والباقات</span>
                </a>
                <a class="nav-item {{ $activeTab === 'domains' ? 'active' : '' }}" href="/?tab=domains">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span>الهوية والنطاقات</span>
                </a>
            @endif

            @if ($ctx['is_platform'])
                <div class="nav-group">إدارة المنصة الرئيسية</div>
                <a class="nav-item {{ $activeTab === 'accounts' ? 'active' : '' }}" href="/?tab=accounts">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="13" y2="13"/></svg>
                    <span>الحسابات والريسيلرز</span>
                </a>
                <a class="nav-item {{ $activeTab === 'platform-ai' ? 'active' : '' }}" href="/?tab=platform-ai">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                    <span>الذكاء الاصطناعي والأصوات</span>
                </a>
                <a class="nav-item {{ in_array($activeTab, ['platform-plans', 'plans'], true) ? 'active' : '' }}" href="/?tab=platform-plans">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>الباقات والفوترة والاعتماد</span>
                </a>
                <a class="nav-item {{ $activeTab === 'releases' ? 'active' : '' }}" href="/?tab=releases">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>النظام وإصدارات Android</span>
                </a>
                <a class="nav-item {{ $activeTab === 'floorplan' ? 'active' : '' }}" href="/?tab=floorplan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                    <span>خريطة المعمارية (Floorplan)</span>
                </a>
            @endif
        </nav>

        <!-- Sidebar Foot: Credits / Open Billing & User -->
        <div class="sidebar-foot">
            @if (($ctx['is_platform'] ?? false) || ($data['credits'] ?? 0) > 0)
                <div style="background:#1E293B;border-radius:8px;padding:8px 12px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#10B981;font-weight:700;">
                        <span>⚡</span>
                        <span>{{ ($ctx['is_platform'] ?? false) ? 'رصيد غير محدود' : ($data['credits'] ?? 0) . ' رصيد' }}</span>
                    </div>
                    <a href="/?tab=billing" style="color:#38BDF8;font-size:11.5px;font-weight:600;text-decoration:none;">الباقات والفوترة</a>
                </div>
            @else
                <div style="background:#1E293B;border-radius:8px;padding:8px 12px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#F59E0B;font-weight:700;">
                        <span>⚠️</span>
                        <span>0 رصيد</span>
                    </div>
                    <a href="/?tab=billing" style="color:#38BDF8;font-size:11.5px;font-weight:600;text-decoration:none;">شحن الرصيد</a>
                </div>
            @endif

            @if (count($ctx['sites'] ?? []) > 1)
                <form method="POST" action="/console/action" style="margin-bottom:8px;">
                    @csrf
                    <input type="hidden" name="action" value="switch_site">
                    <select name="target_site_id" onchange="this.form.submit()" style="font-size:12px;padding:6px;width:100%;background:#1E293B;color:#FFF;border:1px solid rgba(255,255,255,0.1);">
                        @foreach ($ctx['sites'] as $s)
                            <option value="{{ $s['id'] }}" {{ (int) $s['id'] === (int) $ctx['site_id'] ? 'selected' : '' }}>موقع: {{ $s['name'] }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="btn btn-sm btn-ghost" style="width:100%;justify-content:flex-start;color:#EF4444;background:transparent;border:none;padding:6px 0;font-size:12px;">
                    تسجيل الخروج ({{ $ctx['user']->name ?? $ctx['user']->email }})
                </button>
            </form>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left" style="display:flex;align-items:center;gap:12px;">
                <button type="button" class="btn btn-icon mobile-toggle" onclick="toggleSidebar()" aria-label="القائمة" style="color:#FFF;background:none;border:none;font-size:20px;cursor:pointer;">☰</button>
                <span class="mobile-only-brand" style="display:none;font-weight:700;font-size:14px;color:#FFFFFF;letter-spacing:-0.3px;">OnTrack Voice</span>
                <div class="topbar-desktop-only" style="position:relative;display:flex;align-items:center;">
                    <input type="text" placeholder="بحث برقم الهاتف، الاسم، أو البريد... (Ctrl + K)" style="background:#1E293B;color:#FFF;border:1px solid rgba(255,255,255,0.1);padding:6px 14px 6px 36px;border-radius:20px;font-size:12px;width:240px;outline:none;" onkeydown="if(event.key==='k' && (event.ctrlKey||event.metaKey)){event.preventDefault();this.focus();}">
                    <span style="position:absolute;left:12px;color:#94A3B8;font-size:13px;">🔍</span>
                </div>
                <div class="topbar-desktop-only" style="display:flex;align-items:center;gap:8px;margin-right:12px;">
                    <button type="button" class="btn btn-sm btn-icon" style="background:#1E293B;color:#94A3B8;border:none;border-radius:8px;" title="سجل المكالمات" onclick="window.location.href='/?tab=calls'">📞</button>
                    <button type="button" class="btn btn-sm btn-icon" style="background:#1E293B;color:#94A3B8;border:none;border-radius:8px;" title="الرسائل" onclick="window.location.href='/?tab=messages'">💬</button>
                    <button type="button" class="btn btn-sm btn-icon" style="background:#1E293B;color:#94A3B8;border:none;border-radius:8px;" title="التذاكر" onclick="window.location.href='/?tab=tickets'">✉️</button>
                </div>
            </div>

            <div class="topbar-right" style="display:flex;align-items:center;gap:10px;">
                <button type="button" class="btn btn-sm topbar-desktop-only" onclick="alert('مساعد OnTrack AI جاهز لمساعدتك في أي سؤال حول المنصة أو الإيجنت.')" style="background:linear-gradient(135deg, #8B5CF6, #06B6D4);color:#FFF;border:none;border-radius:20px;font-weight:700;display:flex;align-items:center;gap:6px;box-shadow:0 4px 15px rgba(139,92,246,0.3);padding:6px 14px;font-size:12px;">
                    <span>✨</span>
                    <span>+ اسأل OnTrack AI</span>
                </button>

                @if (count($ctx['switchable_accounts'] ?? []) > 1)
                    <form method="POST" action="/console/action" class="topbar-switch-form topbar-desktop-only">
                        @csrf
                        <input type="hidden" name="action" value="quick_switch_tenant">
                        <select name="target_account_id" aria-label="التبديل السريع لمساحة العمل" onchange="this.form.submit()" class="topbar-switch-select" style="background:#1E293B;color:#FFF;border:1px solid rgba(255,255,255,0.12);padding:5px 8px;border-radius:8px;font-size:12px;">
                            @foreach ($ctx['switchable_accounts'] as $sw)
                                <option value="{{ $sw['id'] }}" {{ (int) $sw['id'] === (int) $ctx['account_id'] ? 'selected' : '' }}>
                                    {{ $sw['name'] }} ({{ $sw['kind'] }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <a href="{{ config('ontrack.apk_download_path', '/mobile/updates/native/IOVoice-v62.apk') }}" download="OnTrack-Voice-{{ config('ontrack.apk_version_name', '0.3.58') }}.apk" class="btn btn-sm topbar-desktop-only" style="background:#1E293B;color:#FFF;border:1px solid rgba(255,255,255,0.12);border-radius:8px;">APK v81</a>

                <!-- User Profile Avatar -->
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:32px;height:32px;border-radius:50%;background:#3B82F6;color:#FFF;display:grid;place-items:center;font-weight:700;font-size:12px;">
                        {{ mb_substr($ctx['user']->name ?? 'AB', 0, 2) }}
                    </div>
                    <span class="topbar-desktop-only" style="font-size:12.5px;color:#FFFFFF;font-weight:600;">{{ $ctx['user']->name ?? 'Ahmed Bedir' }}</span>
                </div>
            </div>
        </header>

        <main class="page-body">
            @if (session('success'))
                <div class="alert alert-ok" style="background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:16px;">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-err" style="background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;padding:12px 16px;border-radius:10px;margin-bottom:16px;">{{ session('error') }}</div>
            @endif
            @if (session('device_pair_code'))
                <div class="alert alert-warn" style="background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;padding:14px 18px;border-radius:10px;margin-bottom:16px;">
                    <div>
                        <strong>كود ربط الشريحة SIM {{ ((int) session('device_pair_slot', 0)) + 1 }} (صالح 5 دقائق):</strong>
                        <span class="mono" style="font-size:20px;font-weight:700;letter-spacing:4px;margin-right:10px;color:#B45309;">{{ session('device_pair_code') }}</span>
                    </div>
                </div>
            @endif
            @if (session('secret'))
                <div class="alert alert-warn" style="background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;padding:14px 18px;border-radius:10px;margin-bottom:16px;">
                    <div><strong>انسخ المفتاح السري الآن (يظهر مرة واحدة):</strong> <code class="mono" dir="ltr" style="background:#FDE68A;padding:2px 8px;border-radius:4px;">{{ session('secret') }}</code></div>
                </div>
            @endif

            @php
                $viewMap = [
                    'overview' => 'enterprise.tabs.overview',
                    'leads' => 'enterprise.tabs.leads',
                    'appointments' => 'enterprise.tabs.appointments',
                    'reports' => 'enterprise.tabs.reports',
                    'calls' => 'enterprise.tabs.calls',
                    'messages' => 'enterprise.tabs.messages',
                    'tickets' => 'enterprise.tabs.tickets',
                    'campaigns' => 'enterprise.tabs.campaigns',
                    'devices' => 'enterprise.tabs.devices',
                    'widget' => 'enterprise.tabs.widget',
                    'engagement' => 'enterprise.tabs.widget',
                    'agents' => 'enterprise.tabs.agents',
                    'voices' => 'enterprise.tabs.agents',
                    'activity' => 'enterprise.tabs.activity',
                    'power_dialer' => 'enterprise.tabs.power_dialer',
                    'workflows' => 'enterprise.tabs.workflows',
                    'automations' => 'enterprise.tabs.workflows',
                    'integrations' => 'enterprise.tabs.integrations',
                    'billing' => 'enterprise.tabs.billing',
                    'invoices' => 'enterprise.tabs.billing',
                    'settings' => 'enterprise.tabs.settings',
                    'staff' => 'enterprise.tabs.settings',
                    'domains' => 'enterprise.tabs.settings',
                    'whatsapp' => 'enterprise.tabs.settings',
                    'accounts' => 'enterprise.tabs.admin',
                    'plans' => 'enterprise.tabs.admin',
                    'releases' => 'enterprise.tabs.admin',
                    'reseller-tenants' => 'enterprise.tabs.admin',
                    'reseller-quotas' => 'enterprise.tabs.admin',
                    'platform-ai' => 'enterprise.tabs.admin',
                    'platform-plans' => 'enterprise.tabs.admin',
                    'floorplan' => 'enterprise.tabs.floorplan',
                    'api_keys' => 'enterprise.tabs.api_keys',
                    'api-keys' => 'enterprise.tabs.api_keys',
                    'providers' => 'enterprise.tabs.api_keys',
                ];
            @endphp
            @include($viewMap[$activeTab] ?? 'enterprise.tabs.overview', ['ctx' => $ctx, 'data' => $data, 'activeTab' => $activeTab])
        </main>
    </div>
</div>

<!-- Call Detail Slide-Over Drawer -->
<div id="callDrawerBackdrop" class="drawer-backdrop" onclick="if(event.target===this)closeCallDrawer()">
    <div class="drawer-panel" style="background:#FFFFFF;color:#0F172A;">
        <div class="drawer-head" style="border-bottom:1px solid #E2E8F0;padding:16px 20px;">
            <strong style="font-size:16px;color:#0F172A;">تفاصيل المكالمة والنص</strong>
            <button type="button" class="btn btn-icon btn-sm" onclick="closeCallDrawer()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>
        <div id="callDrawerBody" class="drawer-body" style="padding:20px;color:#0F172A;"></div>
    </div>
</div>

<script src="/assets/js/obsidian.js?v=63"></script>
</body>
</html>