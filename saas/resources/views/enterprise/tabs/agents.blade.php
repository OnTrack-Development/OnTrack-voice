@php
    $agents = $data['agents'] ?? [];
    $selected = $data['selected_agent'] ?? null;
    $policy = $data['policy'] ?? [];
    $voices = $data['voices'] ?? [];
    $connectors = $data['connectors'] ?? [];
    $ioState = $data['io_state'] ?? [];
    $ioAccess = $data['io_access'] ?? [];
@endphp

<div class="card-head" style="margin-bottom:14px">
    <div>
        <h1 style="font-size:20px;margin:0;font-weight:700">استوديو الإيجنت والأصوات</h1>
        <p class="card-sub">صمّم الشخصية، اضبط السيناريو أو المخطط البصري، واختر الصوت مع المعاينة المحلية الفورية</p>
    </div>
    @if (!empty($data['can_create_agent']))
        <form method="POST" action="/console/action" style="display:flex;gap:8px;align-items:center">
            @csrf
            <input type="hidden" name="action" value="create_agent">
            <input type="text" name="name" placeholder="اسم الإيجنت الجديد..." required style="width:190px;padding:7px 10px">
            <button type="submit" class="btn btn-primary btn-sm">+ إنشاء إيجنت</button>
        </form>
    @endif
</div>

@if (!empty($agents))
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
        @foreach ($agents as $ag)
            <a href="/?tab=agents&agent_id={{ $ag['id'] }}"
               class="btn btn-sm {{ $selected && (int) $selected['id'] === (int) $ag['id'] ? 'btn-primary' : '' }}">
                <span>{{ $ag['name'] }}</span>
                <span class="pill" style="padding:1px 6px;font-size:10px">{{ $ag['status'] === 'published' ? 'منشور' : 'مسودة' }}</span>
            </a>
        @endforeach
    </div>
@endif

