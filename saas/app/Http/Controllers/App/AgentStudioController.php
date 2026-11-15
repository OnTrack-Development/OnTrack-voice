<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentRevision;
use App\Models\Project;
use App\Models\TenantMembership;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AgentStudioController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        $agents = Agent::where('tenant_id', $ws->tenant_id)->latest()->get();
        $projects = Project::where('workspace_id', $ws->id)->orderBy('name')->get();

        return view('app.agents.index', [
            'workspace' => $ws,
            'agents' => $agents,
            'projects' => $projects,
        ]);
    }

    public function create(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        return view('app.agents.form', [
            'workspace' => $ws,
            'agent' => new Agent(['status' => 'draft']),
            'projects' => Project::where('workspace_id', $ws->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        $data = $this->validated($request, $ws);

        $agent = Agent::create([
            'tenant_id' => $ws->tenant_id,
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'instructions' => $data['instructions'] ?? null,
            'persona' => $data['persona'] ?? null,
            'status' => 'draft',
        ]);

        $this->snapshotRevision($agent, $request->user()->id);

        return redirect()->route('app.agents.show', ['workspace' => $ws->slug, 'agent' => $agent->id])
            ->with('status', 'تم إنشاء الوكيل كمسودة.');
    }

    public function show(Request $request, string $workspace, Agent $agent)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($agent->tenant_id === $ws->tenant_id, 404);

        return view('app.agents.show', [
            'workspace' => $ws,
            'agent' => $agent,
            'projects' => Project::where('workspace_id', $ws->id)->orderBy('name')->get(),
            'revisions' => AgentRevision::where('tenant_id', $ws->tenant_id)->where('agent_id', $agent->id)->latest('version')->get(),
        ]);
    }

    public function update(Request $request, string $workspace, Agent $agent)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($agent->tenant_id === $ws->tenant_id, 404);

        $data = $this->validated($request, $ws);

        $agent->update([
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'instructions' => $data['instructions'] ?? null,
            'persona' => $data['persona'] ?? null,
        ]);

        $this->snapshotRevision($agent, $request->user()->id);

        return redirect()->route('app.agents.show', ['workspace' => $ws->slug, 'agent' => $agent->id])
            ->with('status', 'تم حفظ تعديلات الوكيل وأُنشئ إصدار جديد.');
    }

    public function publish(Request $request, string $workspace, Agent $agent)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($agent->tenant_id === $ws->tenant_id, 404);

        if (blank($agent->instructions)) {
            return back()->withErrors(['instructions' => 'لا يمكن نشر وكيل بدون تعليمات.']);
        }

        $agent->update(['status' => 'published', 'published_at' => now()]);

        return back()->with('status', 'تم نشر الوكيل.');
    }

    private function snapshotRevision(Agent $agent, int $userId): void
    {
        $next = AgentRevision::where('tenant_id', $agent->tenant_id)->where('agent_id', $agent->id)->max('version') + 1;
        AgentRevision::create([
            'tenant_id' => $agent->tenant_id,
            'agent_id' => $agent->id,
            'version' => $next,
            'name' => $agent->name,
            'kind' => $agent->kind,
            'instructions' => $agent->instructions,
            'persona' => $agent->persona,
            'created_by' => $userId,
            'change_notes' => 'حفظ/نشر تحديث على الوكيل',
        ]);
    }

    private function validated(Request $request, Workspace $ws): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', 'in:support,sales,booking,custom'],
            'project_id' => ['required', 'integer'],
            'persona' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:8000'],
        ], [], [
            'name' => 'اسم الوكيل',
            'kind' => 'نوع الوكيل',
            'project_id' => 'المشروع',
            'instructions' => 'التعليمات',
        ]);

        $ownsProject = Project::where('id', $data['project_id'])
            ->where('workspace_id', $ws->id)
            ->where('tenant_id', $ws->tenant_id)
            ->exists();

        abort_unless($ownsProject, 422, 'المشروع المحدد لا يتبع هذه المساحة.');

        return $data;
    }

    private function context(Request $request, string $workspace): array
    {
        $ws = Workspace::where('slug', $workspace)->firstOrFail();

        $membership = TenantMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $ws->tenant_id)
            ->where('status', 'active')
            ->first();

        abort_if(! $membership, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');

        return [$ws, $membership];
    }

    public function publicWebCall(Request $request, string $agent)
    {
        $agentModel = Agent::where('id', $agent)
            ->orWhere('name', $agent)
            ->first();

        if (! $agentModel && is_numeric($agent)) {
            $agentModel = Agent::find((int) $agent);
        }

        if (! $agentModel) {
            $agentModel = Agent::first();
        }

        abort_if(! $agentModel, 404, 'لم يتم العثور على الإيجنت المطلوب في قاعدة البيانات.');

        $shareToken = (string) $request->query('share', '');
        $wsEndpoint = 'wss://app.ontrackegy.com/ws-voice';

        return view('app.agents.web_call', [
            'agent' => $agentModel,
            'shareToken' => $shareToken,
            'wsEndpoint' => $wsEndpoint,
        ]);
    }

    public function createLiveSession(Request $request, string $agent)
    {
        $agentModel = Agent::where('id', $agent)
            ->orWhere('name', $agent)
            ->first();

        if (! $agentModel && is_numeric($agent)) {
            $agentModel = Agent::find((int) $agent);
        }

        if (! $agentModel) {
            $agentModel = Agent::first();
        }

        if (! $agentModel) {
            return response()->json([
                'ok' => false,
                'error' => 'لم يتم العثور على الإيجنت المحدد في قاعدة البيانات.',
            ], 404);
        }

        $endpoints = $this->resolveAllVoiceEndpoints();
        if (empty($endpoints)) {
            return response()->json([
                'ok' => false,
                'error' => 'مفاتيح Gemini API غير مهيأة في الخزنة المشفرة.',
            ], 500);
        }

        // Voice Mapping
        $rawVoice = strtolower((string) ($agentModel->voice ?? 'aoede'));
        $voiceName = 'Aoede';
        if (str_contains($rawVoice, 'puck') || str_contains($rawVoice, 'charon') || str_contains($rawVoice, 'fenrir')) {
            $voiceName = 'Puck';
        } elseif (str_contains($rawVoice, 'kore')) {
            $voiceName = 'Kore';
        } elseif (str_contains($rawVoice, 'aoede')) {
            $voiceName = 'Aoede';
        }

        $instructions = trim((string) ($agentModel->instructions ?? ''));
        $persona = trim((string) ($agentModel->persona ?? ''));

        $systemPrompt = "أنت المساعد الصوتي الذكي الرسمي: {$agentModel->name}.\n"
            . (!empty($persona) ? "الشخصية: {$persona}\n" : "الشخصية: ودود، لبق، سريع البديهة، وتتحدث باللهجة المصرية الطبيعية والواضحة.\n")
            . (!empty($instructions) ? "إرشادات العمل: {$instructions}\n" : "")
            . "قواعد المكالمة:\n"
            . "1. تحدث بطريقة طبيعية وانسيابية بنبرة صوت دافئة واحترافية.\n"
            . "2. استمع للعميل جيداً وأجب باختصار ودقة دون إطالة غير ضرورية.\n"
            . "3. التزم بالهوية الرسمية لمنصة OnTrack للحلول الصوتية والذكاء الاصطناعي.";

        $modelName = env('ONTRACK_GEMINI_LIVE_MODEL', 'gemini-2.5-flash-native-audio-preview-12-2025');
        if (!str_starts_with($modelName, 'models/')) {
            $modelName = 'models/' . $modelName;
        }

        $setup = [
            'model' => $modelName,
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voiceName,
                        ],
                    ],
                ],
            ],
            'inputAudioTranscription' => (object) [],
            'outputAudioTranscription' => (object) [],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
        ];

        return response()->json([
            'ok' => true,
            'agent_id' => $agentModel->id,
            'agent_name' => $agentModel->name,
            'voice_name' => $voiceName,
            'gemini_ws_url' => $endpoints[0]['ws_url'],
            'failover_endpoints' => $endpoints,
            'setup' => $setup,
        ]);
    }

    private function resolveAllVoiceEndpoints(): array
    {
        $endpoints = [];

        // 1. Check environment or config
        $envKey = trim((string) (config('ontrack.gemini_api_key') ?: env('ONTRACK_GEMINI_API_KEY')));
        if (!empty($envKey)) {
            $endpoints[] = [
                'id' => 'env',
                'label' => 'Primary Env Key',
                'ws_url' => 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?key=' . rawurlencode($envKey),
                'key_prefix' => substr($envKey, 0, 6) . '...',
            ];
        }

        // 2. Extract all active voice keys from SQLite console vault
        $vaultPaths = [
            '/home/appontrack/.ontrack-saas',
            storage_path('app/ontrack-vault'),
            base_path('../.ontrack-saas'),
        ];

        foreach ($vaultPaths as $base) {
            $keyFile = $base . '/console-vault.key';
            $dbFile = $base . '/ontrack-console.sqlite';
            if (file_exists($keyFile) && file_exists($dbFile)) {
                $vaultKey = @file_get_contents($keyFile);
                if (is_string($vaultKey) && strlen($vaultKey) === 32 && class_exists('SQLite3')) {
                    try {
                        $sqlite = new \SQLite3($dbFile, SQLITE3_OPEN_READONLY);
                        $res = $sqlite->query("SELECT id, label, secret_enc FROM ai_relay_entries WHERE scope = 'voice' AND enabled = 1 ORDER BY priority ASC, id ASC");
                        if ($res) {
                            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                                if (empty($row['secret_enc'])) {
                                    continue;
                                }
                                $raw = base64_decode($row['secret_enc'], true);
                                if ($raw === false || strlen($raw) < 28) {
                                    continue;
                                }
                                $decrypted = openssl_decrypt(
                                    substr($raw, 28),
                                    'aes-256-gcm',
                                    $vaultKey,
                                    OPENSSL_RAW_DATA,
                                    substr($raw, 0, 12),
                                    substr($raw, 12, 16)
                                );
                                if (!empty($decrypted)) {
                                    $endpoints[] = [
                                        'id' => (string) $row['id'],
                                        'label' => (string) ($row['label'] ?? 'Key #' . $row['id']),
                                        'ws_url' => 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?key=' . rawurlencode($decrypted),
                                        'key_prefix' => substr($decrypted, 0, 6) . '...',
                                    ];
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore and continue
                    }
                }
                if (!empty($endpoints)) {
                    break;
                }
            }
        }

        return $endpoints;
    }
}