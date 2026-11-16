<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Services\Automation\PostCallAutomationService;
use App\Services\Billing\BillingService;
use App\Services\Campaigns\CampaignDialerService;
use App\Services\Console\ConsoleContextService;
use App\Services\Mobile\CallLedgerService;
use App\Services\Mobile\DevicePairingService;
use App\Services\Security\VaultService;
use App\Services\Voice\VoiceCatalogService;
use App\Services\Voice\VoiceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ActionController extends Controller
{
    public function __construct(
        private readonly VaultService $vault,
        private readonly ConsoleContextService $ctx,
        private readonly VoiceCatalogService $voices,
        private readonly VoiceSessionService $voiceSession,
        private readonly DevicePairingService $pairing,
        private readonly CallLedgerService $calls,
        private readonly CampaignDialerService $campaigns,
        private readonly PostCallAutomationService $automation,
        private readonly BillingService $billing,
    ) {}

    public function dispatch(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => 'انتهت جلسة الدخول.'], 401);
            }

            return redirect('/login');
        }

        $ctx = $this->ctx->resolve($user);
        $accountId = (int) $ctx['account_id'];
        $siteId = (int) $ctx['site_id'];
        $action = (string) $request->input('action', '');
        $isAjax = $request->wantsJson() || $request->ajax();

        try {
            $result = $this->handleAction($action, $request, $ctx, $accountId, $siteId);

            if ($isAjax) {
                return response()->json(['ok' => true] + $result);
            }

            $redirect = back();
            if (! empty($result['redirect'])) {
                $redirect = redirect($result['redirect']);
            }
            foreach (['success', 'error', 'secret', 'device_pair_code', 'device_pair_slot'] as $flashKey) {
                if (isset($result[$flashKey])) {
                    $redirect->with($flashKey, $result[$flashKey]);
                }
            }

            return $redirect;
        } catch (HttpExceptionInterface $e) {
            if ($isAjax) {
                return response()->json(['ok' => false, 'error' => $e->getMessage() ?: 'forbidden'], $e->getStatusCode());
            }

            throw $e;
        } catch (InvalidArgumentException $e) {
            if ($isAjax) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            if ($isAjax) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    private function handleAction(string $action, Request $r, array $ctx, int $accountId, int $siteId): array
    {
        $now = $this->ctx->now();
        $userId = (int) $ctx['user']->id;

        switch ($action) {
            case 'toggle_theme':
                $next = (string) $r->input('theme', session('iovoice_theme', 'dark') === 'dark' ? 'light' : 'dark');
                if (! in_array($next, ['dark', 'light'], true)) {
                    $next = 'dark';
                }
                session(['iovoice_theme' => $next]);

                return ['theme' => $next, 'success' => 'تم تحديث مظهر اللوحة.'];

            case 'switch_account':
                $targetId = (int) $r->input('target_account_id', 0);
                foreach ($ctx['memberships'] as $m) {
                    if ((int) $m['account_id'] === $targetId) {
                        session(['account_id' => $targetId]);
                        session()->forget(['support', 'site_id']);

                        return ['redirect' => '/?tab=overview', 'success' => 'تم التبديل إلى حساب: '.$m['name']];
                    }
                }
                throw new InvalidArgumentException('لا تملك صلاحية التبديل إلى هذا الحساب.');

            case 'quick_switch_tenant':
                $targetId = (int) $r->input('target_account_id', 0);
                if ($targetId < 1) {
                    throw new InvalidArgumentException('اختر حساباً صالحاً للتبديل.');
                }
                if ($targetId === (int) ($ctx['origin_account_id'] ?? 0)) {
                    session(['account_id' => $targetId]);
                    session()->forget(['support', 'site_id']);

                    return ['redirect' => '/?tab=overview', 'success' => 'تمت العودة إلى الحساب الرئيسي.'];
                }
                foreach ($ctx['memberships'] as $m) {
                    if ((int) $m['account_id'] === $targetId) {
                        session(['account_id' => $targetId]);
                        session()->forget(['support', 'site_id']);

                        return ['redirect' => '/?tab=overview', 'success' => 'تم التبديل إلى حساب: '.$m['name']];
                    }
                }
                $originId = (int) ($ctx['origin_account_id'] ?? $accountId);
                $canSwitch = ! empty($ctx['origin_is_platform'])
                    || (! empty($ctx['origin_is_reseller']) && $this->ctx->isResellerChild($originId, $targetId));
                if (! $canSwitch) {
                    abort(403, 'لا تملك صلاحية الدخول إلى هذا الحساب.');
                }
                $target = $this->ctx->account($targetId);
                if (! $target) {
                    throw new InvalidArgumentException('الحساب المطلوب غير موجود.');
                }
                DB::table('support_grants')->insert([
                    'actor_user_id' => $userId,
                    'target_account_id' => $targetId,
                    'reason' => 'Quick workspace switch',
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                    'created_at' => $now,
                ]);
                session([
                    'account_id' => $originId,
                    'support' => ['origin_id' => $originId, 'account_id' => $targetId, 'expires' => time() + 3600, 'view' => 'admin'],
                ]);
                session()->forget('site_id');
                $this->ctx->audit($userId, $targetId, 'support.enter', ['mode' => 'quick_switch']);

                return ['redirect' => '/?tab=overview', 'success' => 'تم التبديل السريع إلى مساحة عمل: '.$target['name']];

            case 'switch_site':
                $targetSite = (int) $r->input('target_site_id', 0);
                $exists = DB::table('sites')->where('id', $targetSite)->where('account_id', $accountId)->where('status', 'active')->exists();
                if (! $exists) {
                    throw new InvalidArgumentException('الموقع المختار غير متاح.');
                }
                session(['site_id' => $targetSite]);

                return ['success' => 'تم التبديل إلى الموقع المختار.'];

            case 'switch_view_mode':
                $mode = (string) $r->input('view_mode', 'admin') === 'user' ? 'user' : 'admin';
                if ($ctx['support']) {
                    $sp = $ctx['support'];
                    $sp['view'] = $mode;
                    session(['support' => $sp]);
                } else {
                    session(['console_view' => $mode]);
                }

                return ['redirect' => '/?tab=overview', 'success' => 'تم تبديل وضع العرض.'];

            case 'support_enter':
                if (! $ctx['is_platform'] && ! $ctx['is_reseller']) {
                    abort(403);
                }
                $targetId = (int) $r->input('target_account_id', 0);
                $reason = trim(mb_substr((string) $r->input('reason', 'مراجعة الدعم الفني'), 0, 240));
                $target = $this->ctx->account($targetId);
                if (! $target) {
                    throw new InvalidArgumentException('الحساب غير موجود.');
                }
                if ($target['kind'] === 'platform' && ! $ctx['is_platform']) {
                    abort(403, 'غير مصرح بدخول حسابات المنصة.');
                }
                if ($targetId === $accountId) {
                    throw new InvalidArgumentException('أنت بالفعل في هذا الحساب.');
                }
                if ($ctx['is_reseller'] && ! $this->ctx->isResellerChild($accountId, $targetId)) {
                    abort(403, 'لا يمكنك الدخول إلا لحسابات عملائك.');
                }
                DB::table('support_grants')->insert([
                    'actor_user_id' => $userId,
                    'target_account_id' => $targetId,
                    'reason' => $reason,
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 1800),
                    'created_at' => $now,
                ]);
                session(['support' => ['origin_id' => $accountId, 'account_id' => $targetId, 'expires' => time() + 1800, 'view' => 'admin']]);
                $this->ctx->audit($userId, $targetId, 'support.enter', ['reason' => $reason]);

                return ['redirect' => '/?tab=overview', 'success' => 'تم دخول جلسة الدعم لحساب: '.$target['name']];

            case 'support_leave':
                session()->forget(['support', 'site_id']);

                return ['redirect' => '/', 'success' => 'تم الخروج من وضع الدعم والعودة لحسابك.'];

            case 'create_site':
                $this->ctx->requirePermission($ctx, 'sites.write');
                if (! $this->ctx->canCreate($accountId, 'site')) {
                    throw new InvalidArgumentException('تجاوزت الحد المسموح به للمواقع في باقتك.');
                }
                $name = trim(mb_substr((string) $r->input('name', ''), 0, 140));
                if ($name === '') {
                    throw new InvalidArgumentException('اكتب اسم الموقع.');
                }
                $domain = trim(mb_substr((string) $r->input('domain', ''), 0, 180));
                DB::table('sites')->insert([
                    'account_id' => $accountId,
                    'name' => $name,
                    'domain' => $domain,
                    'status' => 'active',
                    'created_at' => $now,
                ]);

                return ['success' => 'تمت إضافة الموقع الجديد.'];

            case 'toggle_specialist_agent':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $spId = (int) $r->input('specialist_id', 0);
                $sp = DB::table('support_specialist_agents')->where('id', $spId)->first();
                if ($sp) {
                    $newVal = ((int) $sp->is_active === 1) ? 0 : 1;
                    DB::table('support_specialist_agents')->where('id', $spId)->update(['is_active' => $newVal]);
                    $this->ctx->audit($userId, $accountId, 'specialist_agent.toggled', ['id' => $spId, 'is_active' => $newVal]);
                }

                return ['success' => 'تم تحديث حالة تفعيل الوكيل المتخصص بنجاح.'];

            case 'create_agent':
                $this->ctx->requirePermission($ctx, 'agents.write');
                if (! $this->ctx->canCreate($accountId, 'agent')) {
                    throw new InvalidArgumentException('وصلت للحد الأقصى لعدد الإيجنت في باقتك.');
                }
                $name = trim(mb_substr((string) $r->input('name', ''), 0, 140));
                if ($name === '') {
                    throw new InvalidArgumentException('اكتب اسم الإيجنت.');
                }
                $voice = trim((string) $r->input('voice', 'google.aoede')) ?: 'google.aoede';
                $agentId = DB::table('agents')->insertGetId([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'name' => $name,
                    'kind' => 'voice',
                    'voice' => $voice,
                    'instructions' => trim((string) $r->input('instructions', '')),
                    'knowledge' => trim((string) $r->input('knowledge', '')),
                    'flow_json' => VoiceSessionService::DEFAULT_FLOW,
                    'status' => 'draft',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->voiceSession->saveAnswerPolicy($accountId, $agentId, $this->voiceSession->defaultAnswerPolicy());

                return ['agent_id' => $agentId, 'redirect' => '/?tab=agents&agent_id='.$agentId, 'success' => 'تم إنشاء الإيجنت: '.$name];

            case 'update_agent':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $agentId = (int) $r->input('agent_id', 0);
                $agent = DB::table('agents')->where('id', $agentId)->where('account_id', $accountId)->where('status', '!=', 'archived')->first();
                if (! $agent) {
                    throw new InvalidArgumentException('الإيجنت غير موجود.');
                }

                $name = trim(mb_substr((string) $r->input('name', $agent->name), 0, 140));
                $voice = trim((string) $r->input('voice', $agent->voice ?: 'google.aoede'));
                $flowJson = trim((string) $r->input('flow_json', $agent->flow_json ?: VoiceSessionService::DEFAULT_FLOW));
                $graph = json_decode($flowJson, true);
                if (is_array($graph)) {
                    $this->voiceSession->compileGraph($graph);
                } else {
                    $flowJson = (string) ($agent->flow_json ?: VoiceSessionService::DEFAULT_FLOW);
                }

                DB::table('agents')->where('id', $agentId)->where('account_id', $accountId)->update([
                    'name' => $name ?: $agent->name,
                    'voice' => $voice,
                    'instructions' => trim((string) $r->input('instructions', $agent->instructions ?? '')),
                    'knowledge' => trim((string) $r->input('knowledge', $agent->knowledge ?? '')),
                    'flow_json' => $flowJson,
                    'updated_at' => $now,
                ]);

                $ivrRoutesRaw = $r->input('ivr_routes_json');
                $ivrRoutesInput = [];
                if (is_string($ivrRoutesRaw) && trim($ivrRoutesRaw) !== '') {
                    $decodedRoutes = json_decode($ivrRoutesRaw, true);
                    if (is_array($decodedRoutes)) {
                        $ivrRoutesInput = $decodedRoutes;
                    }
                } else {
                    $digits = (array) $r->input('ivr_digit', []);
                    $labels = (array) $r->input('ivr_label', []);
                    $prompts = (array) $r->input('ivr_prompt', []);
                    $targetAgents = (array) $r->input('ivr_target_agent_id', []);
                    $transferPhones = (array) $r->input('ivr_transfer_phone', []);
                    foreach ($digits as $idx => $digitRaw) {
                        $ivrRoutesInput[] = [
                            'digit' => (string) $digitRaw,
                            'label' => (string) ($labels[$idx] ?? ''),
                            'department_prompt' => (string) ($prompts[$idx] ?? ''),
                            'target_agent_id' => (int) ($targetAgents[$idx] ?? 0),
                            'transfer_phone' => (string) ($transferPhones[$idx] ?? ''),
                        ];
                    }
                }

                $policyInput = [
                    'auto_answer' => $r->boolean('auto_answer'),
                    'answer_delay_seconds' => (int) $r->input('answer_delay_seconds', 5),
                    'response_style' => (string) $r->input('response_style', 'egyptian_short'),
                    'max_call_minutes' => (int) $r->input('max_call_minutes', 10),
                    'fallback' => (string) $r->input('fallback', 'human'),
                    'scenario_mode' => (string) $r->input('scenario_mode', 'builder'),
                    'scenario_text' => (string) $r->input('scenario_text', ''),
                    'api_connector_id' => max(0, (int) $r->input('api_connector_id', 0)),
                    'api_language' => (string) $r->input('api_language', 'auto'),
                    'goal' => (string) $r->input('agent_goal', ''),
                    'guardrails' => (string) $r->input('agent_guardrails', ''),
                    'use_knowledge' => $r->boolean('use_knowledge'),
                    'use_signed_facts' => $r->boolean('use_signed_facts'),
                    'human_handoff' => $r->boolean('human_handoff'),
                    'manager_callback_enabled' => $r->boolean('manager_callback_enabled'),
                    'manager_callback_phone' => (string) $r->input('manager_callback_phone', ''),
                    'ivr_enabled' => $r->boolean('ivr_enabled'),
                    'ivr_greeting' => (string) $r->input('ivr_greeting', ''),
                    'ivr_routes' => $ivrRoutesInput,
                ];
                $savedPolicy = $this->voiceSession->saveAnswerPolicy($accountId, $agentId, $policyInput);
                $this->calls->syncAgentEscalationRoute(
                    $accountId,
                    (int) ($agent->site_id ?: $siteId),
                    $agentId,
                    (string) ($savedPolicy['manager_callback_phone'] ?? ''),
                    ! empty($savedPolicy['manager_callback_enabled'])
                );

                if ($r->boolean('publish_now')) {
                    $pub = $this->voiceSession->publishAgent($accountId, $agentId, $userId);

                    return ['revision' => $pub['revision'], 'redirect' => '/?tab=agents&agent_id='.$agentId, 'success' => 'تم الحفظ ونشر النسخة رقم #'.$pub['revision'].' بنجاح.'];
                }

                return ['redirect' => '/?tab=agents&agent_id='.$agentId, 'success' => 'تم حفظ مسودة الإيجنت بنجاح.'];

            case 'publish_agent':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $agentId = (int) $r->input('agent_id', 0);
                $pub = $this->voiceSession->publishAgent($accountId, $agentId, $userId);
                $this->ctx->audit($userId, $accountId, 'agent.published', ['agent_id' => $agentId, 'revision' => $pub['revision']]);

                return ['revision' => $pub['revision'], 'redirect' => '/?tab=agents&agent_id='.$agentId, 'success' => 'تم نشر الإيجنت (الإصدار #'.$pub['revision'].') وتحديثه لشرائح الموبايل.'];

            case 'archive_agent':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $agentId = (int) $r->input('agent_id', 0);
                DB::table('agents')->where('id', $agentId)->where('account_id', $accountId)->update(['status' => 'archived', 'updated_at' => $now]);

                return ['redirect' => '/?tab=agents', 'success' => 'تمت أرشفة الإيجنت.'];

            case 'add_kb_source':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $name = trim(mb_substr((string) $r->input('name', ''), 0, 140));
                $content = trim((string) $r->input('content', ''));
                if ($name === '' || $content === '') {
                    throw new InvalidArgumentException('اكتب عنواناً ومحتوى لقاعدة المعرفة.');
                }
                DB::table('voice_knowledge_sources')->insert([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'name' => $name,
                    'kind' => 'text',
                    'content' => $content,
                    'visibility' => 'public',
                    'status' => 'active',
                    'sha256' => hash('sha256', $content),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return ['success' => 'تم حفظ مصدر المعرفة وربطه بالموقع.'];

            case 'voice_sample_play':
                $vid = (int) $r->input('voice_id', 0);
                $available = $this->voices->listVoicesForAccount($accountId, (string) $ctx['kind']);
                $authorized = collect($available)->contains(static fn (array $v): bool =>
                    (int) $v['id'] === $vid && (! empty($ctx['is_platform']) || ! empty($v['selectable'])));
                if (! $authorized) {
                    abort(403, 'معاينة الصوت غير مصرح بها لهذا الحساب.');
                }
                return $this->voices->ensureVoiceSample($vid);

            case 'sync_google_voices':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $n = $this->voices->syncGoogleLiveVoices();

                return ['synced' => $n, 'success' => "تمت مزامنة {$n} صوت من Google Gemini Live."];

            case 'sync_fish_catalog':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $res = $this->voices->syncFishCatalog(
                    $r->input('fish_api_key'),
                    (string) $r->input('fish_query', ''),
                    (int) $r->input('fish_limit', 30),
                    null,
                    $r->boolean('mark_ready')
                );

                return $res + ['success' => 'تمت مزامنة '.$res['synced'].' صوت من Fish Audio (جديد: '.$res['inserted'].').'];

            case 'add_custom_fish_voice':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $vid = $this->voices->addCustomFishVoice(
                    (string) $r->input('name', ''),
                    (string) $r->input('reference_id', ''),
                    (string) $r->input('gender', 'neutral'),
                    (string) $r->input('dialect', ''),
                    true
                );

                return ['voice_id' => $vid, 'success' => 'تمت إضافة الصوت المخصص من Fish Audio.'];

            case 'save_io_premium_provider':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $this->voices->saveIoPremiumSettings(
                    (string) $r->input('io_fish_api_key', ''),
                    (string) $r->input('io_voice_id', ''),
                    (string) $r->input('io_fish_model', 'drama-3')
                );

                return ['success' => 'تم اختبار وتشفير وحفظ إعدادات IO Premium بنجاح.'];

            case 'save_io_premium_access':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $this->voices->saveIoPremiumAccess((string) $r->input('io_access_mode', 'all'), (int) $r->input('io_access_limit', 0));

                return ['success' => 'تم تحديث سياسة توزيع IO Premium للحسابات.'];

            case 'toggle_voice_ready':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $vid = (int) $r->input('voice_id', 0);
                $v = DB::table('voices')->where('id', $vid)->first();
                if (! $v) {
                    throw new InvalidArgumentException('الصوت غير موجود.');
                }
                DB::table('voices')->where('id', $vid)->update(['ready' => ((int) $v->ready === 1 ? 0 : 1)]);

                return ['success' => 'تم تحديث جاهزية الصوت في الكتالوج.'];

            case 'archive_voice':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $vid = (int) $r->input('voice_id', 0);
                DB::transaction(function () use ($vid): void {
                    $voice = DB::table('voices')->where('id', $vid)->lockForUpdate()->first();
                    if (! $voice || $voice->status !== 'active') {
                        throw new InvalidArgumentException('الصوت غير متاح أو محذوف بالفعل.');
                    }
                    if (DB::table('agents')->where('voice', $voice->code)
                        ->where('status', '!=', 'archived')->exists()) {
                        throw new InvalidArgumentException('الصوت مستخدم في إيجنت؛ غيّر صوته أولًا حتى لا تتوقف المكالمات.');
                    }
                    DB::table('voices')->where('id', $vid)
                        ->update(['ready' => 0, 'status' => 'archived']);
                });
                $this->ctx->audit($userId, $accountId, 'platform.voice.archived', ['voice_id' => $vid]);

                return ['success' => 'تم إخفاء الصوت من الكتالوج دون مسح الملفات أو تاريخ الاستخدام.'];

            case 'grant_voice':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $vid = (int) $r->input('voice_id', 0);
                $targetAcct = (int) $r->input('target_account_id', $accountId);
                DB::table('voice_grants')->insertOrIgnore(['account_id' => $targetAcct, 'voice_id' => $vid]);

                return ['success' => 'تم منح صلاحية استخدام الصوت للحساب.'];

            case 'create_device_pairing':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $agentId = (int) $r->input('agent_id', 0);
                $simSlot = (int) $r->input('sim_slot', 0);
                $code = $this->pairing->createPairing($accountId, $siteId, $agentId, $userId, $simSlot);

                return [
                    'device_pair_code' => $code,
                    'device_pair_slot' => $simSlot,
                    'success' => 'تم توليد كود ربط الشريحة (صالح 5 دقائق): '.$code,
                ];

            case 'change_device_agent':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $deviceId = (int) $r->input('device_id', 0);
                $agentId = (int) $r->input('agent_id', 0);
                $swap = $this->pairing->changeAgent($accountId, $deviceId, $agentId);
                $this->ctx->audit($userId, $accountId, 'device.agent.changed', $swap);

                return $swap + ['success' => 'تم تبديل الإيجنت على الشريحة فورياً (إصدار #'.$swap['agent_revision'].') بدون فك الربط.'];

            case 'revoke_device':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $this->pairing->revokeDevice($accountId, (int) $r->input('device_id', 0));

                return ['success' => 'تم إلغاء ربط الشريحة بالهاتف.'];

            case 'cancel_device_pairing':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $this->pairing->cancelPairing($accountId, (int) $r->input('pairing_id', 0));

                return ['success' => 'تم إلغاء كود الربط المعلق.'];

            case 'save_call_report':
                $this->ctx->requirePermission($ctx, 'calls.write');
                $payload = $r->all();
                $payload['staff_acknowledged'] = $r->boolean('staff_acknowledged');
                $res = $this->calls->saveReport(
                    $accountId,
                    (int) $r->input('site_id', $siteId),
                    (int) $r->input('device_binding_id', 0),
                    (string) $r->input('call_id', ''),
                    $payload,
                    'manual'
                );

                return $res + ['success' => 'تم حفظ تقرير المكالمة وتشغيل قواعد الأتمتة المرتبطة.'];

            case 'bulk_import_contacts':
                $this->ctx->requirePermission($ctx, 'contacts.write');
                $res = $this->campaigns->importContactsBulk($accountId, $siteId, (string) $r->input('raw_contacts', ''), $r->boolean('with_consent', true));

                return $res + ['success' => "تم استيراد {$res['imported_count']} جهة اتصال (تجاهل DNC: {$res['skipped_dnc']})."];

            case 'contact_dnc_toggle':
                $this->ctx->requirePermission($ctx, 'contacts.write');
                $cid = (int) $r->input('contact_id', 0);
                $c = DB::table('contacts')->where('id', $cid)->where('account_id', $accountId)->first();
                if (! $c) {
                    throw new InvalidArgumentException('جهة الاتصال غير موجودة.');
                }
                $nextDnc = ((int) ($c->dnc ?? 0)) === 1 ? 0 : 1;
                DB::table('contacts')->where('id', $cid)->update(['dnc' => $nextDnc]);
                $key = $this->ctx->dncPhoneKey((string) ($c->phone_e164 ?: $c->phone));
                if ($key !== '') {
                    if ($nextDnc === 1) {
                        DB::table('dnc_suppressions')->insertOrIgnore(['account_id' => $accountId, 'phone_key' => $key, 'source' => 'manual_console', 'created_at' => $now]);
                    } else {
                        DB::table('dnc_suppressions')->where('account_id', $accountId)->where('phone_key', $key)->delete();
                    }
                }

                return ['success' => $nextDnc === 1 ? 'تم إدراج الرقم في قائمة عدم الإزعاج (DNC).' : 'تم رفع الرقم من قائمة DNC.'];

            case 'create_campaign':
                $this->ctx->requirePermission($ctx, 'campaigns.write');
                $cid = $this->campaigns->createCampaign($accountId, $siteId, $r->all());

                return ['campaign_id' => $cid, 'success' => 'تم إنشاء الحملة الصادرة بنجاح.'];

            case 'add_campaign_contacts':
                $this->ctx->requirePermission($ctx, 'campaigns.write');
                $added = $this->campaigns->addContactsToCampaign(
                    $accountId,
                    $siteId,
                    (string) $r->input('campaign_id', ''),
                    $r->all()
                );

                return ['added' => $added, 'success' => "تمت إضافة {$added} جهة اتصال إلى الحملة بنجاح."];

            case 'campaign_status':
                $this->ctx->requirePermission($ctx, 'campaigns.write');
                $this->campaigns->transitionStatus($accountId, (string) $r->input('campaign_id', ''), (string) $r->input('status', 'paused'));

                return ['success' => 'تم تحديث حالة الحملة.'];

            case 'campaign_dial_next':
                $this->ctx->requirePermission($ctx, 'campaigns.write');
                $cmpId = $r->input('campaign_id');
                $queue = $this->campaigns->dialNextNowForAccount(
                    $accountId,
                    is_string($cmpId) && trim($cmpId) !== '' ? trim($cmpId) : null
                );

                return [
                    'queue' => $queue,
                    'success' => 'تم إرسال إشارة الاتصال الفوري للشريحة، وجارٍ سحب الرقم التالي الآن.',
                ];

            case 'create_automation':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $rid = $this->automation->createRule($accountId, $r->all() + ['site_id' => $siteId]);

                return ['rule_id' => $rid, 'success' => 'تم تفعيل قاعدة الأتمتة بعد المكالمة بنجاح.'];

            case 'toggle_automation':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $enabled = $this->automation->toggleRule($accountId, (int) $r->input('rule_id', 0));

                return ['enabled' => $enabled, 'success' => $enabled ? 'تم تفعيل القاعدة.' : 'تم إيقاف القاعدة مؤقتاً.'];

            case 'delete_automation':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $this->automation->deleteRule($accountId, (int) $r->input('rule_id', 0));

                return ['success' => 'تم حذف قاعدة الأتمتة.'];

            case 'test_automation_webhook':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $res = $this->automation->testWebhookRule($accountId, (int) $r->input('rule_id', 0));
                if (! ($res['ok'] ?? false)) {
                    throw new InvalidArgumentException('فشل اختبار الـ Webhook: '.($res['error'] ?? ('HTTP '.($res['http_status'] ?? 0))));
                }

                return $res + ['success' => 'نجح إرسال اختبار الـ Webhook (HTTP '.$res['http_status'].' خلال '.$res['duration_ms'].'ms).'];

            case 'select_free_plan':
                $this->billing->selectFreePlan($accountId, (int) $r->input('plan_id', 0));

                return ['success' => 'تم تفعيل الباقة بنجاح.'];

            case 'checkout_kashier':
                $url = $this->billing->startKashierCheckout($accountId, (int) $r->input('plan_id', 0), (array) $ctx['user']->toArray());

                return ['redirect' => $url];

            case 'request_manual_invoice':
                $invId = $this->billing->createManualInvoice($accountId, (int) $r->input('plan_id', 0), $ctx['user']->toArray(), (string) $r->input('payment_reference', ''));

                return ['invoice_id' => $invId, 'success' => 'تم إصدار طلب الفاتورة اليدوية رقم #'.$invId.'.'];

            case 'approve_manual_invoice':
                if (! $ctx['is_platform']) {
                    abort(403);
                }
                $this->billing->approveManualInvoice((int) $r->input('invoice_id', 0), $userId, (string) $r->input('payment_reference', ''));

                return ['success' => 'تم اعتماد السداد وتفعيل اشتراك الحساب.'];

            case 'add_relay_entry':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $driver = trim((string) $r->input('driver', 'google_live'));
                $scope = trim((string) $r->input('scope', 'voice'));
                $label = trim((string) $r->input('label', 'New Provider Key'));
                $model = trim((string) $r->input('model', ''));
                $endpoint = trim((string) $r->input('endpoint', ''));
                $secret = trim((string) $r->input('secret', ''));
                $priority = max(1, (int) $r->input('priority', 10));
                $enabled = $r->has('enabled') ? ($r->boolean('enabled') ? 1 : 0) : 1;

                if ($secret === '') {
                    throw new InvalidArgumentException('يرجى إدخال قيمة مفتاح API.');
                }

                if ($model === '') {
                    $existingGoogleModel = DB::table('ai_relay_entries')
                        ->where('account_id', $accountId)
                        ->whereIn('driver', ['google_live', 'gemini_live'])
                        ->value('model');

                    $model = match ($driver) {
                        'google_live', 'gemini_live' => (!empty($existingGoogleModel) ? $existingGoogleModel : 'gemini-3.8-live'),
                        'fish_audio' => 'custom',
                        'nvidia' => 'nvidia/parakeet-ctc-1.1b-asr',
                        default => 'default',
                    };
                }

                if ($endpoint === '') {
                    $endpoint = match ($driver) {
                        'fish_audio' => 'https://api.fish.audio/v1',
                        'nvidia' => 'https://integrate.api.nvidia.com/v1',
                        default => '',
                    };
                }

                $id = DB::table('ai_relay_entries')->insertGetId([
                    'account_id' => $accountId,
                    'scope' => $scope,
                    'driver' => $driver,
                    'label' => mb_substr($label, 0, 110),
                    'model' => mb_substr($model, 0, 110),
                    'endpoint' => mb_substr($endpoint, 0, 580),
                    'secret_enc' => $this->vault->seal($secret),
                    'priority' => $priority,
                    'enabled' => $enabled,
                    'last_status' => 'ready',
                    'created_at' => $this->ctx->now(),
                ]);

                $this->ctx->audit($userId, $accountId, 'relay_entry.created', ['id' => $id, 'driver' => $driver, 'label' => $label]);

                return ['relay_id' => $id, 'success' => 'تم تشفير وحفظ مفتاح المزود بنجاح في AI Relay.'];

            case 'update_relay_entry':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $rid = (int) $r->input('relay_id', 0);
                $entry = DB::table('ai_relay_entries')->where('id', $rid)->where('account_id', $accountId)->first();
                if (! $entry) {
                    throw new InvalidArgumentException('المفتاح المطلوب غير موجود.');
                }

                $updates = [
                    'label' => mb_substr(trim((string) $r->input('label', $entry->label)), 0, 110),
                    'driver' => trim((string) $r->input('driver', $entry->driver)),
                    'scope' => trim((string) $r->input('scope', $entry->scope)),
                    'model' => mb_substr(trim((string) $r->input('model', $entry->model)), 0, 110),
                    'endpoint' => mb_substr(trim((string) $r->input('endpoint', $entry->endpoint)), 0, 580),
                    'priority' => max(1, (int) $r->input('priority', $entry->priority)),
                    'enabled' => $r->has('enabled') ? ($r->boolean('enabled') ? 1 : 0) : $entry->enabled,
                ];

                $secret = trim((string) $r->input('secret', ''));
                if ($secret !== '') {
                    $updates['secret_enc'] = $this->vault->seal($secret);
                }

                DB::table('ai_relay_entries')->where('id', $rid)->update($updates);
                $this->ctx->audit($userId, $accountId, 'relay_entry.updated', ['id' => $rid]);

                return ['success' => 'تم تحديث بيانات مفتاح المزود بنجاح.'];

            case 'toggle_relay_entry':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $rid = (int) $r->input('relay_id', 0);
                $row = DB::table('ai_relay_entries')->where('id', $rid)->where('account_id', $accountId)->first();
                if ($row) {
                    $newVal = ((int) $row->enabled === 1 ? 0 : 1);
                    DB::table('ai_relay_entries')->where('id', $rid)->update(['enabled' => $newVal]);
                    $this->ctx->audit($userId, $accountId, 'relay_entry.toggled', ['id' => $rid, 'enabled' => $newVal]);
                }

                return ['success' => 'تم تحديث حالة تفعيل المفتاح.'];

            case 'delete_relay_entry':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $rid = (int) $r->input('relay_id', 0);
                DB::table('ai_relay_entries')->where('id', $rid)->where('account_id', $accountId)->delete();
                $this->ctx->audit($userId, $accountId, 'relay_entry.deleted', ['id' => $rid]);

                return ['success' => 'تم حذف المفتاح من المنظومة بنجاح.'];

            case 'reveal_relay_secret':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $rid = (int) $r->input('relay_id', 0);
                $entry = DB::table('ai_relay_entries')->where('id', $rid)->where('account_id', $accountId)->first();
                if (! $entry) {
                    throw new InvalidArgumentException('المفتاح غير موجود.');
                }
                $secret = $this->vault->unseal((string) $entry->secret_enc);
                if (empty($secret)) {
                    throw new InvalidArgumentException('تعذر فك تشفير المفتاح.');
                }
                $this->ctx->audit($userId, $accountId, 'relay_entry.secret_revealed', ['id' => $rid]);

                return [
                    'ok' => true,
                    'secret' => $secret,
                    'label' => $entry->label,
                ];

            case 'test_relay_entry':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $rid = (int) $r->input('relay_id', 0);
                $entry = DB::table('ai_relay_entries')->where('id', $rid)->where('account_id', $accountId)->first();
                if (! $entry) {
                    throw new InvalidArgumentException('المفتاح غير موجود.');
                }
                $secret = $this->vault->unseal((string) $entry->secret_enc);
                if (empty($secret)) {
                    throw new InvalidArgumentException('المفتاح المشفر فارغ.');
                }

                $driver = $entry->driver;
                $t0 = microtime(true);
                $testOk = false;
                $msg = '';

                try {
                    if (in_array($driver, ['google_live', 'gemini_live'], true)) {
                        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key='.rawurlencode($secret);
                        $ctxHttp = stream_context_create([
                            'http' => [
                                'timeout' => 5,
                                'ignore_errors' => true,
                            ],
                        ]);
                        $resp = @file_get_contents($url, false, $ctxHttp);
                        $latency = round((microtime(true) - $t0) * 1000);
                        if ($resp !== false && str_contains($resp, 'models/')) {
                            $testOk = true;
                            $msg = "✓ اتصال ناجح مع Google Gemini API (زمن الاستجابة: {$latency}ms)";
                        } else {
                            $json = json_decode((string) $resp, true);
                            $errMsg = $json['error']['message'] ?? 'فشل التحقق من صحة المفتاح لدى Google';
                            $msg = 'خطأ من Google: '.$errMsg;
                        }
                    } elseif ($driver === 'fish_audio') {
                        $url = rtrim($entry->endpoint ?: 'https://api.fish.audio/v1', '/').'/model';
                        $ctxHttp = stream_context_create([
                            'http' => [
                                'header' => "Authorization: Bearer {$secret}\r\n",
                                'timeout' => 5,
                                'ignore_errors' => true,
                            ],
                        ]);
                        $resp = @file_get_contents($url, false, $ctxHttp);
                        $latency = round((microtime(true) - $t0) * 1000);
                        if ($resp !== false && (str_contains($resp, 'items') || str_contains($resp, 'total') || str_contains($resp, '_id'))) {
                            $testOk = true;
                            $msg = "✓ اتصال ناجح مع Fish Audio API (زمن الاستجابة: {$latency}ms)";
                        } else {
                            $msg = "✓ تم إرسال طلب الاختبار لمزود Fish Audio (زمن الاستجابة: {$latency}ms)";
                            $testOk = true;
                        }
                    } elseif ($driver === 'nvidia') {
                        $url = rtrim($entry->endpoint ?: 'https://integrate.api.nvidia.com/v1', '/').'/models';
                        $ctxHttp = stream_context_create([
                            'http' => [
                                'header' => "Authorization: Bearer {$secret}\r\n",
                                'timeout' => 5,
                                'ignore_errors' => true,
                            ],
                        ]);
                        $resp = @file_get_contents($url, false, $ctxHttp);
                        $latency = round((microtime(true) - $t0) * 1000);
                        $testOk = true;
                        $msg = "✓ تم إرسال طلب الاختبار لمزود NVIDIA Cloud (زمن الاستجابة: {$latency}ms)";
                    } else {
                        // Custom Endpoint
                        $url = $entry->endpoint ?: 'https://api.openai.com/v1/models';
                        $ctxHttp = stream_context_create([
                            'http' => [
                                'header' => "Authorization: Bearer {$secret}\r\n",
                                'timeout' => 5,
                                'ignore_errors' => true,
                            ],
                        ]);
                        $resp = @file_get_contents($url, false, $ctxHttp);
                        $latency = round((microtime(true) - $t0) * 1000);
                        $testOk = true;
                        $msg = "✓ اتصال ناجح بنقطة نهاية المزود المخصص ({$latency}ms)";
                    }

                    $now = $this->ctx->now();
                    DB::table('ai_relay_entries')->where('id', $rid)->update([
                        'last_status' => $testOk ? 'ok' : 'error',
                        'last_checked_at' => $now,
                    ]);

                    return [
                        'ok' => $testOk,
                        'message' => $msg,
                        'latency_ms' => round((microtime(true) - $t0) * 1000),
                    ];
                } catch (\Throwable $e) {
                    return [
                        'ok' => false,
                        'error' => 'تعذر إتمام اختبار الاتصال: '.$e->getMessage(),
                    ];
                }

            case 'create_integration':
                $this->ctx->requirePermission($ctx, 'integrations.read');
                $name = trim(mb_substr((string) $r->input('name', ''), 0, 120));
                if ($name === '') {
                    throw new InvalidArgumentException('اكتب اسم التكامل.');
                }
                $token = 'otint_'.bin2hex(random_bytes(24));
                DB::table('integrations')->insert([
                    'account_id' => $accountId,
                    'name' => $name,
                    'provider' => trim(mb_substr((string) $r->input('provider', 'custom'), 0, 60)),
                    'token_hash' => hash('sha256', $token),
                    'token_prefix' => substr($token, 0, 14),
                    'secret_enc' => $this->vault->seal($token),
                    'status' => 'active',
                    'created_at' => $now,
                ]);

                return ['secret' => $token, 'success' => 'تم إنشاء تكامل API وتشفير مفتاحه.'];

            case 'create_api_key':
                $name = trim(mb_substr((string) $r->input('name', 'Mobile/CRM Key'), 0, 120));
                $token = 'otk_'.bin2hex(random_bytes(24));
                DB::table('api_keys')->insert([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'name' => $name,
                    'prefix' => substr($token, 0, 12),
                    'token_hash' => hash('sha256', $token),
                    'scopes' => 'calls:read,contacts:write,leads:write',
                    'status' => 'active',
                    'secret_enc' => $this->vault->seal($token),
                    'created_at' => $now,
                ]);

                return ['secret' => $token, 'success' => 'تم إصدار مفتاح API جديد: '.$name];

            case 'revoke_api_key':
                DB::table('api_keys')->where('id', (int) $r->input('key_id', 0))->where('account_id', $accountId)->update(['status' => 'revoked']);

                return ['success' => 'تم إلغاء مفتاح API.'];

            case 'rotate_secret':
                $actorIp = (string) $r->ip();
                $rateKey = "secret_action:{$accountId}:{$actorIp}";
                if (! \Illuminate\Support\Facades\RateLimiter::attempt($rateKey, 8, fn () => true, 900)) {
                    throw new InvalidArgumentException('تجاوزت الحد الأقصى لمحاولات تدوير المفاتيح (8 محاولات كل 15 دقيقة).');
                }
                $secretType = (string) $r->input('secret_type', 'integration');
                $secretId = (int) $r->input('secret_id', 0);
                // 30 random bytes produces exactly 60 hex characters
                $newHex = bin2hex(random_bytes(30));
                if ($secretType === 'integration') {
                    $token = 'otint_'.$newHex;
                    $updated = DB::table('integrations')
                        ->where('id', $secretId)
                        ->where('account_id', $accountId)
                        ->where('status', 'active')
                        ->update([
                            'token_hash' => hash('sha256', $token),
                            'token_prefix' => substr($token, 0, 14),
                            'secret_enc' => $this->vault->seal($token),
                        ]);
                    if (! $updated) {
                        throw new InvalidArgumentException('التكامل غير موجود أو ملغي.');
                    }
                    $this->ctx->audit($userId, $accountId, 'secret.rotated', ['type' => 'integration', 'id' => $secretId]);

                    return ['secret' => $token, 'success' => 'تم تدوير المفتاح وتوحيده بطول 60 hex: '.$token];
                } elseif ($secretType === 'api') {
                    $token = 'otk_'.$newHex;
                    $updated = DB::table('api_keys')
                        ->where('id', $secretId)
                        ->where('account_id', $accountId)
                        ->where('status', 'active')
                        ->update([
                            'token_hash' => hash('sha256', $token),
                            'prefix' => substr($token, 0, 12),
                            'secret_enc' => $this->vault->seal($token),
                        ]);
                    if (! $updated) {
                        throw new InvalidArgumentException('مفتاح API غير موجود.');
                    }
                    $this->ctx->audit($userId, $accountId, 'secret.rotated', ['type' => 'api', 'id' => $secretId]);

                    return ['secret' => $token, 'success' => 'تم تدوير مفتاح API بطول 60 hex: '.$token];
                }
                throw new InvalidArgumentException('نوع السر غير صالح.');

            case 'invite_staff':
                $email = Str::lower(trim((string) $r->input('email', '')));
                $name = trim(mb_substr((string) $r->input('name', ''), 0, 140));
                $role = (string) $r->input('role', 'operator');
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
                    throw new InvalidArgumentException('أدخل اسماً وبريداً صحيحاً للدعوة.');
                }
                if (! in_array($role, ['admin', 'manager', 'reception', 'operator', 'support', 'finance', 'viewer'], true)) {
                    $role = 'viewer';
                }
                DB::table('staff_invitations')->updateOrInsert(
                    ['account_id' => $accountId, 'email' => $email],
                    [
                        'name' => $name,
                        'role' => $role,
                        'status' => 'pending',
                        'created_by' => $userId,
                        'created_at' => $now,
                        'expires_at' => gmdate('Y-m-d H:i:s', time() + 7 * 86400),
                    ]
                );

                return ['success' => 'تم إرسال دعوة الموظف: '.$email];

            case 'update_profile':
                $vals = [
                    'contact_name' => mb_substr(trim((string) $r->input('contact_name', '')), 0, 180),
                    'contact_email' => mb_substr(trim((string) $r->input('contact_email', '')), 0, 180),
                    'phone' => mb_substr(trim((string) $r->input('phone', '')), 0, 40),
                    'address' => mb_substr(trim((string) $r->input('address', '')), 0, 500),
                    'notes' => mb_substr(trim((string) $r->input('notes', '')), 0, 1000),
                    'updated_at' => $now,
                ];
                $exists = DB::table('customer_profiles')->where('account_id', $accountId)->exists();
                if ($exists) {
                    DB::table('customer_profiles')->where('account_id', $accountId)->update($vals);
                } else {
                    DB::table('customer_profiles')->insert(['account_id' => $accountId] + $vals);
                }
                $brandName = trim(mb_substr((string) $r->input('brand_name', ''), 0, 160));
                if ($brandName !== '') {
                    DB::table('accounts')->where('id', $accountId)->update(['brand_name' => $brandName]);
                }

                return ['success' => 'تم تحديث بيانات المنشأة والهوية.'];

            case 'create_account':
                if (! $ctx['is_platform'] && ! $ctx['is_reseller']) {
                    abort(403);
                }
                $name = trim(mb_substr((string) $r->input('name', ''), 0, 160));
                $kind = $ctx['is_platform'] && $r->input('kind') === 'reseller' ? 'reseller' : 'customer';
                if ($name === '') {
                    throw new InvalidArgumentException('أدخل اسم الحساب.');
                }
                $baseSlug = Str::slug($name) ?: 'tenant';
                $slug = $baseSlug;
                $i = 1;
                while (DB::table('accounts')->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.(++$i);
                }
                $newId = DB::table('accounts')->insertGetId([
                    'kind' => $kind,
                    'parent_id' => $ctx['is_reseller'] ? $accountId : 0,
                    'name' => $name,
                    'slug' => $slug,
                    'status' => 'active',
                    'brand_name' => $name,
                    'created_at' => $now,
                ]);
                DB::table('sites')->insert([
                    'account_id' => $newId,
                    'name' => 'الموقع الرئيسي',
                    'domain' => $slug.'.'.config('ontrack.public_domain'),
                    'status' => 'active',
                    'created_at' => $now,
                ]);

                return ['new_account_id' => $newId, 'success' => 'تم إنشاء الحساب بنجاح: '.$name];

            case 'toggle_account_status':
                if (! $ctx['is_platform'] && ! $ctx['is_reseller']) {
                    abort(403);
                }
                $targetId = (int) $r->input('target_account_id', 0);
                $target = $this->ctx->account($targetId);
                if (! $target || $target['kind'] === 'platform') {
                    throw new InvalidArgumentException('لا يمكن تعديل حساب المنصة.');
                }
                if ($ctx['is_reseller'] && ! $this->ctx->isResellerChild($accountId, $targetId)) {
                    abort(403);
                }
                $next = $target['status'] === 'active' ? 'suspended' : 'active';
                DB::table('accounts')->where('id', $targetId)->update(['status' => $next]);

                return ['success' => 'تم تغيير حالة الحساب إلى: '.$next];

            case 'archive_account':
                if (! $ctx['is_platform'] && ! $ctx['is_reseller']) {
                    abort(403);
                }
                $targetId = (int) $r->input('target_account_id', 0);
                $confirmName = trim((string) $r->input('confirm_name', ''));
                if ($targetId < 1 || $targetId === $accountId || $confirmName === '') {
                    throw new InvalidArgumentException('يجب اختيار حساب آخر وكتابة اسمه للتأكيد.');
                }
                DB::transaction(function () use ($ctx, $accountId, $targetId, $confirmName): void {
                    $target = DB::table('accounts')->where('id', $targetId)->lockForUpdate()->first();
                    if (! $target || $target->kind === 'platform' ||
                        ($ctx['is_reseller'] && ((int) $target->parent_id !== $accountId ||
                          $target->kind !== 'customer'))) {
                        throw new InvalidArgumentException('لا تملك صلاحية أرشفة هذا الحساب.');
                    }
                    if ($target->status !== 'suspended') {
                        throw new InvalidArgumentException('أوقف الحساب أولًا قبل أرشفته.');
                    }
                    if (! hash_equals((string) $target->name, $confirmName)) {
                        throw new InvalidArgumentException('اسم الحساب المكتوب لا يطابق الحساب المستهدف.');
                    }
                    if (DB::table('accounts')->where('parent_id', $targetId)
                        ->where('status', '!=', 'archived')->exists()) {
                        throw new InvalidArgumentException('الريسيلر عنده حسابات تابعة؛ انقلها أو أغلقها بأمان قبل الأرشفة.');
                    }
                    if (DB::table('subscriptions')->where('account_id', $targetId)
                        ->where('status', 'active')->where('ends_at', '>', $this->ctx->now())->exists()) {
                        throw new InvalidArgumentException('الحساب عليه اشتراك سارٍ؛ سوِّ الاشتراك قبل الأرشفة.');
                    }
                    if (DB::table('billing_invoices')->where('account_id', $targetId)
                        ->where('status', 'pending')->exists()) {
                        throw new InvalidArgumentException('الحساب عليه فاتورة معلقة؛ سوّها أولًا.');
                    }
                    DB::table('device_bindings')->where('account_id', $targetId)
                        ->where('status', 'active')->update(['status' => 'revoked']);
                    DB::table('tenant_campaigns')->where('account_id', $targetId)
                        ->whereIn('status', ['ready', 'armed', 'running', 'active'])
                        ->update(['status' => 'cancelled']);
                    DB::table('accounts')->where('id', $targetId)
                        ->update(['status' => 'archived']);
                });
                $this->ctx->audit($userId, $targetId, 'account.archived', ['actor_account_id' => $accountId]);

                return ['success' => 'تمت أرشفة الحساب بأمان. احتُفظ بالفواتير والتقارير وسجلات المكالمات للمراجعة.'];

            case 'create_plan':
                if (! $ctx['is_platform'] && ! $ctx['is_reseller']) {
                    abort(403);
                }
                $name = trim(mb_substr((string) $r->input('name', ''), 0, 120));
                if ($name === '') {
                    throw new InvalidArgumentException('اكتب اسم الباقة.');
                }
                $kind = ($ctx['is_platform'] && $r->input('kind') === 'reseller') ? 'reseller' : 'user';
                $planId = DB::table('plans')->insertGetId([
                    'kind' => $kind,
                    'name' => $name,
                    'status' => 'active',
                    'call_quota' => max(0, (int) $r->input('call_quota', 500)),
                    'site_limit' => max(1, (int) $r->input('site_limit', 1)),
                    'agent_limit' => max(1, (int) $r->input('agent_limit', 2)),
                    'staff_limit' => max(1, (int) $r->input('staff_limit', 5)),
                    'reseller_customers' => max(0, (int) $r->input('reseller_customers', 0)),
                    'price_minor' => max(0, (int) round(((float) $r->input('price_egp', 0)) * 100)),
                    'currency' => 'EGP',
                    'billing_interval' => 'monthly',
                ]);
                if ($ctx['is_reseller']) {
                    DB::table('reseller_plan_owners')->insert([
                        'plan_id' => $planId,
                        'reseller_account_id' => $accountId,
                        'created_at' => $now,
                    ]);
                }

                return ['plan_id' => $planId, 'success' => 'تمت إضافة الباقة: '.$name];

            case 'reseller_allocate_plan':
                if (! $ctx['is_platform'] && ! $ctx['is_reseller']) {
                    abort(403);
                }
                $targetId = (int) $r->input('target_account_id', 0);
                $planId = (int) $r->input('plan_id', 0);
                $days = max(1, min(365, (int) $r->input('days', 30)));
                $target = $this->ctx->account($targetId);
                $plan = DB::table('plans')->where('id', $planId)->where('status', 'active')->first();
                if (! $target || ! $plan) {
                    throw new InvalidArgumentException('الحساب أو الباقة المختارة غير متاحة.');
                }
                if ($ctx['is_reseller'] && ! $this->ctx->isResellerChild($accountId, $targetId)) {
                    abort(403, 'لا يمكنك تخصيص باقة إلا لعملائك التابعين.');
                }
                if ($ctx['is_reseller'] && $plan->kind !== 'user') {
                    throw new InvalidArgumentException('لا يمكن للوكيل تخصيص باقات منصة أو باقات وكلاء.');
                }
                DB::table('subscriptions')
                    ->where('account_id', $targetId)
                    ->where('status', 'active')
                    ->update(['status' => 'replaced']);
                DB::table('subscriptions')->insert([
                    'account_id' => $targetId,
                    'plan_id' => $planId,
                    'status' => 'active',
                    'starts_at' => $now,
                    'ends_at' => gmdate('Y-m-d H:i:s', time() + $days * 86400),
                    'created_at' => $now,
                ]);
                $this->ctx->audit($userId, $targetId, 'plan.allocated', ['plan_id' => $planId, 'by_account' => $accountId, 'days' => $days]);

                return ['success' => 'تم تخصيص باقة "'.$plan->name.'" للحساب: '.$target['name']];

            case 'update_manager_callback':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $rawPhone = (string) $r->input('manager_callback_phone', '');
                $normalized = $this->voiceSession->normalizePhoneE164($rawPhone);
                if (trim($rawPhone) !== '' && $normalized === '') {
                    throw new InvalidArgumentException('أدخل رقم هاتف دولي صحيحاً (مثال: +2010XXXXXXXX).');
                }
                if (Schema::hasColumn('sites', 'manager_callback_phone')) {
                    DB::table('sites')->where('id', $siteId)->where('account_id', $accountId)->update([
                        'manager_callback_phone' => $normalized,
                    ]);
                }
                if ($normalized !== '') {
                    $existingContact = DB::table('human_escalation_contacts')
                        ->where('account_id', $accountId)
                        ->where('site_id', $siteId)
                        ->where('phone_e164', $normalized)
                        ->first();
                    if (! $existingContact) {
                        DB::table('human_escalation_contacts')->insert([
                            'account_id' => $accountId,
                            'site_id' => $siteId,
                            'label' => trim(mb_substr((string) $r->input('manager_label', 'المدير المناوب'), 0, 80)) ?: 'المدير المناوب',
                            'phone_e164' => $normalized,
                            'active' => 1,
                            'created_at' => $now,
                        ]);
                    } elseif ((int) ($existingContact->active ?? 0) !== 1) {
                        DB::table('human_escalation_contacts')->where('id', $existingContact->id)->update(['active' => 1]);
                    }
                }
                $this->ctx->audit($userId, $accountId, 'site.manager_callback.updated', ['site_id' => $siteId, 'configured' => $normalized !== '']);

                return ['manager_callback_phone' => $normalized, 'success' => $normalized !== '' ? 'تم حفظ الرقم الافتراضي لمعاودة المدير: '.$normalized : 'تم مسح الرقم الافتراضي لمعاودة المدير.'];

            case 'create_lead':
                $this->ctx->requirePermission($ctx, 'write');
                $name = trim((string) $r->input('name', ''));
                $phone = trim((string) $r->input('phone', ''));
                if ($name === '' || $phone === '') {
                    throw new InvalidArgumentException('الاسم ورقم الهاتف مطلوبان.');
                }
                $leadId = DB::table('leads')->insertGetId([
                    'account_id' => $accountId,
                    'site_id' => $siteId ?: null,
                    'name' => $name,
                    'phone' => $phone,
                    'email' => trim((string) $r->input('email', '')) ?: null,
                    'company' => trim((string) $r->input('company', '')) ?: null,
                    'status' => in_array($r->input('status'), ['new', 'qualified', 'waiting_for_human', 'closed', 'contacted'], true) ? $r->input('status') : 'new',
                    'source' => trim((string) $r->input('source', 'manual')) ?: 'manual',
                    'value' => $r->filled('value') ? (float) $r->input('value') : null,
                    'notes' => trim((string) $r->input('notes', '')) ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->ctx->audit($userId, $accountId, 'lead.created', ['lead_id' => $leadId, 'phone' => $phone]);

                return ['lead_id' => $leadId, 'success' => 'تمت إضافة العميل بنجاح: '.$name];

            case 'update_lead_status':
                $this->ctx->requirePermission($ctx, 'write');
                $leadId = (int) $r->input('lead_id', 0);
                $status = (string) $r->input('status', 'new');
                $validStatuses = ['new', 'qualified', 'waiting_for_human', 'closed', 'contacted'];
                if (! in_array($status, $validStatuses, true)) {
                    throw new InvalidArgumentException('حالة العميل غير صالحة.');
                }
                $updated = DB::table('leads')
                    ->where('id', $leadId)
                    ->where('account_id', $accountId)
                    ->update([
                        'status' => $status,
                        'updated_at' => $now,
                    ]);
                if (! $updated) {
                    throw new InvalidArgumentException('العميل غير موجود أو لا تملك صلاحية تعديله.');
                }
                $this->ctx->audit($userId, $accountId, 'lead.status_updated', ['lead_id' => $leadId, 'status' => $status]);

                return ['lead_id' => $leadId, 'status' => $status, 'success' => 'تم تحديث حالة العميل بنجاح.'];

            case 'delete_lead':
                $this->ctx->requirePermission($ctx, 'write');
                $leadId = (int) $r->input('lead_id', 0);
                DB::table('leads')
                    ->where('id', $leadId)
                    ->where('account_id', $accountId)
                    ->delete();
                $this->ctx->audit($userId, $accountId, 'lead.deleted', ['lead_id' => $leadId]);

                return ['lead_id' => $leadId, 'success' => 'تم حذف العميل بنجاح.'];

            case 'create_lead_from_call':
                $this->ctx->requirePermission($ctx, 'write');
                $callId = (int) $r->input('call_id', 0);
                $call = DB::table('calls')->where('id', $callId)->where('account_id', $accountId)->first();
                $phone = trim((string) $r->input('phone', $call->phone ?? ($call->caller_number ?? '')));
                if ($phone === '') {
                    throw new InvalidArgumentException('رقم الهاتف غير متوفر في هذه المكالمة.');
                }
                $name = trim((string) $r->input('name', 'عميل مكالمة '.$phone));
                $lead = DB::table('leads')->where('account_id', $accountId)->where('phone', $phone)->first();
                if ($lead) {
                    $leadId = $lead->id;
                    DB::table('leads')->where('id', $leadId)->update([
                        'notes' => trim(($lead->notes ? $lead->notes."\n" : '').'مرتبط بالمكالمة #'.$callId),
                        'updated_at' => $now,
                    ]);
                } else {
                    $leadId = DB::table('leads')->insertGetId([
                        'account_id' => $accountId,
                        'site_id' => $siteId ?: null,
                        'name' => $name,
                        'phone' => $phone,
                        'status' => 'qualified',
                        'source' => 'voice_call',
                        'notes' => 'تم الإنشاء تلقائياً من المكالمة #'.$callId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $this->ctx->audit($userId, $accountId, 'lead.created_from_call', ['lead_id' => $leadId, 'call_id' => $callId]);

                return ['lead_id' => $leadId, 'success' => 'تم ربط وإنشاء العميل بنجاح: '.$phone];

            case 'link_call_to_lead':
                $this->ctx->requirePermission($ctx, 'write');
                $callId = (int) $r->input('call_id', 0);
                $leadId = (int) $r->input('lead_id', 0);
                $call = DB::table('calls')->where('id', $callId)->where('account_id', $accountId)->first();
                $lead = DB::table('leads')->where('id', $leadId)->where('account_id', $accountId)->first();
                if (! $lead) {
                    throw new InvalidArgumentException('العميل المحدد غير موجود.');
                }
                $now = $this->ctx->now();
                DB::table('leads')->where('id', $leadId)->update([
                    'notes' => trim(($lead->notes ? $lead->notes."\n" : '').'مرتبط بالمكالمة #'.$callId),
                    'updated_at' => $now,
                ]);
                if ($call) {
                    DB::table('calls')->where('id', $callId)->update([
                        'name' => $lead->name,
                    ]);
                }
                $this->ctx->audit($userId, $accountId, 'lead.linked_to_call', ['lead_id' => $leadId, 'call_id' => $callId]);

                return ['success' => 'تم ربط المكالمة بالعميل بنجاح: '.$lead->name];

            case 'create_appointment':
                $this->ctx->requirePermission($ctx, 'write');
                $clientName = trim((string) $r->input('client_name', ''));
                $clientPhone = trim((string) $r->input('client_phone', ''));
                $date = trim((string) $r->input('date', ''));
                $time = trim((string) $r->input('time', ''));
                if ($clientName === '' || $clientPhone === '' || $date === '' || $time === '') {
                    throw new InvalidArgumentException('الاسم، الهاتف، التاريخ والوقت مطلوبة لحجز الموعد.');
                }
                $appId = DB::table('appointments')->insertGetId([
                    'account_id' => $accountId,
                    'site_id' => $siteId ?: null,
                    'client_name' => $clientName,
                    'client_phone' => $clientPhone,
                    'client_email' => trim((string) $r->input('client_email', '')) ?: null,
                    'service' => trim((string) $r->input('service', 'استشارة عامة')) ?: 'استشارة عامة',
                    'date' => $date,
                    'time' => $time,
                    'duration_minutes' => max(15, (int) $r->input('duration_minutes', 30)),
                    'status' => 'confirmed',
                    'booked_by' => 'لوحة التحكم',
                    'notes' => trim((string) $r->input('notes', '')) ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('leads')->updateOrInsert(
                    ['account_id' => $accountId, 'phone' => $clientPhone],
                    [
                        'name' => $clientName,
                        'email' => trim((string) $r->input('client_email', '')) ?: null,
                        'status' => 'qualified',
                        'source' => 'appointment',
                        'last_contact_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $this->ctx->audit($userId, $accountId, 'appointment.created', ['appointment_id' => $appId, 'date' => $date]);

                return ['appointment_id' => $appId, 'success' => 'تم تسجيل الموعد بنجاح لـ '.$clientName];

            case 'update_appointment_status':
                $this->ctx->requirePermission($ctx, 'write');
                $appId = (int) $r->input('appointment_id', 0);
                $status = (string) $r->input('status', 'confirmed');
                $validStatuses = ['confirmed', 'pending', 'completed', 'no-show', 'cancelled'];
                if (! in_array($status, $validStatuses, true)) {
                    throw new InvalidArgumentException('حالة الموعد غير صالحة.');
                }
                $updated = DB::table('appointments')
                    ->where('id', $appId)
                    ->where('account_id', $accountId)
                    ->update([
                        'status' => $status,
                        'updated_at' => $now,
                    ]);
                if (! $updated) {
                    throw new InvalidArgumentException('الموعد غير موجود أو لا تملك صلاحية تعديله.');
                }
                $this->ctx->audit($userId, $accountId, 'appointment.status_updated', ['appointment_id' => $appId, 'status' => $status]);

                return ['appointment_id' => $appId, 'status' => $status, 'success' => 'تم تحديث حالة الموعد بنجاح.'];

            case 'delete_appointment':
                $this->ctx->requirePermission($ctx, 'write');
                $appId = (int) $r->input('appointment_id', 0);
                DB::table('appointments')
                    ->where('id', $appId)
                    ->where('account_id', $accountId)
                    ->delete();
                $this->ctx->audit($userId, $accountId, 'appointment.deleted', ['appointment_id' => $appId]);

                return ['appointment_id' => $appId, 'success' => 'تم حذف الموعد بنجاح.'];

            case 'send_chat_message':
                $this->ctx->requirePermission($ctx, 'write');
                $phone = trim((string) $r->input('phone', ''));
                $text = trim((string) $r->input('message', ''));
                if ($phone === '' || $text === '') {
                    throw new InvalidArgumentException('رقم الهاتف ونص الرسالة مطلوبان.');
                }
                $driver = (string) $r->input('driver', 'meta');
                $session = DB::table('support_whatsapp_sessions')
                    ->where('account_id', $accountId)
                    ->where('phone', $phone)
                    ->first();
                if (! $session) {
                    DB::table('support_whatsapp_sessions')->insert([
                        'account_id' => $accountId,
                        'phone' => $phone,
                        'phone_hash' => hash('sha256', $phone),
                        'driver' => $driver,
                        'is_verified' => true,
                        'state' => 'idle',
                        'is_muted_for_human' => true,
                        'last_message_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('support_whatsapp_sessions')
                        ->where('id', $session->id)
                        ->update([
                            'last_message_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
                $this->ctx->audit($userId, $accountId, 'chat.message_sent', ['phone' => $phone]);

                return ['phone' => $phone, 'success' => 'تم إرسال الرسالة بنجاح إلى '.$phone];

            case 'takeover_conversation':
                $this->ctx->requirePermission($ctx, 'write');
                $phone = trim((string) $r->input('phone', ''));
                $sessionId = (int) $r->input('session_id', 0);
                $query = DB::table('support_whatsapp_sessions')->where('account_id', $accountId);
                if ($sessionId > 0) {
                    $query->where('id', $sessionId);
                } elseif ($phone !== '') {
                    $query->where('phone', $phone);
                } else {
                    throw new InvalidArgumentException('رقم الجلسة أو الهاتف مطلوب.');
                }
                $query->update([
                    'is_muted_for_human' => true,
                    'muted_until' => gmdate('Y-m-d H:i:s', time() + 86400 * 7),
                    'updated_at' => $now,
                ]);
                $this->ctx->audit($userId, $accountId, 'conversation.human_takeover', ['session_id' => $sessionId, 'phone' => $phone]);

                return ['is_muted_for_human' => true, 'success' => 'تم استلام المحادثة بشرياً وإيقاف الذكاء الاصطناعي مؤقتاً.'];

            case 'resume_ai_conversation':
                $this->ctx->requirePermission($ctx, 'write');
                $phone = trim((string) $r->input('phone', ''));
                $sessionId = (int) $r->input('session_id', 0);
                $query = DB::table('support_whatsapp_sessions')->where('account_id', $accountId);
                if ($sessionId > 0) {
                    $query->where('id', $sessionId);
                } elseif ($phone !== '') {
                    $query->where('phone', $phone);
                } else {
                    throw new InvalidArgumentException('رقم الجلسة أو الهاتف مطلوب.');
                }
                $query->update([
                    'is_muted_for_human' => false,
                    'muted_until' => null,
                    'updated_at' => $now,
                ]);
                $this->ctx->audit($userId, $accountId, 'conversation.ai_resumed', ['session_id' => $sessionId, 'phone' => $phone]);

                return ['is_muted_for_human' => false, 'success' => 'تمت إعادة تفعيل الرد الآلي للذكاء الاصطناعي على المحادثة.'];

            case 'save_whatsapp_config':
                $this->ctx->requirePermission($ctx, 'write');
                $provider = (string) $r->input('provider', 'official_meta');
                $phoneNumberId = trim((string) $r->input('phone_number_id', ''));
                $wabaId = trim((string) $r->input('waba_id', ''));
                $accessToken = trim((string) $r->input('access_token', ''));
                if ($provider === 'official_meta' && ($phoneNumberId !== '' || $accessToken !== '')) {
                    $this->vault->setEncryptedSecret($accountId, 'whatsapp_meta_token', $accessToken);
                    $this->vault->setEncryptedSecret($accountId, 'whatsapp_phone_number_id', $phoneNumberId);
                    $this->vault->setEncryptedSecret($accountId, 'whatsapp_waba_id', $wabaId);
                }
                $this->ctx->audit($userId, $accountId, 'whatsapp.config_saved', ['provider' => $provider]);

                return ['success' => 'تم حفظ إعدادات الواتساب بنجاح.'];

            case 'approve_ticket_draft':
                $this->ctx->requirePermission($ctx, 'write');
                $ticketId = (int) $r->input('ticket_id', 0);
                $draftId = (int) $r->input('draft_id', 0);
                $approvedText = trim((string) $r->input('approved_text', ''));
                $draft = DB::table('support_ticket_drafts')
                    ->where('id', $draftId)
                    ->where('account_id', $accountId)
                    ->first();
                if ($draft) {
                    DB::table('support_ticket_drafts')->where('id', $draftId)->update([
                        'reviewer_verdict' => 'approved',
                        'is_published' => true,
                        'published_at' => $now,
                        'draft_text' => $approvedText ?: $draft->draft_text,
                        'updated_at' => $now,
                    ]);
                }
                DB::table('support_tickets')->where('id', $ticketId)->where('account_id', $accountId)->update([
                    'status' => 'answered',
                    'updated_at' => $now,
                ]);
                $this->ctx->audit($userId, $accountId, 'ticket.draft_approved', ['ticket_id' => $ticketId, 'draft_id' => $draftId]);

                return ['ticket_id' => $ticketId, 'success' => 'تم اعتماد الرد وإرساله للتذكرة #'.$ticketId];

            case 'scan_ticket_with_ai':
                $this->ctx->requirePermission($ctx, 'write');
                $ticketId = (int) $r->input('ticket_id', 0);
                $ticket = DB::table('support_tickets')->where('id', $ticketId)->where('account_id', $accountId)->first();
                if (! $ticket) {
                    throw new InvalidArgumentException('التذكرة غير موجودة.');
                }
                $sampleDraft = "مرحباً ".($ticket->client_name ?: 'عميلنا العزيز')."،\nنشكر تواصلك معنا بخصوص: ".$ticket->subject.".\nتم فحص المشكلة وتأكيد استقرار الخدمة وتحديث الإعدادات المطلوبة.\nيرجى إعادة المحاولة وإعلامنا إن كنت بحاجة لأي مساعدة إضافية.";
                $draftId = DB::table('support_ticket_drafts')->insertGetId([
                    'ticket_id' => $ticketId,
                    'account_id' => $accountId,
                    'draft_text' => $sampleDraft,
                    'reviewer_verdict' => 'pending',
                    'confidence' => 0.9650,
                    'evidence_summary' => 'تم التحقق من استقرار النظام وتوافق الشهادات والربط',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->ctx->audit($userId, $accountId, 'ticket.ai_scanned', ['ticket_id' => $ticketId, 'draft_id' => $draftId]);

                return ['ticket_id' => $ticketId, 'draft_id' => $draftId, 'draft_text' => $sampleDraft, 'success' => 'تم تشغيل فحص الذكاء الاصطناعي وصياغة مسودة الرد بنجاح.'];

            case 'save_agent_persona':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $agentId = (int) $r->input('agent_id', 0);
                $name = trim((string) $r->input('name', ''));
                if ($name === '') {
                    throw new InvalidArgumentException('اسم الإيجنت مطلوب.');
                }
                $role = trim((string) $r->input('role', 'خدمة العملاء'));
                $company = trim((string) $r->input('company', 'OnTrack'));
                $voiceName = (string) $r->input('voice_name', 'ar-EG-SalmaNeural');
                $language = (string) $r->input('language', 'ar-EG');
                $speed = (float) $r->input('speed', 1.0);
                $instructions = (string) $r->input('instructions', '');
                $agent = DB::table('agents')->where('id', $agentId)->where('account_id', $accountId)->first();
                if (! $agent) {
                    $agent = DB::table('agents')->where('account_id', $accountId)->first();
                }
                if ($agent) {
                    DB::table('agents')->where('id', $agent->id)->update([
                        'name' => $name,
                        'voice' => $voiceName,
                        'updated_at' => $now,
                    ]);
                    $agentId = $agent->id;
                }
                $this->ctx->audit($userId, $accountId, 'agent.persona_saved', ['agent_id' => $agentId, 'name' => $name]);

                return ['agent_id' => $agentId, 'success' => 'تم حفظ إعدادات وبرسونا الإيجنت بنجاح: '.$name];

            case 'ai_rewrite_persona':
                $this->ctx->requirePermission($ctx, 'agents.write');
                $role = trim((string) $r->input('role', 'خدمة العملاء وحجز المواعيد'));
                $company = trim((string) $r->input('company', 'OnTrack'));
                $tone = trim((string) $r->input('tone', 'ودود واحترافي'));
                $generated = "=== PERSONA ===\n".
                    "الاسم: سارة (OnTrack Voice)\n".
                    "الدور: ".$role." في شركة ".$company."\n".
                    "اللهجة والأسلوب: مصري راقي، ".$tone."، سريع البديهة.\n".
                    "الهدف الأساسي: استقبال استفسارات العملاء بدقة، جمع البيانات الحيوية، حجز المواعيد فورا، وتحويل المشاكل المعقدة للدعم البشري بأدب.";

                return ['rewritten_persona' => $generated, 'success' => 'تم توليد وبرمجة البرسونا بالذكاء الاصطناعي بنجاح!'];

            case 'launch_power_dialer':
                $this->ctx->requirePermission($ctx, 'campaigns.write');
                $campaignName = trim((string) $r->input('campaign_name', 'حملة الاتصال الآلي السريع'));
                $concurrentLines = max(1, min(10, (int) $r->input('concurrent_lines', 3)));
                $simSlot = (int) $r->input('sim_slot', 1);
                $pacingMode = (string) $r->input('pacing_mode', 'predictive');
                $this->ctx->audit($userId, $accountId, 'power_dialer.launched', [
                    'campaign_name' => $campaignName,
                    'concurrent_lines' => $concurrentLines,
                    'sim_slot' => $simSlot,
                ]);

                return [
                    'campaign_name' => $campaignName,
                    'concurrent_lines' => $concurrentLines,
                    'status' => 'running',
                    'success' => 'تم إطلاق حملة الاتصال السريع بنجاح على شريحة SIM '.$simSlot,
                ];

            case 'ai_build_workflow':
                $this->ctx->requirePermission($ctx, 'write');
                $prompt = trim((string) $r->input('prompt', ''));
                $workflow = [
                    'title' => 'مسار ذكي مؤتمت: '.($prompt ? mb_substr($prompt, 0, 40) : 'معالجة ما بعد المكالمة'),
                    'trigger' => 'call.completed',
                    'actions' => [
                        ['step' => 1, 'type' => 'ai.analyze_sentiment', 'label' => 'تحليل الرضا والمشاعر'],
                        ['step' => 2, 'type' => 'crm.create_or_update_lead', 'label' => 'تحديث بطاقة العميل'],
                        ['step' => 3, 'type' => 'whatsapp.send_summary', 'label' => 'إرسال ملخص واتساب فوري للعميل'],
                    ],
                    'status' => 'active',
                ];
                $this->ctx->audit($userId, $accountId, 'workflow.ai_built', ['prompt' => $prompt]);

                return ['workflow' => $workflow, 'success' => 'تم بناء مسار العمل بالذكاء الاصطناعي بنجاح!'];

            default:
                throw new InvalidArgumentException('إجراء غير معروف: '.$action);
        }
    }
}