@if ($selected)
<form method="POST" action="/console/action" id="agentStudioForm">
    @csrf
    <input type="hidden" name="action" value="update_agent">
    <input type="hidden" name="agent_id" value="{{ $selected['id'] }}">
    <input type="hidden" name="voice" id="agentVoiceInput" value="{{ $selected['voice'] ?: 'google.aoede' }}">

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">إعدادات الشخصية والرد</h2>
                    <p class="card-sub">الصوت المحدد حالياً: <strong id="agentSelectedVoiceLabel" class="mono">{{ $selected['voice'] ?: 'google.aoede' }}</strong></p>
                </div>
                <span class="pill {{ $selected['status'] === 'published' ? 'pill-green' : 'pill-amber' }}">{{ $selected['status'] === 'published' ? 'منشور للشرائح' : 'مسودة' }}</span>
            </div>

            <div class="form-grid">
                <div class="field">
                    <span class="field-label">اسم الإيجنت</span>
                    <input type="text" name="name" value="{{ $selected['name'] }}" required>
                </div>
                <div class="field">
                    <span class="field-label">أسلوب اللغة واللهجة</span>
                    <select name="response_style">
                        @foreach ([
                            'egyptian_short' => 'مصري طبيعي ومختصر (أو التزام بالسيناريو)',
                            'multilingual' => 'تلقائي حسب لغة المتصل أو السيناريو',
                            'formal_ar' => 'العربية الفصحى',
                            'english' => 'English Concise',
                            'bilingual' => 'ثنائي اللغة (عربي / إنجليزي)',
                        ] as $val => $lbl)
                            <option value="{{ $val }}" {{ ($policy['response_style'] ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
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

            <div class="form-grid" style="margin:6px 0 12px">
                <label class="check-row"><input type="checkbox" name="auto_answer" value="1" {{ !empty($policy['auto_answer']) ? 'checked' : '' }}> <span>رد تلقائي على المكالمات الواردة</span></label>
                <label class="check-row"><input type="checkbox" name="use_knowledge" value="1" {{ !empty($policy['use_knowledge']) ? 'checked' : '' }}> <span>استخدام قاعدة المعرفة</span></label>
                <label class="check-row"><input type="checkbox" name="use_signed_facts" value="1" {{ !empty($policy['use_signed_facts']) ? 'checked' : '' }}> <span>تفعيل الحقائق الموقّعة / API</span></label>
                <label class="check-row"><input type="checkbox" name="human_handoff" value="1" {{ !empty($policy['human_handoff']) ? 'checked' : '' }}> <span>السماح بطلب التدخل البشري</span></label>
            </div>

            <div class="field">
                <span class="field-label">الهدف الرئيسي للمكالمة</span>
                <input type="text" name="agent_goal" value="{{ $policy['goal'] ?? '' }}">
            </div>
            <div class="field">
                <span class="field-label">الضوابط والحدود (Guardrails)</span>
                <textarea name="agent_guardrails" style="min-height:66px">{{ $policy['guardrails'] ?? '' }}</textarea>
            </div>
        </div>

        <div class="card">
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
                        <span class="field-label" style="margin:0">السيناريو الكامل للمكالمة (توصيفات الأداء بين أقواس غير منطوقة)</span>
                        <button type="button" class="btn btn-sm" onclick="const ta=document.getElementById('scenarioTextArea');if(ta){ta.value='الدور: موظفة تقييم خدمة عملاء بلهجة لبنانية لطيفة.\nالأسلوب: رد قصير جداً (جملة واحدة + سؤال واحد) وتوقفي فوراً للاستماع.\n\n1. الترحيب (عند أول رد فقط): «مرحبا، معك انجي من الدعم. فينا ناخد رأيك بخدمة اليوم؟» ثم اسكتي.\n2. الاستماع: اسمعي إجابة المتصل أولاً.\n3. سؤال التقييم (بعد رده): «كيف كانت تجربة الخدمة بشكل عام من ١ لـ ١٠؟» ثم اسكتي.\n4. المتابعة: إذا كان مبسوطاً: «شو أكتر شي عجبك؟» — وإذا منزعجاً: «آسفة جداً، شو اللي ضايقك لنصلحو فوراً؟» ثم اسكتي.\n5. الختام: «ممنونة إلك كتير ونهارك سعيد!»';}">قالب تقييم لبناني مختصر (سؤال واحد)</button>
                    </div>
                    <textarea id="scenarioTextArea" name="scenario_text" style="min-height:190px" placeholder="مثال: أهلاً بحضرتك معاك سارة من شركة...">{{ $policy['scenario_text'] ?? '' }}</textarea>
                </div>
            </div>

            <div id="visualBuilderPane" style="display:{{ ($policy['scenario_mode'] ?? 'builder') === 'builder' ? 'block' : 'none' }}">
                <div class="field">
                    <span class="field-label">التعليمات التشغيلية الإضافية</span>
                    <textarea name="instructions" style="min-height:80px">{{ $selected['instructions'] ?? '' }}</textarea>
                </div>
                <div class="field">
                    <span class="field-label">مخطط العقد والمسارات (Flow Graph JSON)</span>
                    <textarea name="flow_json" id="agentFlowJson" class="mono" style="min-height:110px;font-size:12px" dir="ltr">{{ $selected['flow_json'] }}</textarea>
                </div>
            </div>

            <div class="field">
                <span class="field-label">معرفة مختصرة مرتبطة بالإيجنت</span>
                <textarea name="knowledge" style="min-height:70px">{{ $selected['knowledge'] ?? '' }}</textarea>
            </div>
        </div>
    </div>

    @php
        $ivrByDigit = [];
        foreach ((array) ($policy['ivr_routes'] ?? []) as $rItem) {
            if (!is_array($rItem)) {
                continue;
            }
            $d = trim((string) ($rItem['digit'] ?? ''));
            if ($d !== '') {
                $ivrByDigit[$d] = $rItem;
            }
        }
        $ivrDigits = array_values(array_unique(array_merge(['1', '2', '3', '4', '5', '0'], array_keys($ivrByDigit))));
    @endphp

    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">المعاودة الفورية مع المدير (10 ثوانٍ) وقائمة الرد التفاعلي (IVR)</h2>
                <p class="card-sub">فعّل المعاودة التلقائية بعد المكالمة بـ 10 ثوانٍ على نفس الشريحة، وخصّص أقسام IVR الصوتية للتوجيه الذكي</p>
            </div>
            <span class="pill {{ !empty($policy['ivr_enabled']) ? 'pill-green' : '' }}">{{ !empty($policy['ivr_enabled']) ? 'IVR مفعّل' : 'IVR غير مفعّل' }}</span>
        </div>

        <div class="grid-2" style="margin-bottom:14px">
            <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:var(--bg)">
                <div style="font-weight:700;font-size:13.5px;margin-bottom:8px">المعاودة التلقائية للمدير بعد المكالمة (10 ثوانٍ)</div>
                <label class="check-row" style="margin-bottom:10px">
                    <input type="checkbox" name="manager_callback_enabled" value="1" {{ !empty($policy['manager_callback_enabled']) ? 'checked' : '' }}>
                    <span>تفعيل اتصال المعاودة التلقائي بعد انتهاء المكالمة بـ 10 ثوانٍ</span>
                </label>
                <div class="field" style="margin:0">
                    <span class="field-label">رقم هاتف المدير / المشرف (E.164 مثل +2010...)</span>
                    <input type="text" name="manager_callback_phone" dir="ltr" placeholder="+2010XXXXXXXX" value="{{ $policy['manager_callback_phone'] ?? '' }}">
                </div>
            </div>

            <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:var(--bg)">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
                    <span style="font-weight:700;font-size:13.5px">الترحيب الأولي للـ IVR</span>
                    <button type="button" class="btn btn-sm" onclick="const ta=document.getElementById('ivrGreetingInput');if(ta){ta.value='أهلاً بك في شركة IO Voice. للمبيعات قُل المبيعات أو اضغط 1، للدعم الفني قُل الدعم أو اضغط 2، للتحدث مع المدير قُل المدير أو اضغط 0.';}">قالب ترحيب IVR جاهز</button>
                </div>
                <label class="check-row" style="margin-bottom:10px">
                    <input type="checkbox" name="ivr_enabled" value="1" {{ !empty($policy['ivr_enabled']) ? 'checked' : '' }}>
                    <span>تشغيل قائمة الرد التفاعلي التلقائي (Hybrid IVR) عند استلام المكالمة</span>
                </label>
                <div class="field" style="margin:0">
                    <span class="field-label">رسالة الترحيب التفاعلية</span>
                    <textarea id="ivrGreetingInput" name="ivr_greeting" style="min-height:66px">{{ $policy['ivr_greeting'] ?? \App\Services\Voice\VoiceSessionService::DEFAULT_IVR_GREETING }}</textarea>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table ivr-table">
                <thead>
                    <tr>
                        <th style="width:60px">الرقم</th>
                        <th style="width:170px">اسم القسم (Label)</th>
                        <th>توجيه القسم للإيجنت (Prompt / Specialization)</th>
                        <th style="width:175px">تحويل لإيجنت (اختياري)</th>
                        <th style="width:145px">هاتف تحويل (اختياري)</th>
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

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
            <button type="submit" class="btn">حفظ المسودة</button>
            <button type="submit" name="publish_now" value="1" class="btn btn-primary">حفظ ونشر للشرائح الآن</button>
        </div>
    </div>
</form>

<!-- Integrated Zero-Token Voice Picker Grid -->
<div class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title">كتالوج الأصوات والمعاينة المحلية (0 توكن للعينات المحفوظة)</h2>
            <p class="card-sub">ابحث أو صفّي حسب المزود والجنس، استمع للعينة، واضغط على بطاقة الصوت لاختياره في الاستوديو</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="text" id="voiceSearchInput" oninput="filterVoiceCards()" placeholder="بحث بالاسم أو اللهجة..." style="width:170px;padding:6px 10px">
            <select id="voiceProviderFilter" onchange="filterVoiceCards()" style="width:auto;padding:6px 10px">
                <option value="">كل المزودين</option>
                <option value="fish_audio">Fish Audio / IO Premium</option>
                <option value="gemini_live">Google Gemini Live</option>
            </select>
            <select id="voiceGenderFilter" onchange="filterVoiceCards()" style="width:auto;padding:6px 10px">
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
                    @if ($ctx['is_platform'] && $ctx['view_mode'] === 'admin')
                        <span class="mono" style="font-size:10.5px;color:var(--dim)">#{{ $v['id'] }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Knowledge Base Quick Add & Version History -->
<div class="grid-2">
    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">قاعدة المعرفة (Knowledge Base)</h2>
        <form method="POST" action="/console/action" style="margin-bottom:12px">
            @csrf
            <input type="hidden" name="action" value="add_kb_source">
            <div class="field"><span class="field-label">عنوان المستند أو المعلومة</span><input type="text" name="name" required></div>
            <div class="field"><span class="field-label">المحتوى</span><textarea name="content" required style="min-height:70px"></textarea></div>
            <button type="submit" class="btn btn-sm">إضافة لقاعدة المعرفة</button>
        </form>
        @foreach ($data['kb_sources'] ?? [] as $kb)
            <div style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center">
                <span style="font-weight:600;font-size:13px">{{ $kb['name'] }}</span>
                <span class="mono" style="font-size:11px;color:var(--dim)">{{ $kb['created_at'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-bottom:10px">إصدارات الإيجنت المنشورة (Revisions)</h2>
        @forelse ($data['revisions'] ?? [] as $rev)
            <div style="padding:9px 11px;border:1px solid var(--border);border-radius:8px;margin-bottom:7px;display:flex;justify-content:space-between;align-items:center">
                <div>
                    <strong class="mono">#Rev {{ $rev['revision'] }}</strong>
                    <span class="mono" style="font-size:11.5px;color:var(--muted);margin-right:8px">{{ $rev['voice'] }}</span>
                </div>
                <span class="mono" style="font-size:11px;color:var(--dim)">{{ $rev['published_at'] }}</span>
            </div>
        @empty
            <p class="card-sub">لم يُنشر هذا الإيجنت بعد.</p>
        @endforelse
    </div>
</div>
@else
<div class="card"><p class="card-sub">لا يوجد إيجنت في هذا الموقع بعد. أنشئ واحداً من الأعلى للبدء.</p></div>
@endif

@if ($ctx['is_platform'] && $ctx['view_mode'] === 'admin')
<div class="card">
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
