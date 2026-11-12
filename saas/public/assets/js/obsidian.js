(() => {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function showToast(msg, ok = true) {
        let stack = document.getElementById('ioToastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'ioToastStack';
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        const el = document.createElement('div');
        el.className = 'toast-item';
        el.style.borderRight = `3px solid ${ok ? 'var(--green)' : 'var(--crimson)'}`;
        el.textContent = msg;
        stack.appendChild(el);
        setTimeout(() => el.remove(), 3800);
    }

    // Theme toggle (icon-only persisted Light/Dark)
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem('iovoice_theme', theme); } catch (e) {}
        document.querySelectorAll('[data-theme-icon]').forEach((btn) => {
            const sun = btn.querySelector('.icon-sun');
            const moon = btn.querySelector('.icon-moon');
            if (sun && moon) {
                sun.style.display = theme === 'dark' ? 'block' : 'none';
                moon.style.display = theme === 'light' ? 'block' : 'none';
            }
        });
    }

    try {
        const saved = localStorage.getItem('iovoice_theme');
        if (saved === 'light' || saved === 'dark') {
            applyTheme(saved);
        }
    } catch (e) {}

    window.toggleIoTheme = async function () {
        const cur = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
        const next = cur === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        try {
            await fetch('/console/action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ action: 'toggle_theme', theme: next }),
            });
        } catch (e) {}
    };

    // Mobile Sidebar Drawer
    window.toggleSidebar = function (force) {
        const sb = document.getElementById('ioSidebar');
        const scrim = document.getElementById('ioSidebarScrim');
        if (!sb || !scrim) return;
        const open = typeof force === 'boolean' ? force : !sb.classList.contains('open');
        sb.classList.toggle('open', open);
        scrim.classList.toggle('open', open);
    };

    // Voice Selection inside Agent Studio
    window.selectAgentVoice = function (code, label) {
        const inp = document.getElementById('agentVoiceInput');
        const lbl = document.getElementById('agentSelectedVoiceLabel');
        if (inp) inp.value = code;
        if (lbl) lbl.textContent = label + ' (' + code + ')';
        document.querySelectorAll('.voice-card').forEach((card) => {
            card.classList.toggle('selected', card.dataset.voiceCode === code);
        });
    };

    window.filterVoiceCards = function () {
        const q = (document.getElementById('voiceSearchInput')?.value || '').trim().toLowerCase();
        const provider = document.getElementById('voiceProviderFilter')?.value || '';
        const gender = document.getElementById('voiceGenderFilter')?.value || '';
        document.querySelectorAll('.voice-card').forEach((card) => {
            const hay = (card.dataset.voiceSearch || '').toLowerCase();
            const matchQ = !q || hay.includes(q);
            const matchP = !provider || card.dataset.voiceProvider === provider;
            const matchG = !gender || card.dataset.voiceGender === gender;
            card.style.display = (matchQ && matchP && matchG) ? 'flex' : 'none';
        });
    };

    // Zero-token Voice Sample Preview
    let currentAudio = null;
    window.playVoicePreview = async function (voiceId, btn) {
        try {
            if (currentAudio) {
                currentAudio.pause();
                currentAudio = null;
            }
            const cachedUrl = btn.dataset.cachedUrl;
            if (cachedUrl) {
                currentAudio = new Audio(cachedUrl);
                await currentAudio.play();
                showToast('تم تشغيل العينة المحفوظة محلياً (0 توكن)');
                return;
            }
            btn.disabled = true;
            const res = await fetch('/console/action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ action: 'voice_sample_play', voice_id: voiceId }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok || !data.url) {
                showToast(data.error || 'تعذر تحميل العينة الصوتية', false);
                return;
            }
            btn.dataset.cachedUrl = data.url;
            currentAudio = new Audio(data.url);
            await currentAudio.play();
            showToast(data.cached ? 'تشغيل من الكاش المحلي (0 توكن)' : 'تم حفظ العينة محلياً للمرات القادمة');
        } catch (err) {
            showToast('تعذر تشغيل الصوت: ' + (err.message || ''), false);
        } finally {
            btn.disabled = false;
        }
    };

    // Inline Hot Agent Swap on SIM Card
    window.hotSwapDeviceAgent = async function (deviceId, agentId, selectEl) {
        try {
            selectEl.disabled = true;
            const res = await fetch('/console/action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ action: 'change_device_agent', device_id: deviceId, agent_id: agentId }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                showToast(data.error || 'تعذر تبديل الإيجنت', false);
                return;
            }
            showToast(data.success || 'تم تبديل الإيجنت للشريحة بنجاح');
        } catch (err) {
            showToast('فشل الاتصال بالسيرفر', false);
        } finally {
            selectEl.disabled = false;
        }
    };

    // Call Detail Slide-Over Drawer
    window.openCallDrawer = async function (callId) {
        const backdrop = document.getElementById('callDrawerBackdrop');
        const body = document.getElementById('callDrawerBody');
        if (!backdrop || !body) return;
        backdrop.classList.add('open');
        body.innerHTML = '<div style="padding:32px;text-align:center;color:var(--muted)">جاري تحميل سجل المكالمة والنص...</div>';

        try {
            const res = await fetch('/api/console/calls/' + encodeURIComponent(callId), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                body.innerHTML = `<div class="alert alert-err">${data.error || 'تعذر تحميل البيانات'}</div>`;
                return;
            }
            const c = data.call || {};
            const transcript = data.transcript || 'لا يوجد تفريغ نصي مسجل لهذه المكالمة.';
            const resolvedSummary = c.report_summary || c.summary || '';
            const audioHtml = c.recording_url
                ? `<div class="card" style="padding:12px"><div class="field-label">تسجيل المكالمة</div><audio controls style="width:100%;margin-top:6px" src="${escapeHtml(c.recording_url)}"></audio></div>`
                : '';

            body.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
                    <div>
                        <div style="font-size:17px;font-weight:700">${escapeHtml(c.name || c.phone || 'متصل غير مسجل')}</div>
                        <div class="mono" style="color:var(--muted);font-size:12.5px">${escapeHtml(c.phone || '')} · SIM ${(Number(c.sim_slot || 0) + 1)} · ${escapeHtml(c.created_at_cairo || c.created_at || '')}</div>
                    </div>
                    <span class="pill pill-crimson">${escapeHtml(c.outcome_label || c.status || '')}</span>
                </div>
                <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:14px">
                    <div class="kpi-card" style="padding:10px"><div class="kpi-label">المدة</div><div style="font-weight:700">${escapeHtml(c.duration_formatted || '')}</div></div>
                    <div class="kpi-card" style="padding:10px"><div class="kpi-label">الإيجنت</div><div style="font-weight:700">${escapeHtml(c.resolved_agent_name || '-')}</div></div>
                    <div class="kpi-card" style="padding:10px"><div class="kpi-label">التقييم / الجنس</div><div style="font-weight:700">${c.ai_rating ? '★ ' + c.ai_rating + '/5' : '-'} · ${escapeHtml(c.caller_gender || '-')}</div></div>
                </div>
                ${resolvedSummary ? `<div class="card" style="padding:12px;border-right:3px solid var(--crimson)"><div class="field-label">الملخص التلقائي للمكالمة</div><div style="font-size:13px;line-height:1.65;margin-top:4px">${escapeHtml(resolvedSummary)}</div></div>` : ''}
                ${audioHtml}
                <div class="card" style="padding:14px">
                    <div class="field-label" style="margin-bottom:8px">النص الكامل للمحادثة (Transcript)</div>
                    <pre style="margin:0;white-space:pre-wrap;font-family:inherit;font-size:13px;line-height:1.7;color:var(--text);max-height:240px;overflow-y:auto">${escapeHtml(transcript)}</pre>
                </div>
                ${c.device_binding_id && c.external_call_id ? `
                <form method="POST" action="/console/action" class="card" style="padding:14px">
                    <input type="hidden" name="_token" value="${escapeHtml(csrfToken())}">
                    <input type="hidden" name="action" value="save_call_report">
                    <input type="hidden" name="device_binding_id" value="${escapeHtml(String(c.device_binding_id))}">
                    <input type="hidden" name="site_id" value="${escapeHtml(String(c.site_id || 0))}">
                    <input type="hidden" name="call_id" value="${escapeHtml(String(c.external_call_id))}">
                    <div class="card-title" style="margin-bottom:10px">تحديث النتيجة والملخص (وتشغيل الأتمتة)</div>
                    <div class="form-grid">
                        <div class="field"><span class="field-label">النتيجة</span>
                            <select name="outcome">
                                <option value="unknown" ${c.report_outcome === 'unknown' ? 'selected' : ''}>غير محدد</option>
                                <option value="answered_completed" ${c.report_outcome === 'answered_completed' ? 'selected' : ''}>تم الرد واكتملت</option>
                                <option value="booked" ${c.report_outcome === 'booked' ? 'selected' : ''}>حجز / موعد</option>
                                <option value="lead_qualified" ${c.report_outcome === 'lead_qualified' ? 'selected' : ''}>عميل مهتم (Qualified)</option>
                                <option value="interested" ${c.report_outcome === 'interested' ? 'selected' : ''}>مهتم بالتواصل</option>
                                <option value="satisfied" ${c.report_outcome === 'satisfied' ? 'selected' : ''}>عميل راضٍ</option>
                                <option value="unsatisfied" ${c.report_outcome === 'unsatisfied' ? 'selected' : ''}>عميل غير راضٍ</option>
                                <option value="follow_up" ${c.report_outcome === 'follow_up' ? 'selected' : ''}>متابعة لاحقة</option>
                                <option value="callback_requested" ${c.report_outcome === 'callback_requested' ? 'selected' : ''}>طلب معاودة اتصال</option>
                                <option value="handoff_needed" ${c.report_outcome === 'handoff_needed' ? 'selected' : ''}>يحتاج تدخل بشري</option>
                                <option value="not_interested" ${c.report_outcome === 'not_interested' ? 'selected' : ''}>غير مهتم</option>
                                <option value="no_answer" ${c.report_outcome === 'no_answer' ? 'selected' : ''}>لم يُرد</option>
                                <option value="busy" ${c.report_outcome === 'busy' ? 'selected' : ''}>مشغول</option>
                                <option value="rejected" ${c.report_outcome === 'rejected' ? 'selected' : ''}>مرفوضة</option>
                                <option value="dnc" ${c.report_outcome === 'dnc' ? 'selected' : ''}>رفض التواصل (DNC)</option>
                                <option value="do_not_call" ${c.report_outcome === 'do_not_call' ? 'selected' : ''}>إدراج DNC (رفض)</option>
                            </select>
                        </div>
                        <div class="field"><span class="field-label">متابعة بشرية</span>
                            <select name="handoff_status" onchange="const f=this.form;if(f&&f.staff_acknowledged){f.staff_acknowledged.checked=(this.value==='completed');}">
                                <option value="not_requested" ${c.handoff_status === 'not_requested' ? 'selected' : ''}>لا يحتاج</option>
                                <option value="requested" ${c.handoff_status === 'requested' ? 'selected' : ''}>مطلوب متابعة</option>
                                <option value="followed_up" ${c.handoff_status === 'followed_up' ? 'selected' : ''}>تمت المتابعة</option>
                                <option value="completed" ${c.handoff_status === 'completed' ? 'selected' : ''}>مكتمل (تأكيد بشري)</option>
                            </select>
                        </div>
                    </div>
                    <label class="check-row" style="margin-bottom:10px"><input type="checkbox" name="staff_acknowledged" value="1" ${c.handoff_status === 'completed' ? 'checked' : ''}> <span>تأكيد استلام وإغلاق المتابعة البشرية من قِبل الموظف</span></label>
                    <div class="field"><span class="field-label">سبب المتابعة البشرية</span><input type="text" name="handoff_reason" value="${escapeHtml(c.handoff_reason || '')}"></div>
                    <div class="field"><span class="field-label">الملخص</span><textarea name="summary">${escapeHtml(resolvedSummary)}</textarea></div>
                    <div class="field"><span class="field-label">ملاحظات العميل</span><input type="text" name="customer_notes" value="${escapeHtml(c.report_notes || '')}"></div>
                    <button type="submit" class="btn btn-primary">حفظ التقرير</button>
                </form>` : ''}
            `;
        } catch (e) {
            body.innerHTML = '<div class="alert alert-err">فشل الاتصال بالسيرفر أثناء جلب التفاصيل.</div>';
        }
    };

    window.closeCallDrawer = function () {
        document.getElementById('callDrawerBackdrop')?.classList.remove('open');
    };

    // Scenario vs Visual Builder mode toggle
    window.toggleScenarioMode = function (mode) {
        const scenarioPane = document.getElementById('scenarioTextPane');
        const builderPane = document.getElementById('visualBuilderPane');
        if (scenarioPane) scenarioPane.style.display = mode === 'scenario' ? 'block' : 'none';
        if (builderPane) builderPane.style.display = mode === 'builder' ? 'block' : 'none';
    };

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[m]));
    }

    // Export toast globally
    window.ioToast = showToast;
})();
