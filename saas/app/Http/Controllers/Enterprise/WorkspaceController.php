<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Services\Automation\PostCallAutomationService;
use App\Services\Billing\BillingService;
use App\Services\Campaigns\CampaignDialerService;
use App\Services\Console\ConsoleContextService;
use App\Services\Mobile\CallLedgerService;
use App\Services\Voice\VoiceCatalogService;
use App\Services\Voice\VoiceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    public const VALID_TABS = [
        'overview', 'agents', 'voices', 'devices', 'calls', 'campaigns',
        'automations', 'billing', 'invoices', 'settings', 'whatsapp',
        'accounts', 'plans', 'domains', 'staff', 'integrations', 'releases',
        'reseller-tenants', 'reseller-quotas', 'platform-ai', 'platform-plans',
    ];

    public function __construct(
        private readonly ConsoleContextService $ctx,
        private readonly VoiceCatalogService $voices,
        private readonly VoiceSessionService $voiceSession,
        private readonly CallLedgerService $calls,
        private readonly CampaignDialerService $campaigns,
        private readonly PostCallAutomationService $automation,
        private readonly BillingService $billing,
    ) {}

    public function home(Request $request)
    {
        if (! Auth::check()) {
            if ($request->has('tab')) {
                return redirect()->guest(route('login'));
            }

            return view('enterprise.portal', [
                'theme' => session('iovoice_theme', 'dark'),
            ]);
        }

        $tab = (string) $request->query('tab', 'overview');

        return $this->renderWorkspace($request, $tab);
    }

    public function tab(Request $request, string $tab = 'overview')
    {
        return $this->renderWorkspace($request, $tab);
    }

    private function renderWorkspace(Request $request, string $tab)
    {
        $user = Auth::user();
        $siteRaw = $request->query('site_id') ?: $request->query('site');
        $siteParam = $siteRaw ? (int) $siteRaw : null;
        $ctx = $this->ctx->resolve($user, $siteParam);
        $this->ctx->requirePermission($ctx, 'read');

        if (in_array($tab, ['flows', 'answering'], true)) {
            $tab = 'agents';
        }
        if (! in_array($tab, self::VALID_TABS, true)) {
            $tab = 'overview';
        }
        if (in_array($tab, ['platform-ai', 'platform-plans', 'releases'], true) && ! $ctx['is_platform']) {
            abort(403, 'هذا القسم مخصص لإدارة المنصة فقط.');
        }
        if (in_array($tab, ['accounts', 'plans', 'reseller-tenants', 'reseller-quotas'], true) && ! $ctx['is_platform'] && ! $ctx['is_reseller']) {
            abort(403, 'هذا القسم مخصص لإدارة الوكلاء والمنصة فقط.');
        }
        // Unify /invoices into billing tab and /voices inside agents/studio or voices tab
        $accountId = (int) $ctx['account_id'];
        $siteId = (int) $ctx['site_id'];

        $data = $this->buildTabData($ctx, $tab, $request, $accountId, $siteId);

        return view('enterprise.layout', [
            'ctx' => $ctx,
            'activeTab' => $tab,
            'data' => $data,
        ]);
    }

    private function buildTabData(array $ctx, string $tab, Request $request, int $accountId, int $siteId): array
    {
        $onlineCutoff = gmdate('Y-m-d H:i:s', time() - 120);

        $devices = DB::table('device_bindings as d')
            ->leftJoin('agents as ag', 'ag.id', '=', 'd.agent_id')
            ->where('d.account_id', $accountId)
            ->where('d.status', 'active')
            ->orderBy('d.sim_slot')
            ->orderByDesc('d.id')
            ->select('d.*', 'ag.name as agent_name')
            ->get()
            ->map(function ($d) use ($onlineCutoff) {
                $arr = (array) $d;
                $arr['online'] = ! empty($d->last_seen_at) && $d->last_seen_at >= $onlineCutoff;

                return $arr;
            })->all();

        $agents = DB::table('agents')
            ->where('account_id', $accountId)
            ->where('status', '!=', 'archived')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($a) => (array) $a)
            ->all();

        $onlineSims = collect($devices)->where('online', true)->count();
        $subscription = $this->billing->activeSubscription($accountId);

        $base = [
            'devices' => $devices,
            'online_sims' => $onlineSims,
            'agents' => $agents,
            'subscription' => $subscription,
        ];

        return match ($tab) {
            'overview' => $base + $this->overviewData($accountId, $devices),
            'agents', 'voices' => $base + $this->agentsAndVoicesData($ctx, $request, $accountId, $siteId, $agents),
            'devices' => $base + $this->devicesData($accountId),
            'calls' => $base + $this->callsData($accountId, $request),
            'campaigns' => $base + $this->campaignsData($accountId),
            'automations' => $base + [
                'rules' => $this->automation->listRules($accountId),
                'webhook_logs' => $this->automation->listWebhookLogs($accountId),
            ],
            'billing', 'invoices' => $base + [
                'plans' => $this->billing->plansForAccount($ctx['account']),
                'invoices' => $this->billing->invoicesForAccount($accountId),
                'call_quota' => $this->ctx->siteLimit($accountId, 'call_quota'),
                'used_calls' => DB::table('calls')->where('account_id', $accountId)->where('is_demo', 0)->count(),
                'manual_pending' => ($ctx['is_platform'] && $ctx['view_mode'] === 'admin')
                    ? DB::table('billing_invoices')->where('status', 'pending')->orderByDesc('id')->limit(25)->get()->map(fn ($r) => (array) $r)->all()
                    : [],
            ],
            'settings', 'integrations', 'staff', 'domains', 'whatsapp' => $base + $this->settingsData($ctx, $accountId, $siteId),
            'accounts', 'plans', 'releases', 'reseller-tenants', 'reseller-quotas', 'platform-ai', 'platform-plans' => $base + $this->adminData($ctx),
            default => $base + $this->overviewData($accountId, $devices),
        };
    }

    private function overviewData(int $accountId, array $devices): array
    {
        $totalCalls = DB::table('calls')->where('account_id', $accountId)->where('is_demo', 0)->count();
        $tzCairo = new \DateTimeZone('Africa/Cairo');
        $tzUtc = new \DateTimeZone('UTC');
        $cairoTodayStart = (new \DateTimeImmutable('today', $tzCairo))->setTimezone($tzUtc)->format('Y-m-d H:i:s');
        $todayCalls = DB::table('calls')->where('account_id', $accountId)->where('is_demo', 0)->where('created_at', '>=', $cairoTodayStart)->count();
        $answeredCalls = DB::table('calls')->where('account_id', $accountId)->where('is_demo', 0)->whereIn('status', ['answered', 'completed', 'ended'])->count();
        $avgDuration = (int) round((float) (DB::table('calls')->where('account_id', $accountId)->where('is_demo', 0)->where('duration_seconds', '>', 0)->avg('duration_seconds') ?? 0));
        $avgRating = round((float) (DB::table('calls')->where('account_id', $accountId)->whereNotNull('ai_rating')->avg('ai_rating') ?? 0), 1);

        $recentCalls = $this->calls->filteredQuery($accountId, [])->limit(8)->get()->map(function ($c) {
            $arr = (array) $c;
            $arr['duration_formatted'] = $this->calls->formatDurationArabic((int) ($c->duration_seconds ?? 0));
            $arr['created_at_cairo'] = $this->calls->formatCairo((string) ($c->created_at ?? ''));
            $arr['effective_summary'] = trim((string) ($c->report_summary ?? '')) !== ''
                ? (string) $c->report_summary
                : trim((string) ($c->summary ?? ''));

            return $arr;
        })->all();

        $pendingHandoffs = DB::table('tenant_call_reports')
            ->where('account_id', $accountId)
            ->where('handoff_status', 'requested')
            ->count();

        // 7-day call volume sparkline
        $daily = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime("-{$i} days"));
            $count = DB::table('calls')->where('account_id', $accountId)->where('is_demo', 0)->where('created_at', 'like', $day.'%')->count();
            $daily[] = ['day' => gmdate('m/d', strtotime($day)), 'count' => $count];
        }

        return [
            'kpis' => [
                'total_calls' => $totalCalls,
                'today_calls' => $todayCalls,
                'answer_rate' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100) : 0,
                'avg_duration' => $this->calls->formatDurationArabic($avgDuration),
                'avg_rating' => $avgRating,
                'pending_handoffs' => $pendingHandoffs,
                'call_quota' => $this->ctx->siteLimit($accountId, 'call_quota'),
            ],
            'daily_chart' => $daily,
            'recent_calls' => $recentCalls,
        ];
    }

    private function agentsAndVoicesData(array $ctx, Request $request, int $accountId, int $siteId, array $agents): array
    {
        $selectedId = (int) ($request->query('agent_id') ?: $request->query('agent', 0));
        $selectedAgent = null;
        foreach ($agents as $a) {
            if ((int) $a['id'] === $selectedId) {
                $selectedAgent = $a;
                break;
            }
        }
        if (! $selectedAgent && ! empty($agents)) {
            $selectedAgent = $agents[0];
        }

        $policy = $selectedAgent
            ? $this->voiceSession->answerPolicyForAgent($accountId, (int) $selectedAgent['id'])
            : $this->voiceSession->defaultAnswerPolicy();

        $revisions = $selectedAgent
            ? DB::table('agent_flow_versions')
                ->where('account_id', $accountId)
                ->where('agent_id', $selectedAgent['id'])
                ->orderByDesc('revision')
                ->limit(10)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all()
            : [];

        $kbSources = DB::table('voice_knowledge_sources')
            ->where('account_id', $accountId)
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn ($k) => (array) $k)
            ->all();

        $connectors = DB::table('voice_data_connectors')
            ->where('account_id', $accountId)
            ->where('site_id', $siteId)
            ->where('status', 'verified')
            ->orderByDesc('id')
            ->get(['id', 'name', 'type'])
            ->map(fn ($c) => (array) $c)
            ->all();

        $voices = $this->voices->listVoicesForAccount($accountId, $ctx['kind'], [
            'provider' => $request->query('voice_provider'),
            'gender' => $request->query('voice_gender'),
            'q' => $request->query('voice_q'),
        ]);

        return [
            'selected_agent' => $selectedAgent,
            'policy' => $policy,
            'revisions' => $revisions,
            'kb_sources' => $kbSources,
            'connectors' => $connectors,
            'voices' => $voices,
            'io_state' => $this->voices->ioPremiumState(),
            'io_access' => $this->voices->ioPremiumAccessPolicy(),
            'can_create_agent' => $this->ctx->canCreate($accountId, 'agent'),
        ];
    }

    private function devicesData(int $accountId): array
    {
        $pendingPairings = DB::table('device_pairings')
            ->where('account_id', $accountId)
            ->whereIn('status', ['pending', 'pending_sim0', 'pending_sim1'])
            ->where('expires_at', '>', $this->ctx->now())
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => (array) $p)
            ->all();

        return [
            'pending_pairings' => $pendingPairings,
        ];
    }

    private function callsData(int $accountId, Request $request): array
    {
        $filters = [
            'q' => $request->query('q', ''),
            'status' => $request->query('status', ''),
            'outcome' => $request->query('outcome', ''),
            'sim' => $request->query('sim', ''),
            'from' => $request->query('from', ''),
            'to' => $request->query('to', ''),
        ];

        $paginator = $this->calls->filteredQuery($accountId, $filters)->paginate(20)->withQueryString();
        $rows = collect($paginator->items())->map(function ($r) {
            $arr = (array) $r;
            $arr['duration_formatted'] = $this->calls->formatDurationArabic((int) ($r->duration_seconds ?? 0));
            $arr['created_at_cairo'] = $this->calls->formatCairo((string) ($r->created_at ?? ''));
            $arr['effective_summary'] = trim((string) ($r->report_summary ?? '')) !== ''
                ? (string) $r->report_summary
                : trim((string) ($r->summary ?? ''));
            $arr['outcome_label'] = CallLedgerService::OUTCOMES[$r->report_outcome ?? 'unknown'] ?? ($r->report_outcome ?? 'غير محدد');

            return $arr;
        })->all();

        return [
            'calls' => $rows,
            'paginator' => $paginator,
            'filters' => $filters,
            'outcomes' => CallLedgerService::OUTCOMES,
            'handoff_statuses' => CallLedgerService::HANDOFF_STATUSES,
        ];
    }

    private function campaignsData(int $accountId): array
    {
        $contacts = DB::table('contacts')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->map(fn ($c) => (array) $c)
            ->all();

        return [
            'campaigns' => $this->campaigns->listCampaigns($accountId, 40),
            'queue_status' => $this->campaigns->campaignQueueStatusForAccount($accountId),
            'contacts' => $contacts,
            'dnc_count' => DB::table('dnc_suppressions')->where('account_id', $accountId)->count(),
        ];
    }

    private function settingsData(array $ctx, int $accountId, int $siteId): array
    {
        return [
            'site' => (array) (DB::table('sites')->where('id', $siteId)->where('account_id', $accountId)->first() ?: []),
            'profile' => (array) (DB::table('customer_profiles')->where('account_id', $accountId)->first() ?: []),
            'api_keys' => DB::table('api_keys')->where('account_id', $accountId)->orderByDesc('id')->get()->map(fn ($r) => (array) $r)->all(),
            'integrations' => DB::table('integrations')->where('account_id', $accountId)->orderByDesc('id')->get()->map(fn ($r) => (array) $r)->all(),
            'relay_entries' => DB::table('ai_relay_entries')->where('account_id', $accountId)->orderBy('scope')->orderBy('priority')->get()->map(fn ($r) => (array) $r)->all(),
            'staff_members' => DB::table('memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.account_id', $accountId)->select('m.*', 'u.name', 'u.email', 'u.status as user_status')->get()->map(fn ($r) => (array) $r)->all(),
            'invitations' => DB::table('staff_invitations')->where('account_id', $accountId)->orderByDesc('id')->limit(20)->get()->map(fn ($r) => (array) $r)->all(),
            'domains' => DB::table('domains')->where('account_id', $accountId)->orderByDesc('id')->get()->map(fn ($r) => (array) $r)->all(),
            'wa_connections' => DB::table('wa_connections')->where('account_id', $accountId)->orderByDesc('id')->get()->map(fn ($r) => (array) $r)->all(),
            'escalation_contacts' => DB::table('human_escalation_contacts')->where('account_id', $accountId)->orderByDesc('id')->get()->map(fn ($r) => (array) $r)->all(),
            'can_create_site' => $this->ctx->canCreate($accountId, 'site'),
        ];
    }

    private function adminData(array $ctx): array
    {
        if (! $ctx['is_platform'] && ! $ctx['is_reseller']) {
            return [];
        }

        $acctQuery = DB::table('accounts')->orderByDesc('id');
        if ($ctx['is_reseller']) {
            $acctQuery->where('parent_id', $ctx['account_id']);
        }

        $accounts = $acctQuery->limit(100)->get()->map(function ($a) {
            $arr = (array) $a;
            $sub = $this->billing->activeSubscription((int) $a->id);
            $arr['active_plan_name'] = $sub['plan_name'] ?? 'بدون باقة نشطة';
            $arr['active_call_quota'] = (int) ($sub['call_quota'] ?? 0);

            return $arr;
        })->all();

        $planQuery = DB::table('plans')->orderBy('kind')->orderBy('price_minor');
        if ($ctx['is_reseller']) {
            $planQuery->where('kind', 'user');
        }
        $plans = $planQuery->get()->map(fn ($p) => (array) $p)->all();

        $data = [
            'accounts_list' => $accounts,
            'all_plans' => $plans,
        ];

        if ($ctx['is_platform']) {
            $data['platform_relay_entries'] = DB::table('ai_relay_entries')
                ->where('account_id', $ctx['account_id'])
                ->orderBy('scope')
                ->orderBy('priority')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
            $data['manual_pending'] = DB::table('billing_invoices as i')
                ->leftJoin('accounts as a', 'a.id', '=', 'i.account_id')
                ->where('i.status', 'pending')
                ->orderByDesc('i.id')
                ->limit(30)
                ->get(['i.*', 'a.name as account_name'])
                ->map(fn ($r) => (array) $r)
                ->all();
            $data['global_voices'] = $this->voices->listVoicesForAccount((int) $ctx['account_id'], 'platform', []);
            $data['io_state'] = $this->voices->ioPremiumState();
            $data['io_access'] = $this->voices->ioPremiumAccessPolicy();
        }

        return $data;
    }

    public function callDetailJson(int $id)
    {
        $user = Auth::user();
        $ctx = $this->ctx->resolve($user);
        $this->ctx->requirePermission($ctx, 'read');

        $detail = $this->calls->callDetail((int) $ctx['account_id'], $id);

        return response()->json(['ok' => true] + $detail);
    }

    public function campaignQueueJson(Request $request)
    {
        $user = Auth::user();
        $ctx = $this->ctx->resolve($user);
        $this->ctx->requirePermission($ctx, 'read');
        $cmpId = $request->query('campaign_id');

        $queue = $this->campaigns->campaignQueueStatusForAccount(
            (int) $ctx['account_id'],
            is_string($cmpId) && trim($cmpId) !== '' ? trim($cmpId) : null
        );

        return response()->json(['ok' => true, 'queue' => $queue]);
    }

    public function exportCallsCsv(Request $request)
    {
        $user = Auth::user();
        $ctx = $this->ctx->resolve($user);
        $this->ctx->requirePermission($ctx, 'read');

        $csv = $this->calls->buildCsvString((int) $ctx['account_id'], $request->query());

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="iovoice-calls-'.gmdate('Ymd').'.csv"',
        ]);
    }

    public function printInvoice(int $id)
    {
        $user = Auth::user();
        $ctx = $this->ctx->resolve($user);
        $inv = DB::table('billing_invoices')
            ->where('id', $id)
            ->where(function ($q) use ($ctx): void {
                $q->where('account_id', $ctx['account_id']);
                if ($ctx['is_platform']) {
                    $q->orWhereRaw('1=1');
                }
            })
            ->first();

        abort_if(! $inv, 404);

        return view('enterprise.invoice_print', ['invoice' => (array) $inv, 'ctx' => $ctx]);
    }
}
