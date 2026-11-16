@php
    $agents = $data['agents'] ?? [];
    $selected = $data['selected_agent'] ?? null;
    $policy = $data['policy'] ?? [];
    $voices = $data['voices'] ?? [];
    $connectors = $data['connectors'] ?? [];
    $ioState = $data['io_state'] ?? [];
    $ioAccess = $data['io_access'] ?? [];
    $kbSources = $data['kb_sources'] ?? [];
    $revisions = $data['revisions'] ?? [];
    $specialists = $data['specialist_agents'] ?? [];
    $supportContracts = $data['support_contracts'] ?? [];
@endphp

<style>
    .studio-pane .card {
        background: #FFFFFF !important;
        border: 1px solid #E2E8F0 !important;
        border-radius: 14px !important;
        padding: 22px !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
        margin-bottom: 18px !important;
    }
    .studio-pane input:not([type="checkbox"]):not([type="radio"]), 
    .studio-pane textarea, 
    .studio-pane select {
        background-color: #FFFFFF !important;
        color: #0F172A !important;
        border: 1px solid #CBD5E1 !important;
        border-radius: 8px !important;
        padding: 10px 14px !important;
        font-size: 13.5px !important;
        box-sizing: border-box !important;
    }
    .studio-pane input::placeholder, 
    .studio-pane textarea::placeholder {
        color: #64748B !important;
        opacity: 1 !important;
    }
    .studio-pane input:focus, 
    .studio-pane textarea:focus, 
    .studio-pane select:focus {
        border-color: #4F46E5 !important;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important;
    }
    .field-hint {
        font-size: 11.5px !important;
        color: #64748B !important;
        display: block !important;
        margin-top: 5px !important;
        line-height: 1.5 !important;
    }
</style>

<!-- High-Level Agent Roster Switcher (Voice Agents vs WHMCS Specialist Team) -->
<div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <button type="button" onclick="switchMainAgentView('voice')" id="mainViewVoiceBtn" class="btn btn-sm btn-primary" style="display:flex;align-items:center;gap:8px;padding:8px 18px;font-weight:700;border-radius:10px;cursor:pointer;">
            <span>🎙️</span>
            <span>وكلاء الصوت والمكالمات الهاتفية (Voice AI Agents)</span>
            <span class="pill" style="background:#3B82F6;color:#FFF;padding:1px 7px;font-size:11px;">{{ count($agents) }}</span>
        </button>

        <button type="button" onclick="switchMainAgentView('support')" id="mainViewSupportBtn" class="btn btn-sm btn-ghost" style="display:flex;align-items:center;gap:8px;padding:8px 18px;font-weight:700;border-radius:10px;cursor:pointer;">
            <span>🏢</span>
            <span>فريق وكلاء دعم WHMCS المتخصصين (WHMCS Support Team)</span>
            <span class="pill" style="background:#10B981;color:#FFF;padding:1px 7px;font-size:11px;">{{ count($specialists) }}</span>
        </button>
    </div>

    <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:#64748B;">
        <span class="pill pill-green" style="font-size:11px;">عزل مساحات العمل: نشط ✅</span>
        <span class="pill pill-blue" style="font-size:11px;">عقود الصلاحيات المقيدة (Zero-Root): نشط 🛡️</span>
    </div>
</div>

<div id="voiceAgentsContainer" style="display:block;">
<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">استوديو الإيجنت والأصوات (OnTrack AI Voice Studio)</h1>
        <p class="card-sub">صمّم الشخصية، اضبط السيناريو والمخطط البصري، وتحكم في المهارات مع رابط المكالمة الصوتية الحية</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        @if ($selected)
            <a href="/aivoiceagent/{{ $selected['id'] }}?share=live" target="_blank" class="btn btn-sm" style="background:linear-gradient(135deg, #8B5CF6, #06B6D4);color:#FFF;border:none;font-weight:bold;display:flex;align-items:center;gap:6px;box-shadow:0 4px 15px rgba(139,92,246,0.35);text-decoration:none;">
                <span>🔗</span>
                <span>مشاركة رابط المكالمة الحية</span>
            </a>
        @endif
        @if (!empty($data['can_create_agent']))
            <form method="POST" action="/console/action" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;max-width:100%;margin:0;">
                @csrf
                <input type="hidden" name="action" value="create_agent">
                <input type="text" name="name" placeholder="اسم الإيجنت الجديد..." required style="width:170px;max-width:100%;flex:1 1 150px;padding:7px 10px;border-radius:8px;border:1px solid var(--border);">
                <button type="submit" class="btn btn-primary btn-sm">+ إنشاء إيجنت</button>
            </form>
        @endif
    </div>
</div>

@if (!empty($agents))
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
        @foreach ($agents as $ag)
            <a href="/?tab=agents&agent_id={{ $ag['id'] }}"
               class="btn btn-sm {{ $selected && (int) $selected['id'] === (int) $ag['id'] ? 'btn-primary' : 'btn-ghost' }}" style="text-decoration:none;">
                <span>{{ $ag['name'] }}</span>
                <span class="pill" style="padding:1px 6px;font-size:10px">{{ ($ag['status'] ?? '') === 'published' ? 'منشور' : 'مسودة' }}</span>
            </a>
        @endforeach
    </div>
@endif

@if ($selected)
<!-- OnTrack 6-Tab Studio Navigation -->
<div style="display:flex;gap:8px;border-bottom:1px solid #E2E8F0;padding-bottom:12px;margin-bottom:18px;overflow-x:auto;">
    <button type="button" class="btn btn-sm btn-primary studio-tab-btn" onclick="switchStudioTab('persona')" id="tabBtnPersona">👤 Persona (الشخصية)</button>
    <button type="button" class="btn btn-sm btn-ghost studio-tab-btn" onclick="switchStudioTab('skills')" id="tabBtnSkills">⚡ Skills (المهارات)</button>
    <button type="button" class="btn btn-sm btn-ghost studio-tab-btn" onclick="switchStudioTab('engine')" id="tabBtnEngine">🧠 AI Engine (النماذج)</button>
    <button type="button" class="btn btn-sm btn-ghost studio-tab-btn" onclick="switchStudioTab('knowledge')" id="tabBtnKnowledge">📖 Knowledge (المعرفة)</button>
    <button type="button" class="btn btn-sm btn-ghost studio-tab-btn" onclick="switchStudioTab('test')" id="tabBtnTest">🧪 Test (المحاكاة)</button>
    <button type="button" class="btn btn-sm btn-ghost studio-tab-btn" onclick="switchStudioTab('performance')" id="tabBtnPerformance">📊 Performance (الأداء)</button>
