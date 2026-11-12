<!doctype html>
<html lang="ar" dir="rtl" data-theme="{{ $ctx['theme'] ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $ctx['brand']['name'] ?? 'IO Voice' }} — لوحة التحكم</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/obsidian.css?v=62">
</head>
<body>
@if (!empty($ctx['support']))
    <div class="support-bar">
        <span>وضع الدعم الفني النشط للحساب: <strong>{{ $ctx['account']['name'] ?? '' }}</strong> (الدور: {{ $ctx['role'] }})</span>
        <form method="POST" action="/console/action" style="margin:0">
            @csrf
            <input type="hidden" name="action" value="support_leave">
            <button type="submit" class="btn btn-sm">إنهاء جلسة الدعم</button>
        </form>
    </div>
@endif

<div class="app-shell">
    <div id="ioSidebarScrim" class="sidebar-scrim" onclick="toggleSidebar(false)"></div>

    <aside id="ioSidebar" class="sidebar">
        <div class="brand-bar">
            <div class="brand-mark">
                <img src="/brand-logo" alt="IO Voice" style="width:28px;height:28px">
            </div>
            <div style="min-width:0">
                <div class="brand-name">{{ $ctx['brand']['name'] ?? 'IO Voice' }}</div>
                <div class="brand-sub mono">v{{ config('ontrack.apk_version_name', '0.3.39') }} · {{ $ctx['account']['name'] ?? '' }}</div>
            </div>
        </div>

        <nav class="nav-scroll">
            <div class="nav-group">التشغيل الأساسي</div>
            <a class="nav-item {{ $activeTab === 'overview' ? 'active' : '' }}" href="/?tab=overview">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>لوحة التحكم</span>
            </a>
            <a class="nav-item {{ in_array($activeTab, ['agents', 'voices'], true) ? 'active' : '' }}" href="/?tab=agents">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                <span>الإيجنت والأصوات</span>
            </a>
            <a class="nav-item {{ $activeTab === 'devices' ? 'active' : '' }}" href="/?tab=devices">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18.01"/></svg>
                <span>الشرائح والأجهزة</span>
                @if (($data['online_sims'] ?? 0) > 0)
                    <span class="pill pill-green" style="margin-right:auto;padding:1px 7px">{{ $data['online_sims'] }}</span>
                @endif
            </a>
            <a class="nav-item {{ $activeTab === 'calls' ? 'active' : '' }}" href="/?tab=calls">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <span>سجل المكالمات</span>
            </a>
            <a class="nav-item {{ $activeTab === 'campaigns' ? 'active' : '' }}" href="/?tab=campaigns">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                <span>الحملات وجهات الاتصال</span>
            </a>

            <div class="nav-group">الأتمتة والنظام</div>
            <a class="nav-item {{ $activeTab === 'automations' ? 'active' : '' }}" href="/?tab=automations">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>الأتمتة والربط البرمجي</span>
            </a>
            <a class="nav-item {{ in_array($activeTab, ['billing', 'invoices'], true) ? 'active' : '' }}" href="/?tab=billing">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <span>الفواتير والباقات</span>
            </a>
            <a class="nav-item {{ in_array($activeTab, ['settings', 'integrations', 'staff', 'domains', 'whatsapp'], true) ? 'active' : '' }}" href="/?tab=settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.77 1.01 1.51 1H21a2 2 0 1 1 0 4h-.09c-.74 0-1.25.41-1.51 1z"/></svg>
                <span>الإعدادات والفريق</span>
            </a>

            @if (($ctx['is_platform'] || $ctx['is_reseller']) && $ctx['view_mode'] === 'admin')
                <div class="nav-group">إدارة المنصة والوكلاء</div>
                <a class="nav-item {{ in_array($activeTab, ['accounts', 'plans', 'releases'], true) ? 'active' : '' }}" href="/?tab=accounts">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>الحسابات والباقات والإصدار</span>
                </a>
            @endif
        </nav>

        <div class="sidebar-foot">
            @if (count($ctx['sites'] ?? []) > 1)
                <form method="POST" action="/console/action">
                    @csrf
                    <input type="hidden" name="action" value="switch_site">
                    <select name="target_site_id" onchange="this.form.submit()" style="font-size:12.5px;padding:7px">
                        @foreach ($ctx['sites'] as $s)
                            <option value="{{ $s['id'] }}" {{ (int) $s['id'] === (int) $ctx['site_id'] ? 'selected' : '' }}>موقع: {{ $s['name'] }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="btn btn-sm btn-ghost" style="width:100%;justify-content:flex-start;color:var(--crimson)">
                    تسجيل الخروج ({{ $ctx['user']->name ?? $ctx['user']->email }})
                </button>
            </form>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="btn btn-icon mobile-toggle" onclick="toggleSidebar()" aria-label="القائمة">☰</button>
                <span class="pill">
                    <span class="dot {{ ($data['online_sims'] ?? 0) > 0 ? 'online' : 'offline' }}"></span>
                    <span>{{ ($data['online_sims'] ?? 0) > 0 ? $data['online_sims'].' شريحة متصلة الآن' : 'لا توجد شريحة متصلة حالياً' }}</span>
                </span>
            </div>

            <div class="topbar-right">
                @if (count($ctx['memberships'] ?? []) > 1)
                    <form method="POST" action="/console/action" style="margin:0">
                        @csrf
                        <input type="hidden" name="action" value="switch_account">
                        <select name="target_account_id" onchange="this.form.submit()" style="width:auto;min-width:160px;padding:6px 10px;font-size:12.5px">
                            @foreach ($ctx['memberships'] as $m)
                                <option value="{{ $m['account_id'] }}" {{ (int) $m['account_id'] === (int) $ctx['account_id'] ? 'selected' : '' }}>
                                    {{ $m['name'] }} ({{ $m['kind'] }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <a href="{{ config('ontrack.apk_download_path', '/mobile/updates/native/IOVoice-v62.apk') }}" class="btn btn-sm">تحميل APK</a>

                <button type="button" class="btn btn-icon" data-theme-icon onclick="toggleIoTheme()" aria-label="تبديل المظهر">
                    <svg class="icon-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <svg class="icon-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </header>

        <main class="page-body">
            @if (session('success'))
                <div class="alert alert-ok">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-err">{{ session('error') }}</div>
            @endif
            @if (session('device_pair_code'))
                <div class="alert alert-warn">
                    <div>
                        <strong>كود ربط الشريحة SIM {{ ((int) session('device_pair_slot', 0)) + 1 }} (صالح 5 دقائق):</strong>
                        <span class="mono" style="font-size:20px;font-weight:700;letter-spacing:4px;margin-right:10px">{{ session('device_pair_code') }}</span>
                    </div>
                </div>
            @endif
            @if (session('secret'))
                <div class="alert alert-warn">
                    <div><strong>انسخ المفتاح السري الآن (يظهر مرة واحدة):</strong> <code class="mono" dir="ltr">{{ session('secret') }}</code></div>
                </div>
            @endif

            @php
                $viewMap = [
                    'overview' => 'enterprise.tabs.overview',
                    'agents' => 'enterprise.tabs.agents',
                    'voices' => 'enterprise.tabs.agents',
                    'devices' => 'enterprise.tabs.devices',
                    'calls' => 'enterprise.tabs.calls',
                    'campaigns' => 'enterprise.tabs.campaigns',
                    'automations' => 'enterprise.tabs.automations',
                    'billing' => 'enterprise.tabs.billing',
                    'invoices' => 'enterprise.tabs.billing',
                    'settings' => 'enterprise.tabs.settings',
                    'integrations' => 'enterprise.tabs.settings',
                    'staff' => 'enterprise.tabs.settings',
                    'domains' => 'enterprise.tabs.settings',
                    'whatsapp' => 'enterprise.tabs.settings',
                    'accounts' => 'enterprise.tabs.admin',
                    'plans' => 'enterprise.tabs.admin',
                    'releases' => 'enterprise.tabs.admin',
                ];
            @endphp
            @include($viewMap[$activeTab] ?? 'enterprise.tabs.overview', ['ctx' => $ctx, 'data' => $data, 'activeTab' => $activeTab])
        </main>
    </div>
</div>

<!-- Call Detail Slide-Over Drawer -->
<div id="callDrawerBackdrop" class="drawer-backdrop" onclick="if(event.target===this)closeCallDrawer()">
    <div class="drawer-panel">
        <div class="drawer-head">
            <strong>تفاصيل المكالمة والنص</strong>
            <button type="button" class="btn btn-icon btn-sm" onclick="closeCallDrawer()">✕</button>
        </div>
        <div id="callDrawerBody" class="drawer-body"></div>
    </div>
</div>

<script src="/assets/js/obsidian.js?v=62"></script>
</body>
</html>