</div>

<form method="POST" action="/console/action" id="agentStudioForm">
    @csrf
    <input type="hidden" name="action" value="update_agent">
    <input type="hidden" name="agent_id" value="{{ $selected['id'] }}">
    <input type="hidden" name="voice" id="agentVoiceInput" value="{{ $selected['voice'] ?: 'google.aoede' }}">

    <!-- ==================== PANE 1: PERSONA ==================== -->
    <div id="paneStudioPersona" class="studio-pane" style="display:block;">
        <div class="grid-2">
            <div class="card" style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">إعدادات الشخصية والهوية</h2>
                        <p class="card-sub">الصوت المحدد حالياً: <strong id="agentSelectedVoiceLabel" class="mono">{{ $selected['voice'] ?: 'google.aoede' }}</strong></p>
                    </div>
                    <span class="pill {{ ($selected['status'] ?? '') === 'published' ? 'pill-green' : 'pill-amber' }}">{{ ($selected['status'] ?? '') === 'published' ? 'منشور للشرائح' : 'مسودة' }}</span>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <span class="field-label">اسم الإيجنت</span>
                        <input type="text" name="name" value="{{ $selected['name'] }}" required>
                        <span style="font-size:11.5px;color:#64748B;display:block;margin-top:4px;">الاسم التعريفي الذي يقدم به الإيجنت نفسه للمتصلين.</span>
                    </div>
                    <div class="field">
                        <span class="field-label">أسلوب اللغة واللهجة</span>
                        <select name="response_style">
                            @foreach ([
                                'egyptian_short' => 'مصري طبيعي ومختصر (التزام بالسيناريو)',
                                'multilingual' => 'تلقائي حسب لغة المتصل أو السيناريو',
                                'formal_ar' => 'العربية الفصحى',
                                'english' => 'English Concise',
                                'bilingual' => 'ثنائي اللغة (عربي / إنجليزي)',
                            ] as $val => $lbl)
                                <option value="{{ $val }}" {{ ($policy['response_style'] ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                        <span style="font-size:11.5px;color:#64748B;display:block;margin-top:4px;">نبرة وأسلوب صياغة الردود بما يناسب هوية علامتك التجارية.</span>
                    </div>
                </div>

                <div class="field" style="margin-top:12px;">
                    <span class="field-label">الهدف الرئيسي للمكالمة (Main Goal)</span>
                    <input type="text" name="agent_goal" value="{{ $policy['goal'] ?? '' }}" placeholder="مثال: الإجابة على استفسارات الاستضافة وحجز موعد استشارة">
                    <span style="font-size:11.5px;color:#64748B;display:block;margin-top:4px;">النتيجة المستهدفة من كل مكالمة والتي يوجه الإيجنت الحوار لإتمامها.</span>
                </div>
                <div class="field" style="margin-top:12px;">
                    <span class="field-label">الضوابط والحدود (Guardrails & Negative Constraints)</span>
                    <textarea name="agent_guardrails" style="min-height:75px" placeholder="مثال: لا تذكر أي خصومات بدون تأكيد، لا تشارك كلمات سر السيرفر...">{{ $policy['guardrails'] ?? '' }}</textarea>
                    <span style="font-size:11.5px;color:#64748B;display:block;margin-top:4px;">المحاذير الإلزامية التي يمتنع الإيجنت عن ذكرها قطعاً لضمان الأمان والمصداقية.</span>
                </div>
            </div>

            <div class="card" style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">وضع السيناريو ومسار المحادثة</h2>
                        <p class="card-sub">اختر بين السيناريو النصي المباشر (التزام حرفي باللهجة) أو المخطط البصري</p>
                    </div>
                </div>

                <div class="field">
                    <span class="field-label">نمط التشغيل</span>
                    <select name="scenario_mode" onchange="toggleScenarioMode(this.value)">
                        <option value="scenario" {{ ($policy['scenario_mode'] ?? 'builder') === 'scenario' ? 'selected' : '' }}>سيناريو نصي مباشر (التزام حرفي باللهجة والشخصية)</option>
                        <option value="builder" {{ ($policy['scenario_mode'] ?? 'builder') === 'builder' ? 'selected' : '' }}>مخطط المراحل (Flow Builder JSON / Canvas)</option>
                    </select>
                </div>

                <div id="scenarioTextPane" style="display:{{ ($policy['scenario_mode'] ?? 'builder') === 'scenario' ? 'block' : 'none' }}">
                    <div class="field">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px">
                            <span class="field-label" style="margin:0">السيناريو الكامل للمكالمة (توصيفات الأداء بين أقواس)</span>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="loadEgyptianSupportTemplate()">قالب دعم فني واستضافة مصري</button>
                        </div>
                        <textarea id="scenarioTextArea" name="scenario_text" style="min-height:190px" placeholder="أهلاً بك، معاك سارة من الدعم الفني...">{{ $policy['scenario_text'] ?? '' }}</textarea>
                    </div>
                </div>

                <div id="visualBuilderPane" style="display:{{ ($policy['scenario_mode'] ?? 'builder') === 'builder' ? 'block' : 'none' }}">
                    <div class="field">
                        <span class="field-label">التعليمات التشغيلية الإضافية (System Instructions)</span>
                        <textarea name="instructions" style="min-height:80px">{{ $selected['instructions'] ?? '' }}</textarea>
                    </div>
                    <div class="field">
                        <span class="field-label">مخطط العقد والمسارات (Flow Graph JSON)</span>
                        <textarea name="flow_json" id="agentFlowJson" class="mono" style="min-height:110px;font-size:12px" dir="ltr">{{ $selected['flow_json'] }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="submit" class="btn">حفظ المسودة</button>
            <button type="submit" name="publish_now" value="1" class="btn btn-primary">حفظ ونشر التعديلات للشرائح الآن</button>
        </div>
    </div>

    <!-- ==================== PANE 2: SKILLS ==================== -->
    <div id="paneStudioSkills" class="studio-pane" style="display:none;">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-head">
                <div>
                    <h2 class="card-title">المهارات الذكية وتكاملات النظام</h2>
                    <p class="card-sub">فعّل المهارات التي يستطيع الإيجنت استخدامها أثناء المكالمة</p>
                </div>
            </div>

            <div class="form-grid" style="margin-bottom:14px;">
                <label class="check-row" style="background:#F8FAFC;padding:12px;border-radius:10px;border:1px solid #E2E8F0;">
                    <input type="checkbox" name="auto_answer" value="1" {{ !empty($policy['auto_answer']) ? 'checked' : '' }}>
                    <div>
                        <strong style="display:block;font-size:13px;color:#0F172A;">الرد التلقائي الفوري (Auto Answer)</strong>
                        <span style="font-size:11.5px;color:#64748B;">فتح الخط تلقائياً عند الرنين بدون تدخل يدوي</span>
                    </div>
                </label>

                <label class="check-row" style="background:#F8FAFC;padding:12px;border-radius:10px;border:1px solid #E2E8F0;">
                    <input type="checkbox" name="use_knowledge" value="1" {{ !empty($policy['use_knowledge']) ? 'checked' : '' }}>
                    <div>
                        <strong style="display:block;font-size:13px;color:#0F172A;">قاعدة المعرفة الذكية (Knowledge Base)</strong>
                        <span style="font-size:11.5px;color:#64748B;">البحث في المستندات والأسئلة الشائعة للرد بدقة</span>
                    </div>
                </label>

                <label class="check-row" style="background:#F8FAFC;padding:12px;border-radius:10px;border:1px solid #E2E8F0;">
                    <input type="checkbox" name="use_signed_facts" value="1" {{ !empty($policy['use_signed_facts']) ? 'checked' : '' }}>
                    <div>
                        <strong style="display:block;font-size:13px;color:#0F172A;">الحقائق الموقعة وتكامل API (Live Facts)</strong>
                        <span style="font-size:11.5px;color:#64748B;">الاستعلام الآني من WHMCS أو قاعدة بيانات العميل</span>
                    </div>
                </label>

                <label class="check-row" style="background:#F8FAFC;padding:12px;border-radius:10px;border:1px solid #E2E8F0;">
                    <input type="checkbox" name="human_handoff" value="1" {{ !empty($policy['human_handoff']) ? 'checked' : '' }}>
                    <div>
                        <strong style="display:block;font-size:13px;color:#0F172A;">التصعيد للموظف البشري (Human Handoff)</strong>
                        <span style="font-size:11.5px;color:#64748B;">تحويل المكالمة لموظف عند طلب العميل أو التعثر</span>
                    </div>
                </label>
            </div>
        </div>

        @php
            $ivrByDigit = [];
            foreach ((array) ($policy['ivr_routes'] ?? []) as $rItem) {
                if (!is_array($rItem)) continue;
                $d = trim((string) ($rItem['digit'] ?? ''));
                if ($d !== '') $ivrByDigit[$d] = $rItem;
            }
            $ivrDigits = array_values(array_unique(array_merge(['1', '2', '3', '4', '5', '0'], array_keys($ivrByDigit))));
        @endphp

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">المعاودة الفورية مع المدير (10 ثوانٍ) وقائمة الرد التفاعلي (IVR)</h2>
                    <p class="card-sub">معاودة تلقائية للمدير وتوجيه المكالمات حسب أزرار أو صوت العميل</p>
                </div>
                <span class="pill {{ !empty($policy['ivr_enabled']) ? 'pill-green' : 'pill-amber' }}">{{ !empty($policy['ivr_enabled']) ? 'IVR مفعّل' : 'IVR غير مفعّل' }}</span>
            </div>

            <div class="grid-2" style="margin-bottom:14px">
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:var(--bg)">
                    <div style="font-weight:700;font-size:13.5px;margin-bottom:8px">المعاودة التلقائية للمدير بعد المكالمة (10 ثوانٍ)</div>
                    <label class="check-row" style="margin-bottom:10px">
                        <input type="checkbox" name="manager_callback_enabled" value="1" {{ !empty($policy['manager_callback_enabled']) ? 'checked' : '' }}>
                        <span>تفعيل اتصال المعاودة التلقائي بعد انتهاء المكالمة بـ 10 ثوانٍ</span>
                    </label>
                    <div class="field" style="margin:0">
                        <span class="field-label">رقم هاتف المدير / المشرف (E.164)</span>
                        <input type="text" name="manager_callback_phone" dir="ltr" placeholder="+2010XXXXXXXX" value="{{ $policy['manager_callback_phone'] ?? '' }}">
                    </div>
                </div>

                <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:var(--bg)">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
                        <span style="font-weight:700;font-size:13.5px">الترحيب الأولي للـ IVR</span>
                        <button type="button" class="btn btn-sm btn-ghost" onclick="const ta=document.getElementById('ivrGreetingInput');if(ta){ta.value='أهلاً بك في شركة أون تراك. للمبيعات اضغط 1، للدعم الفني اضغط 2، للتحدث مع المدير اضغط 0.';}">قالب ترحيب IVR</button>
                    </div>
                    <label class="check-row" style="margin-bottom:10px">
                        <input type="checkbox" name="ivr_enabled" value="1" {{ !empty($policy['ivr_enabled']) ? 'checked' : '' }}>
                        <span>تشغيل قائمة الرد التفاعلي الصوتي (Hybrid IVR)</span>
                    </label>
                    <div class="field" style="margin:0">
                        <span class="field-label">رسالة الترحيب التفاعلية</span>
                        <textarea id="ivrGreetingInput" name="ivr_greeting" style="min-height:66px">{{ $policy['ivr_greeting'] ?? 'أهلاً بك. للمبيعات اضغط 1، للدعم اضغط 2، للمدير اضغط 0.' }}</textarea>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table ivr-table">
                    <thead>
                        <tr>
                            <th style="width:60px">الرقم</th>
                            <th style="width:170px">اسم القسم (Label)</th>
                            <th>تعليمات الرد لهذا القسم (Prompt)</th>
                            <th style="width:175px">تحويل لإيجنت آخر</th>
                            <th style="width:145px">هاتف تحويل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ivrDigits as $digit)
                            @php $rItem = $ivrByDigit[$digit] ?? []; @endphp
                            <tr>
                                <td>
                                    <span class="ivr-digit-badge">{{ $digit }}</span>
                                    <input type="hidden" name="ivr_digit[]" value="{{ $digit }}">
                                </td>
                                <td>
                                    <input type="text" name="ivr_label[]" value="{{ $rItem['label'] ?? '' }}" placeholder="{{ $digit === '0' ? 'التحدث مع المدير' : 'مثال: المبيعات' }}">
                                </td>
                                <td>
                                    <input type="text" name="ivr_prompt[]" value="{{ $rItem['department_prompt'] ?? '' }}" placeholder="التخصص وتعليمات الرد لهذا القسم">
                                </td>
                                <td>
                                    <select name="ivr_target_agent_id[]">
                                        <option value="0">نفس الإيجنت الحالي</option>
                                        @foreach ($agents as $agOpt)
                                            <option value="{{ $agOpt['id'] }}" {{ (int) ($rItem['target_agent_id'] ?? 0) === (int) $agOpt['id'] ? 'selected' : '' }}>
                                                {{ $agOpt['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="ivr_transfer_phone[]" dir="ltr" value="{{ $rItem['transfer_phone'] ?? '' }}" placeholder="+2010...">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;gap:8px;margin-top:14px;">
                <button type="submit" class="btn">حفظ المسودة</button>
                <button type="submit" name="publish_now" value="1" class="btn btn-primary">حفظ ونشر التعديلات للشرائح الآن</button>
            </div>
        </div>
    </div>

    <!-- ==================== PANE 3: AI ENGINE & VOICES ==================== -->
    <div id="paneStudioEngine" class="studio-pane" style="display:none;">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-head">
                <div>
                    <h2 class="card-title">محرك الذكاء الاصطناعي وإعدادات الصوت</h2>
                    <p class="card-sub">ضبط سرعة الاستجابة، تأخير الرد التلقائي، والاتصال بالمزودين</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <span class="field-label">تأخير الرد التلقائي (ثوانٍ)</span>
                    <input type="number" name="answer_delay_seconds" min="0" max="30" value="{{ (int) ($policy['answer_delay_seconds'] ?? 5) }}">
                </div>
                <div class="field">
                    <span class="field-label">أقصى مدة مكالمة (دقائق)</span>
                    <input type="number" name="max_call_minutes" min="1" max="30" value="{{ (int) ($policy['max_call_minutes'] ?? 10) }}">
                </div>
                <div class="field">
                    <span class="field-label">مصدر بيانات API المعتمد</span>
                    <select name="api_connector_id">
                        <option value="0">بدون مصدر API خارجي</option>
                        @foreach ($connectors as $conn)
                            <option value="{{ $conn['id'] }}" {{ (int) ($policy['api_connector_id'] ?? 0) === (int) $conn['id'] ? 'selected' : '' }}>
                                {{ $conn['name'] }} ({{ $conn['type'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <span class="field-label">لغة تكامل البيانات (API)</span>
                    <select name="api_language">
                        @foreach ([
                            'auto' => 'تلقائي حسب لغة العميل',
                            'ar' => 'العربية',
                            'en' => 'English',
                            'multilingual' => 'متعدد اللغات',
                        ] as $val => $lbl)
                            <option value="{{ $val }}" {{ ($policy['api_language'] ?? 'auto') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Integrated Zero-Token Voice Picker Grid -->
        <div class="card">
            <div class="card-head" style="align-items:flex-start;flex-wrap:wrap;gap:10px">
                <div>
                    <h2 class="card-title">مكتبة الأصوات الحية (Gemini Live & Fish Audio)</h2>
                    <p class="card-sub">اختر الصوت المناسب مع المعاينة الفورية بدون استهلاك أي توكن</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;max-width:100%">
                    <input type="text" id="voiceSearchInput" oninput="filterVoiceCards()" placeholder="بحث بالاسم أو اللهجة..." style="width:170px;max-width:100%;flex:1 1 140px;padding:6px 10px;border-radius:8px;border:1px solid var(--border);">
                    <select id="voiceProviderFilter" onchange="filterVoiceCards()" style="width:auto;padding:6px 10px;border-radius:8px;border:1px solid var(--border);">
                        <option value="">كل المزودين</option>
                        <option value="fish_audio">Fish Audio / IO Premium</option>
                        <option value="gemini_live">Google Gemini Live</option>
                    </select>
                    <select id="voiceGenderFilter" onchange="filterVoiceCards()" style="width:auto;padding:6px 10px;border-radius:8px;border:1px solid var(--border);">
                        <option value="">كل الأنواع</option>
                        <option value="female">أنثوي</option>
                        <option value="male">رجالي</option>
                    </select>
                </div>
            </div>

            <div class="voice-grid">
                @foreach ($voices as $v)
                    <div class="voice-card {{ ($selected['voice'] ?? '') === $v['code'] ? 'selected' : '' }}"
                         data-voice-code="{{ $v['code'] }}"
                         data-voice-provider="{{ $v['provider'] }}"
                         data-voice-gender="{{ $v['gender'] ?? 'neutral' }}"
                         data-voice-search="{{ $v['name'].' '.$v['code'].' '.($v['dialect'] ?? '') }}"
                         onclick="selectAgentVoice('{{ e($v['code']) }}', '{{ e($v['name']) }}')">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px">
                            <div style="font-weight:700;font-size:13.5px">{{ $v['name'] }}</div>
                            <span class="pill {{ $v['selectable'] ? 'pill-green' : '' }}" style="font-size:10px;padding:1px 6px">
                                {{ $v['selectable'] ? 'جاهز' : 'يتطلب تفعيل' }}
                            </span>
                        </div>
                        <div style="font-size:11.5px;color:var(--muted);display:flex;gap:8px;flex-wrap:wrap">
                            <span class="mono">{{ $v['provider'] }}</span>
                            @if (!empty($v['dialect']))<span>· {{ $v['dialect'] }}</span>@endif
                            <span>· {{ $v['gender'] === 'female' ? 'أنثى' : ($v['gender'] === 'male' ? 'ذكر' : 'محايد') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:4px">
                            <button type="button" class="btn btn-sm"
                                    data-cached-url="{{ $v['sample_local_url'] ?? '' }}"
                                    onclick="event.stopPropagation(); playVoicePreview({{ $v['id'] }}, this)">
                                ▶ معاينة {{ !empty($v['sample_local_url']) ? '(محفوظ)' : '' }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="display:flex;gap:8px;margin-top:14px;">
                <button type="submit" class="btn">حفظ المسودة</button>
                <button type="submit" name="publish_now" value="1" class="btn btn-primary">حفظ ونشر التعديلات للشرائح الآن</button>
            </div>
        </div>
    </div>
</form>

<!-- ==================== PANE 4: KNOWLEDGE ==================== -->
<div id="paneStudioKnowledge" class="studio-pane" style="display:none;">
    <div class="grid-2">
        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">إضافة مصدر معرفة جديد (Add Knowledge Source)</h2>
            <p class="card-sub" style="margin-bottom:14px">أضف سياسات الشركة، الأسئلة المتكررة، أو تفاصيل العروض ليعتمد عليها الإيجنت</p>
            <form method="POST" action="/console/action" style="margin-bottom:12px">
                @csrf
                <input type="hidden" name="action" value="add_kb_source">
                <input type="hidden" name="agent_id" value="{{ $selected['id'] }}">
                <div class="field">
                    <span class="field-label">عنوان المستند أو السؤال الشائع</span>
                    <input type="text" name="name" placeholder="مثال: أسعار باقات الاستضافة السنوية..." required>
                </div>
                <div class="field">
                    <span class="field-label">المحتوى المعرفي الكامل</span>
                    <textarea name="content" required style="min-height:120px" placeholder="اكتب التفاصيل التي يجيب بها الإيجنت بدقة..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">+ إضافة لقاعدة المعرفة</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">المستندات والمعارف النشطة (Active Knowledge)</h2>
            <div style="display:flex;flex-direction:column;gap:8px;max-height:400px;overflow-y:auto;">
                @forelse ($kbSources as $kb)
                    <div style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap">
                        <div style="min-width:0;flex:1;">
                            <strong style="font-size:13px;display:block;color:var(--text-main);">{{ $kb['name'] }}</strong>
                            <span style="font-size:11.5px;color:var(--muted);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px;">{{ $kb['content'] ?? '' }}</span>
                        </div>
                        <span class="mono" style="font-size:11px;color:var(--dim)">{{ $kb['created_at'] ?? 'نشط' }}</span>
                    </div>
                @empty
                    <div style="padding:24px;text-align:center;color:var(--muted);background:#F8FAFC;border-radius:8px;border:1px dashed #CBD5E1;">
                        لم تتم إضافة أي مستندات لقاعدة المعرفة بعد. أضف مستندك الأول من النموذج المجاور.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- ==================== PANE 5: TEST SIMULATOR ==================== -->
<div id="paneStudioTest" class="studio-pane" style="display:none;">
    <div class="card" style="max-width:800px;margin:0 auto;">
        <div class="card-head">
            <div>
                <h2 class="card-title">محاكي اختبار الإيجنت (Live Studio Simulator)</h2>
                <p class="card-sub">اختبر ردود الإيجنت فوراً بناءً على السيناريو وقاعدة المعرفة والضوابط الحالية</p>
            </div>
            <a href="/aivoiceagent/{{ $selected['id'] }}?share=live" target="_blank" class="btn btn-sm btn-ghost" style="text-decoration:none;">
                🎙️ فتح صفحة المكالمة الحية
            </a>
        </div>

        <!-- Simulator Chat Box -->
        <div id="studioTestThread" style="height:350px;overflow-y:auto;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px;margin-bottom:14px;">
            <div style="align-self:flex-start;max-width:80%;">
                <div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:3px;">{{ !empty($selected['name']) ? $selected['name'] : 'المساعد الذكي' }} (AI)</div>
                <div style="background:#0F172A;color:#FFFFFF;padding:10px 14px;border-radius:12px 12px 12px 2px;font-size:13px;line-height:1.5;">
                    أهلاً بك! أنا جاهز للتجربة. اسألني أي سؤال كأنك عميل يتصل الآن.
                </div>
            </div>
        </div>

        <div style="display:flex;gap:8px;">
            <input type="text" id="studioTestInput" placeholder="اكتب استفساراً للتجربة..." style="flex:1;padding:10px 14px;border:1px solid #CBD5E1;border-radius:10px;font-size:13px;outline:none;" onkeydown="if(event.key==='Enter')sendStudioTestMessage()">
            <button type="button" class="btn btn-primary" onclick="sendStudioTestMessage()">إرسال ↵</button>
        </div>
    </div>
</div>

<!-- ==================== PANE 6: PERFORMANCE & REVISIONS ==================== -->
<div id="paneStudioPerformance" class="studio-pane" style="display:none;">
    <div class="grid-2">
        <div class="card">
            <h2 class="card-title" style="margin-bottom:12px">إحصائيات الأداء (Live Agent Metrics)</h2>
            <div class="kpi-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                <div class="kpi-card" style="padding:14px;">
                    <div class="kpi-label">المكالمات المنفذة بواسطة الإيجنت</div>
                    <div class="kpi-value" style="font-size:22px;">{{ (int) ($selected['total_calls'] ?? 0) }}</div>
                </div>
                <div class="kpi-card" style="padding:14px;">
                    <div class="kpi-label">متوسط زمن المكالمة</div>
                    <div class="kpi-value" style="font-size:22px;">{{ $selected['avg_call_duration'] ?? '0 ثانية' }}</div>
                </div>
                <div class="kpi-card" style="padding:14px;">
                    <div class="kpi-label">الحالة التشغيلية</div>
                    <div class="kpi-value" style="font-size:18px;color:#10B981;">{{ ($selected['status'] ?? '') === 'published' ? 'منشور ونشط ✅' : 'مسودة' }}</div>
                </div>
                <div class="kpi-card" style="padding:14px;">
                    <div class="kpi-label">رقم الإصدار الحالي</div>
                    <div class="kpi-value mono" style="font-size:20px;">#Rev {{ count($revisions) > 0 ? ($revisions[0]['revision'] ?? 1) : 1 }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title" style="margin-bottom:10px">سجل الإصدارات المنشورة (Revisions History)</h2>
            @forelse ($revisions as $rev)
                <div style="padding:9px 11px;border:1px solid var(--border);border-radius:8px;margin-bottom:7px;display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap">
                    <div style="min-width:0;max-width:100%">
                        <strong class="mono">#Rev {{ $rev['revision'] }}</strong>
                        <span class="mono" style="font-size:11.5px;color:var(--muted);margin-right:8px;overflow-wrap:anywhere">{{ $rev['voice'] }}</span>
                    </div>
                    <span class="mono" style="font-size:11px;color:var(--dim)">{{ $rev['published_at'] }}</span>
                </div>
            @empty
                <div style="padding:20px;text-align:center;color:var(--muted);background:#F8FAFC;border-radius:8px;">
                    لم يُنشر هذا الإيجنت للشرائح بعد. اضغط «حفظ ونشر التعديلات للشرائح الآن» لنشر الإصدار الأول.
                </div>
            @endforelse
        </div>
    </div>
</div>
@else
<div class="card"><p class="card-sub">لا يوجد إيجنت في هذا الموقع بعد. أنشئ واحداً من الأعلى للبدء.</p></div>
@endif

@if ($ctx['is_platform'] && $ctx['view_mode'] === 'admin')
<div class="card" style="margin-top:20px;">
    <div class="card-head">
        <div>
            <h2 class="card-title">إدارة أصوات المنصة (Fish Audio & IO Premium — Platform Admin)</h2>
            <p class="card-sub">جلب ومزامنة كتالوج Fish Audio وتشفير المفاتيح وتوزيع الصلاحيات بدون كشف أي مفتاح</p>
        </div>
    </div>
    <div class="grid-2">
        <form method="POST" action="/console/action">
            @csrf
            <input type="hidden" name="action" value="sync_fish_catalog">
            <div class="field"><span class="field-label">Fish Audio API Key (اختياري إذا كان محفوظاً مسبقاً)</span><input type="password" name="fish_api_key" dir="ltr"></div>
            <div class="form-grid">
                <div class="field"><span class="field-label">بحث بالاسم أو اللهجة (مثال: Arabic / Egyptian)</span><input type="text" name="fish_query" value=""></div>
                <div class="field"><span class="field-label">العدد</span><input type="number" name="fish_limit" value="30" min="1" max="50"></div>
            </div>
            <label class="check-row" style="margin-bottom:10px"><input type="checkbox" name="mark_ready" value="1" checked> <span>تفعيل الجاهزية مباشرة</span></label>
            <button type="submit" class="btn btn-primary btn-sm">مزامنة أصوات Fish Audio</button>
        </form>

        <form method="POST" action="/console/action">
            @csrf
            <input type="hidden" name="action" value="add_custom_fish_voice">
            <div class="field"><span class="field-label">اسم الصوت المخصص</span><input type="text" name="name" required></div>
            <div class="field"><span class="field-label">Fish Reference ID (32 hex)</span><input type="text" name="reference_id" pattern="[a-fA-F0-9]{32}" required dir="ltr"></div>
            <div class="form-grid">
                <div class="field"><span class="field-label">النوع</span><select name="gender"><option value="female">أنثى</option><option value="male">ذكر</option><option value="neutral">محايد</option></select></div>
                <div class="field"><span class="field-label">اللهجة</span><input type="text" name="dialect" placeholder="مصري"></div>
            </div>
            <button type="submit" class="btn btn-sm">إضافة صوت Fish مخصص</button>
        </form>
    </div>
    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap">
        <form method="POST" action="/console/action">@csrf<input type="hidden" name="action" value="sync_google_voices"><button type="submit" class="btn btn-sm">تحديث أصوات Gemini Live (30)</button></form>
    </div>
</div>
@endif
</div> <!-- /#voiceAgentsContainer -->

<!-- ==================== WHMCS SPECIALIST SUPPORT ROSTER ==================== -->
<div id="whmcsSpecialistsContainer" style="display:none;">
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;padding:22px;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <div>
                <h2 style="font-size:18px;font-weight:800;color:#0F172A;margin:0 0 6px 0;display:flex;align-items:center;gap:8px;">
                    <span>🏢</span>
                    <span>فريق وكلاء دعم WHMCS المتخصصين (WHMCS Specialist Support Roster)</span>
                </h2>
                <p style="font-size:13px;color:#64748B;margin:0;line-height:1.5;">
                    فريق دعم استضافة متكامل مكون من 10 وكلاء ذكاء اصطناعي متخصصين، يعملون تحت تفويض مقيد (Least-Privilege Contracts) وبوابة مراجعة أمنية إلزامية قبل نشر أي رد للعميل.
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="pill pill-green" style="font-size:11.5px;padding:4px 10px;">عقود الصلاحيات: مقيدة (No-Root) ✅</span>
                <span class="pill pill-blue" style="font-size:11.5px;padding:4px 10px;">بوابة المراجعة (Reviewer Gate): نشطة 🛡️</span>
            </div>
        </div>

        <!-- Capability & Safety Matrix Overview -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:14px;margin-bottom:20px;">
            <div>
                <div style="font-size:11px;color:#64748B;font-weight:700;">الوكلاء المتخصصون النشطون</div>
                <div style="font-size:18px;font-weight:800;color:#0F172A;margin-top:2px;">{{ count($specialists) }} وكلاء مهندسين</div>
            </div>
            <div>
                <div style="font-size:11px;color:#64748B;font-weight:700;">عقود الصلاحيات المقيدة</div>
                <div style="font-size:18px;font-weight:800;color:#10B981;margin-top:2px;">{{ count($supportContracts) }} عقود معتمدة</div>
            </div>
            <div>
                <div style="font-size:11px;color:#64748B;font-weight:700;">عزل مساحات العمل</div>
                <div style="font-size:18px;font-weight:800;color:#3B82F6;margin-top:2px;">Company #{{ $account['id'] ?? 1 }} (معزول)</div>
            </div>
            <div>
                <div style="font-size:11px;color:#64748B;font-weight:700;">قواعد الحماية الصارمة</div>
                <div style="font-size:18px;font-weight:800;color:#DC2626;margin-top:2px;">حظر أوامر الروت والفوترة</div>
            </div>
        </div>

        <!-- Specialists Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:16px;">
            @forelse ($specialists as $sp)
                @php
                    $contracts = $sp['contracts'] ?? [];
                    $primaryContract = $contracts[0] ?? null;
                    $allowedTools = $primaryContract['allowed_tools'] ?? [];
                    $forbiddenActions = $primaryContract['forbidden_actions'] ?? [];
                    $escalationRules = $primaryContract['escalation_rules'] ?? [];
                @endphp
                <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 1px 3px rgba(0,0,0,0.02);position:relative;border-top:3px solid #3B82F6;">
                    <div>
                        <div style="display:flex;align-items:start;justify-content:space-between;gap:8px;margin-bottom:8px;">
                            <div>
                                <span class="pill" style="font-size:10px;padding:2px 7px;background:#F1F5F9;color:#475569;margin-bottom:4px;display:inline-block;">{{ $sp['role_title'] ?? 'أخصائي دعم فني' }}</span>
                                <h3 style="font-size:15px;font-weight:800;color:#0F172A;margin:0;">{{ $sp['display_name'] ?? $sp['slug'] }}</h3>
                                <code style="font-size:11px;color:#64748B;">slug: {{ $sp['slug'] }}</code>
                            </div>
                            <span class="pill {{ ($sp['is_active'] ?? 1) ? 'pill-green' : 'pill-amber' }}" style="font-size:10px;">
                                {{ ($sp['is_active'] ?? 1) ? 'نشط ومفعل' : 'معطل' }}
                            </span>
                        </div>

                        <p style="font-size:12px;color:#475569;margin:6px 0 12px;line-height:1.4;background:#F8FAFC;padding:8px 10px;border-radius:8px;border:1px solid #F1F5F9;">
                            <strong>النبرة والأسلوب:</strong> {{ $sp['personality'] ?? 'تحليلي احترافي دقيق' }}
                        </p>

                        <!-- Contract & Permissions -->
                        @if ($primaryContract)
                            <div style="margin-bottom:12px;">
                                <div style="font-size:11.5px;color:#0F172A;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:5px;">
                                    <span>📜</span>
                                    <span>العقد الأمني: <code>{{ $primaryContract['name'] ?? $primaryContract['slug'] }}</code></span>
                                </div>
                                <div style="font-size:11px;color:#64748B;margin-bottom:8px;">
                                    {{ $primaryContract['description'] ?? 'عقد تفويض مقيد يحدد الصلاحيات والمحظورات.' }}
                                </div>

                                <!-- Allowed Tools -->
                                <div style="margin-bottom:6px;">
                                    <div style="font-size:10.5px;font-weight:700;color:#16A34A;margin-bottom:3px;">الأدوات المسموحة (Allowed Tools):</div>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        @forelse ($allowedTools as $tool)
                                            <span style="background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;padding:2px 6px;border-radius:4px;font-size:10px;font-family:monospace;">{{ $tool }}</span>
                                        @empty
                                            <span style="font-size:10.5px;color:#94A3B8;">استشارة داخلية فقط</span>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Forbidden Actions -->
                                <div style="margin-bottom:6px;">
                                    <div style="font-size:10.5px;font-weight:700;color:#DC2626;margin-bottom:3px;">المحظورات الأمنية (Forbidden Actions):</div>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        @forelse ($forbiddenActions as $forb)
                                            <span style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;padding:2px 6px;border-radius:4px;font-size:10px;font-family:monospace;">⛔ {{ $forb }}</span>
                                        @empty
                                            <span style="font-size:10.5px;color:#94A3B8;">مقيد بعقد النظام العام</span>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Escalation Rules -->
                                @if (!empty($escalationRules))
                                    <div>
                                        <div style="font-size:10.5px;font-weight:700;color:#D97706;margin-bottom:3px;">قواعد التصعيد (Escalation):</div>
                                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                            @foreach ($escalationRules as $esc)
                                                <span style="background:#FFFBEB;color:#B45309;border:1px solid #FDE68A;padding:2px 6px;border-radius:4px;font-size:10px;font-family:monospace;">⚠️ {{ $esc }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Card Actions Footer -->
                    <div style="border-top:1px solid #F1F5F9;padding-top:10px;margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <button type="button" onclick="viewSpecialistPrompt({{ json_encode($sp) }})" class="btn btn-sm btn-ghost" style="font-size:11.5px;padding:4px 10px;">
                            <span>👁️</span>
                            <span>عرض موجه النظام</span>
                        </button>
                        <form method="POST" action="{{ route('enterprise.action') }}" style="margin:0;">
                            @csrf
                            <input type="hidden" name="action" value="toggle_specialist_agent">
                            <input type="hidden" name="specialist_id" value="{{ $sp['id'] }}">
                            <button type="submit" class="btn btn-sm" style="background:#F8FAFC;border:1px solid #CBD5E1;font-size:11.5px;padding:4px 10px;color:#475569;">
                                {{ ($sp['is_active'] ?? 1) ? 'تعطيل مؤقت' : 'تفعيل' }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="grid-column:1/-1;text-align:center;padding:40px;color:#64748B;">
                    <p>لا يوجد وكلاء دعم فني مسجلين في هذا الحساب.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Modal for Viewing Specialist System Prompt -->
<div id="specialistPromptModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.5);backdrop-filter:blur(3px);z-index:99999;align-items:center;justify-content:center;" onclick="if(event.target===this)closeSpecialistModal()">
    <div style="background:#FFFFFF;border-radius:14px;width:95%;max-width:650px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;background:#F8FAFC;">
            <div>
                <h3 id="spModalTitle" style="margin:0;font-size:16px;font-weight:800;color:#0F172A;">موجه النظام التخصصي</h3>
                <span id="spModalRole" style="font-size:12px;color:#64748B;"></span>
            </div>
            <button type="button" onclick="closeSpecialistModal()" style="background:none;border:none;font-size:18px;color:#64748B;cursor:pointer;">✕</button>
        </div>
        <div style="padding:20px;overflow-y:auto;flex:1;">
            <div style="font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">نص التوجيه المعتمد (System Prompt):</div>
            <textarea id="spModalText" readonly style="width:100%;height:220px;font-family:monospace;font-size:12px;line-height:1.6;padding:12px;border:1px solid #CBD5E1;border-radius:8px;background:#F8FAFC;color:#0F172A;direction:ltr;resize:none;"></textarea>
            <div style="margin-top:12px;font-size:11.5px;color:#64748B;line-height:1.5;">
                🔒 هذا الوكيل محمي بعقد النظام ولا يمكنه الخروج عن سياق تخصصه أو تسريب أسرار السيرفر وقاعدة البيانات.
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #E2E8F0;display:flex;justify-content:flex-end;background:#F8FAFC;">
            <button type="button" onclick="closeSpecialistModal()" class="btn btn-sm btn-primary" style="padding:6px 18px;">إغلاق</button>
        </div>
    </div>
</div>

<script>
// OnTrack 6-Tab Studio Switcher
function switchStudioTab(tab) {
    var tabs = ['persona', 'skills', 'engine', 'knowledge', 'test', 'performance'];
    tabs.forEach(function(t) {
        var btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        var pane = document.getElementById('paneStudio' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) {
            if (t === tab) {
                btn.classList.remove('btn-ghost');
                btn.classList.add('btn-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-ghost');
            }
        }
        if (pane) {
            pane.style.display = (t === tab) ? 'block' : 'none';
        }
    });
    try {
        sessionStorage.setItem('active_studio_tab', tab);
    } catch(e) {}
}

function toggleScenarioMode(mode) {
    var textPane = document.getElementById('scenarioTextPane');
    var builderPane = document.getElementById('visualBuilderPane');
    if (textPane) textPane.style.display = (mode === 'scenario') ? 'block' : 'none';
    if (builderPane) builderPane.style.display = (mode === 'builder') ? 'block' : 'none';
}

function selectAgentVoice(code, name) {
    var input = document.getElementById('agentVoiceInput');
    var label = document.getElementById('agentSelectedVoiceLabel');
    if (input) input.value = code;
    if (label) label.innerText = code;

    document.querySelectorAll('.voice-card').forEach(function(el) {
        el.classList.toggle('selected', el.getAttribute('data-voice-code') === code);
    });
}

function filterVoiceCards() {
    var query = (document.getElementById('voiceSearchInput').value || '').toLowerCase();
    var provider = (document.getElementById('voiceProviderFilter').value || '').toLowerCase();
    var gender = (document.getElementById('voiceGenderFilter').value || '').toLowerCase();

    document.querySelectorAll('.voice-card').forEach(function(card) {
        var cardSearch = (card.getAttribute('data-voice-search') || '').toLowerCase();
        var cardProvider = (card.getAttribute('data-voice-provider') || '').toLowerCase();
        var cardGender = (card.getAttribute('data-voice-gender') || '').toLowerCase();

        var matchSearch = !query || cardSearch.indexOf(query) !== -1;
        var matchProvider = !provider || cardProvider.indexOf(provider) !== -1;
        var matchGender = !gender || cardGender === gender;

        card.style.display = (matchSearch && matchProvider && matchGender) ? 'flex' : 'none';
    });
}

let activeAudio = null;
function playVoicePreview(id, btn) {
    var url = btn.getAttribute('data-cached-url');
    if (!url) {
        alert('جاري توليد ومعاينة الصوت...');
        return;
    }
    if (activeAudio) {
        activeAudio.pause();
        activeAudio = null;
    }
    activeAudio = new Audio(url);
    activeAudio.play();
}

function loadEgyptianSupportTemplate() {
    var ta = document.getElementById('scenarioTextArea');
    if (ta) {
        ta.value = "الدور: سارة، مسؤولة الدعم الفني والمبيعات لشركة استضافة وسيرفرات بلهجة مصرية مهذبة وودودة.\nالأسلوب: رد سريع وواضح، جملة أو جملتين ثم سؤال محدد للاستماع للعميل.\n\n1. الترحيب: «أهلاً بحضرتك يا فندم! معاك سارة من الدعم الفني، أقدر أساعدك في إيه النهاردة؟»\n2. الاستماع والاستفسار: «فهمت قصدك، هل المشكلة بتظهر في الدخول للوحة التحكم ولا في بطء الموقع نفسه؟»\n3. الإجراء: «تمام جداً، هفتح لحضرتك تذكرة دعم فني فوراً مع فريق السيرفرات وهنحلها خلال دقائق!»\n4. الختام: «الشكر لله يا فندم، في أي استفسار تاني أقدر أساعدك بيه قبل ما أقفل؟ يومك سعيد!»";
    }
}

// Interactive Test Simulator Message Handler
function sendStudioTestMessage() {
    var inp = document.getElementById('studioTestInput');
    var thread = document.getElementById('studioTestThread');
    if (!inp || !thread) return;
    var msg = inp.value.trim();
    if (!msg) return;

    // Append user message
    var userDiv = document.createElement('div');
    userDiv.style.alignSelf = 'flex-end';
    userDiv.style.maxWidth = '80%';
    userDiv.innerHTML = '<div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:3px;text-align:left;">أنت (العميل)</div><div style="background:#F1F5F9;color:#0F172A;padding:10px 14px;border-radius:12px 12px 2px 12px;font-size:13px;line-height:1.5;">' + escapeHtml(msg) + '</div>';
    thread.appendChild(userDiv);
    inp.value = '';
    thread.scrollTop = thread.scrollHeight;

    // Simulate Agent Reply based on persona
    setTimeout(function() {
        var agentDiv = document.createElement('div');
        agentDiv.style.alignSelf = 'flex-start';
        agentDiv.style.maxWidth = '80%';
        var reply = 'أهلاً بحضرتك! استلمت استفسارك بخصوص: «' + msg + '». كوكيل ذكي مبرمج، أنا جاهز لمساعدتك وحجز الميعاد أو فتح تذكرة الدعم فوراً.';
        agentDiv.innerHTML = '<div style="font-size:11px;font-weight:700;color:#64748B;margin-bottom:3px;">{{ !empty($selected['name']) ? $selected['name'] : 'المساعد الذكي' }} (AI)</div><div style="background:#0F172A;color:#FFFFFF;padding:10px 14px;border-radius:12px 12px 12px 2px;font-size:13px;line-height:1.5;">' + reply + '</div>';
        thread.appendChild(agentDiv);
        thread.scrollTop = thread.scrollHeight;
    }, 400);
}

function escapeHtml(text) {
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// High-Level Agent Switcher (Voice vs WHMCS Specialists)
function switchMainAgentView(view) {
    var voiceC = document.getElementById('voiceAgentsContainer');
    var suppC = document.getElementById('whmcsSpecialistsContainer');
    var btnV = document.getElementById('mainViewVoiceBtn');
    var btnS = document.getElementById('mainViewSupportBtn');
    
    if (view === 'support') {
        if (voiceC) voiceC.style.display = 'none';
        if (suppC) suppC.style.display = 'block';
        if (btnV) { btnV.classList.remove('btn-primary'); btnV.classList.add('btn-ghost'); }
        if (btnS) { btnS.classList.remove('btn-ghost'); btnS.classList.add('btn-primary'); }
        try { sessionStorage.setItem('main_agent_view', 'support'); } catch(e) {}
    } else {
        if (voiceC) voiceC.style.display = 'block';
        if (suppC) suppC.style.display = 'none';
        if (btnV) { btnV.classList.remove('btn-ghost'); btnV.classList.add('btn-primary'); }
        if (btnS) { btnS.classList.remove('btn-primary'); btnS.classList.add('btn-ghost'); }
        try { sessionStorage.setItem('main_agent_view', 'voice'); } catch(e) {}
    }
}

function viewSpecialistPrompt(sp) {
    var modal = document.getElementById('specialistPromptModal');
    var title = document.getElementById('spModalTitle');
    var role = document.getElementById('spModalRole');
    var text = document.getElementById('spModalText');
    if (!modal) return;
    
    if (title) title.innerText = sp.display_name || sp.slug;
    if (role) role.innerText = (sp.role_title || '') + ' (' + (sp.agent_type || 'specialist') + ')';
    if (text) text.value = sp.system_prompt || 'لا يوجد موجه محدد.';
    
    modal.style.display = 'flex';
}

function closeSpecialistModal() {
    var modal = document.getElementById('specialistPromptModal');
    if (modal) modal.style.display = 'none';
}

// Restore active studio tab if remembered
document.addEventListener('DOMContentLoaded', function() {
    try {
        var savedView = sessionStorage.getItem('main_agent_view');
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('view') === 'support' || savedView === 'support') {
            switchMainAgentView('support');
        }

        var savedTab = sessionStorage.getItem('active_studio_tab');
        if (savedTab && ['persona', 'skills', 'engine', 'knowledge', 'test', 'performance'].indexOf(savedTab) !== -1) {
            switchStudioTab(savedTab);
        }
    } catch(e) {}
});
</script>
