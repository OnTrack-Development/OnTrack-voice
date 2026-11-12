<?php

namespace App\Services\Mobile;

use App\Services\Automation\PostCallAutomationService;
use App\Services\Console\ConsoleContextService;
use App\Services\Security\VaultService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CallLedgerService
{
    public const OUTCOMES = [
        'unknown' => 'غير محدد',
        'answered_completed' => 'تم الرد واكتملت',
        'booked' => 'حجز / موعد',
        'lead_qualified' => 'عميل مهتم',
        'interested' => 'مهتم بالتواصل',
        'satisfied' => 'عميل راضٍ',
        'unsatisfied' => 'عميل غير راضٍ',
        'follow_up' => 'متابعة لاحقة',
        'not_interested' => 'غير مهتم',
        'do_not_call' => 'رفض التواصل (DNC)',
        'callback_requested' => 'طلب معاودة اتصال',
        'handoff_needed' => 'يحتاج تدخل بشري',
        'no_answer' => 'لم يُرد',
        'busy' => 'مشغول',
        'rejected' => 'مرفوضة',
        'dnc' => 'رفض التواصل (DNC)',
    ];

    public const HANDOFF_STATUSES = [
        'not_requested' => 'لا يحتاج متابعة',
        'requested' => 'مطلوب متابعة بشرية',
        'followed_up' => 'تمت المتابعة',
        'completed' => 'مكتمل (تأكيد بشري)',
    ];

    public function __construct(
        private readonly VaultService $vault,
        private readonly ConsoleContextService $ctx,
        private readonly PostCallAutomationService $automation,
    ) {}

    public function formatDurationArabic(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $mins = intdiv($seconds, 60);
        $rem = $seconds % 60;
        if ($mins === 0) {
            return $rem.' ثانية';
        }
        if ($rem === 0) {
            return $mins.' دقيقة';
        }

        return $mins.' دقيقة و'.$rem.' ثانية';
    }

    public function safeCsvCell(?string $val): string
    {
        $clean = str_replace(["\r\n", "\r", "\n"], ' ', trim((string) $val));
        if ($clean !== '' && in_array($clean[0], ['=', '+', '-', '@', "\t"], true)) {
            return "'".$clean;
        }

        return $clean;
    }

    public function filteredQuery(int $accountId, array $filters = [], bool $isDemo = false)
    {
        $q = DB::table('calls as c')
            ->leftJoin('tenant_call_reports as r', function ($join): void {
                $join->on('r.device_binding_id', '=', 'c.device_binding_id')
                    ->on('r.call_id', '=', 'c.external_call_id');
            })
            ->leftJoin('agents as ag', 'ag.id', '=', 'c.agent_id')
            ->leftJoin('device_bindings as d', 'd.id', '=', 'c.device_binding_id')
            ->where('c.account_id', $accountId);

        if (! $isDemo) {
            $q->where('c.is_demo', 0);
        }

        if (! empty($filters['q'])) {
            $needle = '%'.trim((string) $filters['q']).'%';
            $q->where(function ($sub) use ($needle): void {
                $sub->where('c.phone', 'like', $needle)
                    ->orWhere('c.name', 'like', $needle)
                    ->orWhere('c.agent_name', 'like', $needle)
                    ->orWhere('r.summary', 'like', $needle)
                    ->orWhere('r.customer_notes', 'like', $needle);
            });
        }

        if (! empty($filters['status'])) {
            $q->where('c.status', (string) $filters['status']);
        }
        if (! empty($filters['outcome'])) {
            $q->where('r.outcome', (string) $filters['outcome']);
        }
        if (isset($filters['sim']) && $filters['sim'] !== '' && in_array((string) $filters['sim'], ['0', '1'], true)) {
            $q->where('c.sim_slot', (int) $filters['sim']);
        }
        if (! empty($filters['site_id'])) {
            $q->where('c.site_id', (int) $filters['site_id']);
        }
        if (! empty($filters['from'])) {
            $q->where('c.created_at', '>=', (string) $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $q->where('c.created_at', '<=', (string) $filters['to'].' 23:59:59');
        }

        return $q->select([
            'c.*',
            'r.summary as report_summary',
            'r.customer_notes as report_notes',
            'r.outcome as report_outcome',
            'r.handoff_status',
            'r.handoff_reason',
            'r.source as report_source',
            DB::raw('COALESCE(c.agent_name, ag.name) as resolved_agent_name'),
            'd.device_name',
        ])->orderByDesc('c.id');
    }

    public function callDetail(int $accountId, int $callRecordId): array
    {
        $call = DB::table('calls as c')
            ->leftJoin('tenant_call_reports as r', function ($join): void {
                $join->on('r.device_binding_id', '=', 'c.device_binding_id')
                    ->on('r.call_id', '=', 'c.external_call_id');
            })
            ->leftJoin('agents as ag', 'ag.id', '=', 'c.agent_id')
            ->leftJoin('device_bindings as d', 'd.id', '=', 'c.device_binding_id')
            ->where('c.account_id', $accountId)
            ->where('c.id', $callRecordId)
            ->select([
                'c.*',
                'r.summary as report_summary',
                'r.customer_notes as report_notes',
                'r.outcome as report_outcome',
                'r.handoff_status',
                'r.handoff_reason',
                'r.source as report_source',
                'r.revision as report_revision',
                DB::raw('COALESCE(c.agent_name, ag.name) as resolved_agent_name'),
                'd.device_name',
            ])
            ->first();

        if (! $call) {
            throw new InvalidArgumentException('المكالمة المطلوبة غير موجودة أو لا تتبع هذا الحساب.');
        }

        $transcript = null;
        $timeline = [];

        if (! empty($call->device_binding_id) && ! empty($call->external_call_id)) {
            $tRow = DB::table('tenant_call_transcripts')
                ->where('account_id', $accountId)
                ->where('device_binding_id', $call->device_binding_id)
                ->where('call_id', $call->external_call_id)
                ->first();

            if ($tRow && ! empty($tRow->transcript)) {
                $transcript = $this->vault->unsealTranscript((string) $tRow->transcript);
            }

            $timeline = DB::table('voice_call_timeline')
                ->where('account_id', $accountId)
                ->where('device_binding_id', $call->device_binding_id)
                ->where('call_id', $call->external_call_id)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        $callArr = (array) $call;
        $callArr['duration_formatted'] = $this->formatDurationArabic((int) ($call->duration_seconds ?? 0));
        $callArr['outcome_label'] = self::OUTCOMES[$call->report_outcome ?? 'unknown'] ?? ($call->report_outcome ?? 'غير محدد');
        $callArr['handoff_label'] = self::HANDOFF_STATUSES[$call->handoff_status ?? 'not_requested'] ?? ($call->handoff_status ?? '');

        return [
            'call' => $callArr,
            'transcript' => $transcript,
            'timeline' => $timeline,
        ];
    }

    public function saveReport(int $accountId, int $siteId, int $deviceBindingId, string $callId, array $input, string $source = 'manual', ?callable $webhookTransport = null): array
    {
        if (! in_array($source, ['manual', 'device', 'device_ai'], true)) {
            throw new InvalidArgumentException('invalid_report_source');
        }
        $isDevice = in_array($source, ['device', 'device_ai'], true);

        $call = DB::table('calls')
            ->where('account_id', $accountId)
            ->where('device_binding_id', $deviceBindingId)
            ->where('external_call_id', $callId)
            ->first();

        if (! $call) {
            throw new InvalidArgumentException($isDevice ? 'call_not_found_for_sim' : 'تعذر العثور على سجل المكالمة المطلوب.');
        }

        $outcomeRaw = (string) ($input['outcome'] ?? 'unknown');
        if (! array_key_exists($outcomeRaw, self::OUTCOMES)) {
            if ($isDevice) {
                throw new InvalidArgumentException('invalid_call_report_state');
            }
            $outcomeRaw = 'unknown';
        }
        $outcome = $outcomeRaw;

        $handoffRaw = (string) ($input['handoff_status'] ?? 'not_requested');
        if (! array_key_exists($handoffRaw, self::HANDOFF_STATUSES)) {
            if ($isDevice) {
                throw new InvalidArgumentException('invalid_call_report_state');
            }
            $handoffRaw = 'not_requested';
        }
        $handoffStatus = $handoffRaw;

        if ($isDevice && $handoffStatus === 'completed') {
            throw new InvalidArgumentException('human_handoff_requires_confirmed_staff_action');
        }

        $summary = trim(mb_substr((string) ($input['summary'] ?? ''), 0, 4000));
        $notes = trim(mb_substr((string) ($input['customer_notes'] ?? ''), 0, 4000));
        $reason = trim(mb_substr((string) ($input['handoff_reason'] ?? ''), 0, 2000));

        if ($isDevice && $handoffStatus !== 'not_requested' && $reason === '') {
            throw new InvalidArgumentException('handoff_reason_required');
        }

        $existing = DB::table('tenant_call_reports')
            ->where('device_binding_id', $deviceBindingId)
            ->where('call_id', $callId)
            ->first();

        if ($reason === '' && $existing && trim((string) $existing->handoff_reason) !== '') {
            $reason = trim((string) $existing->handoff_reason);
        }

        if ($handoffStatus === 'completed' && (! $existing || $existing->handoff_status !== 'completed')) {
            $acknowledged = ($input['staff_acknowledged'] ?? false) === true;
            if ($source !== 'manual' || ! $existing || $existing->handoff_status !== 'requested' || ! $acknowledged) {
                throw new InvalidArgumentException('human_handoff_explicit_staff_confirmation_required');
            }
        }

        if ($isDevice && $existing && $existing->handoff_status === 'requested' && $handoffStatus === 'not_requested') {
            throw new InvalidArgumentException('human_handoff_request_cannot_be_erased_by_device');
        }
        if ($isDevice && $existing && $existing->handoff_status === 'completed' && $handoffStatus !== 'completed') {
            throw new InvalidArgumentException('handoff_cannot_be_downgraded');
        }

        if ($summary === '' && $notes === '' && $handoffStatus === 'not_requested' && $outcome === 'unknown') {
            throw new InvalidArgumentException('empty_call_report');
        }

        $now = $this->ctx->now();

        if (
            $existing
            && (string) $existing->summary === $summary
            && (string) $existing->customer_notes === $notes
            && (string) $existing->outcome === $outcome
            && (string) $existing->handoff_status === $handoffStatus
            && (string) $existing->handoff_reason === $reason
        ) {
            return [
                'ok' => true,
                'saved' => true,
                'duplicate' => true,
                'revision' => (int) $existing->revision,
                'outcome' => $outcome,
                'report' => (array) $existing,
            ];
        }

        $rev = DB::transaction(function () use (
            $existing,
            $accountId,
            $siteId,
            $deviceBindingId,
            $callId,
            $summary,
            $notes,
            $outcome,
            $handoffStatus,
            $reason,
            $source,
            $call,
            $input,
            $now
        ): int {
            if ($existing) {
                $rev = ((int) $existing->revision) + 1;
                DB::table('tenant_call_reports')
                    ->where('device_binding_id', $deviceBindingId)
                    ->where('call_id', $callId)
                    ->update([
                        'summary' => $summary,
                        'customer_notes' => $notes,
                        'outcome' => $outcome,
                        'handoff_status' => $handoffStatus,
                        'handoff_reason' => $reason,
                        'source' => $source,
                        'revision' => $rev,
                        'updated_at' => $now,
                    ]);
            } else {
                $rev = 1;
                DB::table('tenant_call_reports')->insert([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'device_binding_id' => $deviceBindingId,
                    'call_id' => $callId,
                    'summary' => $summary,
                    'customer_notes' => $notes,
                    'outcome' => $outcome,
                    'handoff_status' => $handoffStatus,
                    'handoff_reason' => $reason,
                    'source' => $source,
                    'revision' => $rev,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Update AI quality/gender/mood fields on call if supplied
            $callUpdates = [];
            if (isset($input['caller_gender']) && in_array($input['caller_gender'], ['male', 'female', 'unknown'], true)) {
                $callUpdates['caller_gender'] = $input['caller_gender'];
            }
            if (isset($input['ai_rating']) && is_numeric($input['ai_rating'])) {
                $callUpdates['ai_rating'] = max(1, min(5, (int) $input['ai_rating']));
            }
            if (isset($input['ai_mood']) && trim((string) $input['ai_mood']) !== '') {
                $callUpdates['ai_mood'] = trim(mb_substr((string) $input['ai_mood'], 0, 30));
            }
            if (! empty($callUpdates)) {
                DB::table('calls')->where('id', $call->id)->update($callUpdates);
            }

            // DNC propagation
            if (in_array($outcome, ['dnc', 'do_not_call'], true) && ! empty($call->phone)) {
                $phoneKey = $this->ctx->dncPhoneKey((string) $call->phone);
                if ($phoneKey !== '') {
                    DB::table('dnc_suppressions')->insertOrIgnore([
                        'account_id' => $accountId,
                        'phone_key' => $phoneKey,
                        'source' => 'call_report',
                        'created_at' => $now,
                    ]);
                }
                DB::table('contacts')
                    ->where('account_id', $accountId)
                    ->where('phone', (string) $call->phone)
                    ->update(['dnc' => 1]);
            }

            return $rev;
        });

        $freshReport = DB::table('tenant_call_reports')
            ->where('device_binding_id', $deviceBindingId)
            ->where('call_id', $callId)
            ->first();

        $notification = $this->scheduleHumanEscalation([
            'id' => $deviceBindingId,
            'account_id' => $accountId,
            'site_id' => $siteId,
            'sim_slot' => (int) ($call->sim_slot ?? 0),
        ], $callId);

        // Fire Post-Call Automations
        $this->automation->evaluateAndExecute($accountId, 'report.saved', [
            'call_id' => $callId,
            'phone' => (string) ($call->phone ?? ''),
            'name' => (string) ($call->name ?? ''),
            'agent_id' => (int) ($call->agent_id ?? 0),
            'outcome' => $outcome,
            'handoff_status' => $handoffStatus,
            'summary' => $summary,
            'ai_mood' => (string) ($input['ai_mood'] ?? $call->ai_mood ?? ''),
            'ai_rating' => (int) ($input['ai_rating'] ?? $call->ai_rating ?? 0),
        ], $webhookTransport);

        return [
            'ok' => true,
            'saved' => true,
            'duplicate' => false,
            'revision' => $rev,
            'outcome' => $outcome,
            'report' => $freshReport ? (array) $freshReport : null,
            'manager_notification' => $notification,
        ];
    }

    public function scheduleHumanEscalation(array $binding, string $callId): array
    {
        $accountId = (int) $binding['account_id'];
        $siteId = (int) $binding['site_id'];
        $deviceId = (int) $binding['id'];
        $simSlot = (int) ($binding['sim_slot'] ?? 0);

        $row = DB::table('calls as c')
            ->join('tenant_call_reports as r', function ($join): void {
                $join->on('r.account_id', '=', 'c.account_id')
                    ->on('r.site_id', '=', 'c.site_id')
                    ->on('r.device_binding_id', '=', 'c.device_binding_id')
                    ->on('r.call_id', '=', 'c.external_call_id');
            })
            ->leftJoin('human_escalation_routes as ar', function ($join): void {
                $join->on('ar.agent_id', '=', 'c.agent_id')
                    ->on('ar.account_id', '=', 'c.account_id')
                    ->on('ar.site_id', '=', 'c.site_id');
            })
            ->leftJoin('human_escalation_contacts as co', function ($join): void {
                $join->on('co.id', '=', 'ar.contact_id')
                    ->on('co.account_id', '=', 'c.account_id');
            })
            ->where('c.account_id', $accountId)
            ->where('c.site_id', $siteId)
            ->where('c.device_binding_id', $deviceId)
            ->where('c.sim_slot', $simSlot)
            ->where('c.external_call_id', $callId)
            ->select([
                'c.ended_at',
                'c.created_at as call_started_at',
                'c.status',
                'c.agent_id',
                'r.handoff_status',
                'r.handoff_reason',
                'r.source',
                'ar.contact_id',
                'ar.enabled',
                'ar.delay_seconds',
                'ar.updated_at as route_updated_at',
                'co.status as recipient_status',
            ])
            ->first();

        if (! $row) {
            return ['queued' => false, 'reason' => 'no_eligible_report'];
        }
        if (! in_array($row->status, ['ended', 'completed'], true) || ! $row->ended_at) {
            return ['queued' => false, 'reason' => 'call_still_open'];
        }
        if ($row->handoff_status !== 'requested') {
            return ['queued' => false, 'reason' => 'no_human_request'];
        }
        if ($row->source === 'device_handoff') {
            return ['queued' => false, 'reason' => 'handset_takeover_not_customer_request'];
        }
        if ((int) ($row->enabled ?? 0) !== 1 || ! $row->contact_id || $row->recipient_status !== 'active') {
            return ['queued' => false, 'reason' => 'staff_routing_not_configured'];
        }
        if (! empty($row->route_updated_at) && (string) $row->call_started_at < (string) $row->route_updated_at) {
            return ['queued' => false, 'reason' => 'staff_route_configured_after_call'];
        }

        $existing = DB::table('human_escalation_jobs')
            ->where('device_binding_id', $deviceId)
            ->where('call_id', $callId)
            ->first();
        if ($existing) {
            return ['queued' => true, 'duplicate' => true, 'status' => (string) $existing->status];
        }

        $ended = strtotime((string) $row->ended_at.' UTC');
        $due = max(time(), ($ended ?: time()) + max(10, min(120, (int) ($row->delay_seconds ?? 10))));

        try {
            $id = (int) DB::table('human_escalation_jobs')->insertGetId([
                'account_id' => $accountId,
                'site_id' => $siteId,
                'device_binding_id' => $deviceId,
                'sim_slot' => $simSlot,
                'call_id' => $callId,
                'contact_id' => (int) $row->contact_id,
                'due_epoch' => $due,
                'status' => 'pending',
                'created_at' => $this->ctx->now(),
            ]);

            return ['queued' => true, 'duplicate' => false, 'id' => $id, 'ready_after_seconds' => max(0, $due - time())];
        } catch (QueryException $e) {
            $dup = DB::table('human_escalation_jobs')
                ->where('device_binding_id', $deviceId)
                ->where('call_id', $callId)
                ->first();
            if ($dup) {
                return ['queued' => true, 'duplicate' => true, 'status' => (string) $dup->status];
            }
            throw $e;
        }
    }

    public function updateUsageOnCallPhase(array $binding, string $callId, string $phase): bool
    {
        if (! in_array($phase, ['active', 'ended'], true)) {
            return false;
        }
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        $d = (int) $binding['id'];
        $slot = (int) $binding['sim_slot'];
        $now = $this->ctx->now();

        if ($phase === 'active') {
            $ev = DB::table('voice_call_answer_evidence')
                ->where('device_binding_id', $d)
                ->where('call_id', $callId)
                ->first();
            if (! $ev) {
                DB::table('voice_call_answer_evidence')->insertOrIgnore([
                    'account_id' => $a,
                    'site_id' => $s,
                    'device_binding_id' => $d,
                    'sim_slot' => $slot,
                    'call_id' => $callId,
                    'observed_at' => $now,
                ]);
                $ev = DB::table('voice_call_answer_evidence')
                    ->where('device_binding_id', $d)
                    ->where('call_id', $callId)
                    ->first();
            }
            if ($ev && ((int) $ev->account_id !== $a || (int) $ev->site_id !== $s || (int) $ev->sim_slot !== $slot)) {
                throw new RuntimeException('voice_usage_evidence_scope_mismatch');
            }
        }

        $row = DB::table('calls')
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('device_binding_id', $d)
            ->where('sim_slot', $slot)
            ->where('external_call_id', $callId)
            ->first();

        if (! $row || ! in_array($row->status, ['ended', 'completed'], true) || (int) $row->duration_seconds < 1 || (int) $row->is_demo === 1) {
            return false;
        }

        $hasEv = DB::table('voice_call_answer_evidence')
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('device_binding_id', $d)
            ->where('sim_slot', $slot)
            ->where('call_id', $callId)
            ->exists();

        if (! $hasEv) {
            return false;
        }

        $ref = 'voice-'.hash('sha256', $a.':'.$s.':'.$d.':'.$slot.':'.$callId);
        if (DB::table('usage_ledger')->where('reference', $ref)->exists()) {
            return false;
        }

        $inserted = DB::table('usage_ledger')->insertOrIgnore([
            'account_id' => $a,
            'kind' => 'connected_call',
            'units' => 1,
            'reference' => $ref,
            'created_at' => $now,
        ]);

        return $inserted === 1;
    }

    public function storeTimelineEvent(array $binding, array $payload): array
    {
        $callId = (string) ($payload['call_id'] ?? '');
        $eventId = (string) ($payload['event_id'] ?? '');
        $speaker = (string) ($payload['speaker'] ?? '');
        $event = (string) ($payload['event_type'] ?? '');
        $content = $payload['text'] ?? null;

        if (
            preg_match('/^[A-Za-z0-9_-]{8,64}$/D', $callId) !== 1
            || preg_match('/^[A-Za-z0-9_-]{8,64}$/D', $eventId) !== 1
            || ! in_array($speaker, ['caller', 'agent', 'unknown'], true)
            || ! in_array($event, ['transcript', 'spoken_text', 'notice'], true)
            || ! is_string($content)
            || trim($content) === ''
            || mb_strlen($content) > 1200
            || ! mb_check_encoding($content, 'UTF-8')
        ) {
            throw new InvalidArgumentException('timeline_event_invalid');
        }
        if ($event === 'spoken_text' && $speaker !== 'agent') {
            throw new InvalidArgumentException('timeline_spoken_text_must_be_agent');
        }

        $accountId = (int) $binding['account_id'];
        $siteId = (int) $binding['site_id'];
        $deviceId = (int) $binding['id'];
        $simSlot = (int) $binding['sim_slot'];

        $call = DB::table('calls')
            ->where('account_id', $accountId)
            ->where('site_id', $siteId)
            ->where('device_binding_id', $deviceId)
            ->where('sim_slot', $simSlot)
            ->where('external_call_id', $callId)
            ->first();

        if (! $call) {
            throw new InvalidArgumentException('timeline_call_not_owned');
        }

        $at = (string) ($payload['occurred_at'] ?? '');
        if (preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $at) !== 1) {
            throw new InvalidArgumentException('timeline_timestamp_invalid');
        }

        $trimmed = trim($content);
        $prev = DB::table('voice_call_timeline')
            ->where('device_binding_id', $deviceId)
            ->where('call_id', $callId)
            ->where('event_id', $eventId)
            ->first();

        if ($prev) {
            if (
                (int) $prev->account_id !== $accountId
                || (int) $prev->site_id !== $siteId
                || (int) $prev->sim_slot !== $simSlot
                || $prev->speaker !== $speaker
                || $prev->event_type !== $event
                || $prev->content !== $trimmed
                || $prev->occurred_at !== $at
            ) {
                throw new RuntimeException('timeline_replay_changed');
            }

            return ['saved' => true, 'duplicate' => true];
        }

        $stamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $at, new DateTimeZone('UTC'));
        if (! $stamp || $stamp->format('Y-m-d H:i:s') !== $at || abs(time() - $stamp->getTimestamp()) > 24 * 3600) {
            throw new InvalidArgumentException('timeline_timestamp_invalid');
        }

        DB::table('voice_call_timeline')->insert([
            'account_id' => $accountId,
            'site_id' => $siteId,
            'device_binding_id' => $deviceId,
            'sim_slot' => $simSlot,
            'call_id' => $callId,
            'event_id' => $eventId,
            'speaker' => $speaker,
            'event_type' => $event,
            'content' => $trimmed,
            'origin' => 'device_reported',
            'occurred_at' => $at,
            'received_at' => $this->ctx->now(),
        ]);

        return ['saved' => true, 'duplicate' => false];
    }

    public function claimHandoff(array $binding): array
    {
        $accountId = (int) $binding['account_id'];
        $siteId = (int) $binding['site_id'];
        $deviceId = (int) $binding['id'];
        $simSlot = (int) $binding['sim_slot'];

        $eligible = DB::table('calls as c')
            ->join('tenant_call_reports as r', function ($join): void {
                $join->on('r.call_id', '=', 'c.external_call_id')
                    ->on('r.account_id', '=', 'c.account_id')
                    ->on('r.site_id', '=', 'c.site_id')
                    ->on('r.device_binding_id', '=', 'c.device_binding_id');
            })
            ->where('c.account_id', $accountId)
            ->where('c.site_id', $siteId)
            ->where('c.device_binding_id', $deviceId)
            ->where('c.sim_slot', $simSlot)
            ->whereIn('c.status', ['ended', 'completed'])
            ->where('r.handoff_status', 'requested')
            ->where('r.source', '!=', 'device_handoff')
            ->orderByDesc('c.id')
            ->limit(20)
            ->pluck('c.external_call_id');

        foreach ($eligible as $extCallId) {
            $this->scheduleHumanEscalation($binding, (string) $extCallId);
        }

        $prev = DB::table('human_escalation_jobs')
            ->where('account_id', $accountId)
            ->where('device_binding_id', $deviceId)
            ->where('sim_slot', $simSlot)
            ->where('status', 'claimed')
            ->orderByDesc('id')
            ->first();

        if ($prev) {
            if (time() - (int) $prev->leased_epoch > 90) {
                DB::table('human_escalation_jobs')
                    ->where('id', $prev->id)
                    ->where('status', 'claimed')
                    ->update([
                        'status' => 'unknown',
                        'result' => 'claim_expired',
                        'finished_at' => $this->ctx->now(),
                    ]);
            } else {
                return ['job' => null, 'reason' => 'active_claim'];
            }
        }

        $job = DB::table('human_escalation_jobs as j')
            ->join('human_escalation_contacts as co', function ($join): void {
                $join->on('co.id', '=', 'j.contact_id')
                    ->on('co.account_id', '=', 'j.account_id')
                    ->where('co.status', '=', 'active');
            })
            ->join('device_bindings as d', function ($join): void {
                $join->on('d.id', '=', 'j.device_binding_id')
                    ->on('d.account_id', '=', 'j.account_id')
                    ->on('d.sim_slot', '=', 'j.sim_slot')
                    ->where('d.status', '=', 'active');
            })
            ->join('calls as c', function ($join): void {
                $join->on('c.account_id', '=', 'j.account_id')
                    ->on('c.site_id', '=', 'j.site_id')
                    ->on('c.device_binding_id', '=', 'j.device_binding_id')
                    ->on('c.sim_slot', '=', 'j.sim_slot')
                    ->on('c.external_call_id', '=', 'j.call_id')
                    ->whereIn('c.status', ['ended', 'completed']);
            })
            ->join('human_escalation_routes as ar', function ($join): void {
                $join->on('ar.account_id', '=', 'j.account_id')
                    ->on('ar.site_id', '=', 'j.site_id')
                    ->on('ar.agent_id', '=', 'c.agent_id')
                    ->on('ar.contact_id', '=', 'j.contact_id')
                    ->where('ar.enabled', '=', 1);
            })
            ->join('tenant_call_reports as r', function ($join): void {
                $join->on('r.account_id', '=', 'j.account_id')
                    ->on('r.site_id', '=', 'j.site_id')
                    ->on('r.device_binding_id', '=', 'j.device_binding_id')
                    ->on('r.call_id', '=', 'j.call_id')
                    ->where('r.handoff_status', '=', 'requested');
            })
            ->where('j.account_id', $accountId)
            ->where('j.site_id', $siteId)
            ->where('j.device_binding_id', $deviceId)
            ->where('j.sim_slot', $simSlot)
            ->where('j.status', 'pending')
            ->where('j.due_epoch', '<=', time())
            ->orderBy('j.id')
            ->select(['j.*', 'co.name as recipient_name', 'co.phone_e164'])
            ->first();

        if (! $job) {
            return ['job' => null, 'reason' => 'no_due_notification'];
        }

        $claimed = DB::table('human_escalation_jobs')
            ->where('id', $job->id)
            ->where('account_id', $accountId)
            ->where('device_binding_id', $deviceId)
            ->where('status', 'pending')
            ->update([
                'status' => 'claimed',
                'leased_epoch' => time(),
                'attempt_count' => ((int) $job->attempt_count) + 1,
            ]);

        if ($claimed !== 1) {
            return ['job' => null, 'reason' => 'claim_race'];
        }

        $report = DB::table('calls as c')
            ->join('tenant_call_reports as r', function ($join): void {
                $join->on('r.account_id', '=', 'c.account_id')
                    ->on('r.site_id', '=', 'c.site_id')
                    ->on('r.device_binding_id', '=', 'c.device_binding_id')
                    ->on('r.call_id', '=', 'c.external_call_id');
            })
            ->where('c.account_id', $accountId)
            ->where('c.site_id', $siteId)
            ->where('c.device_binding_id', $deviceId)
            ->where('c.sim_slot', $simSlot)
            ->where('c.external_call_id', $job->call_id)
            ->select(['c.phone', 'c.name', 'c.status', 'r.summary', 'r.customer_notes', 'r.handoff_reason', 'r.outcome'])
            ->first();

        $reason = trim((string) ($report->handoff_reason ?? ''));
        $summary = trim((string) ($report->summary ?? ''));
        if ($summary === '') {
            $summary = 'لا يوجد ملخص صوتي موثّق حتى الآن؛ راجع سجل المكالمة.';
        }

        return [
            'job' => [
                'id' => (int) $job->id,
                'call_id' => (string) $job->call_id,
                'name' => (string) $job->recipient_name,
                'phone' => (string) $job->phone_e164,
                'customer_name' => (string) (($report->name ?? '') !== '' ? $report->name : 'عميل غير معروف'),
                'customer_phone' => (string) (($report->phone ?? '') !== '' ? $report->phone : 'رقم غير متاح'),
                'reason' => mb_substr($reason, 0, 900),
                'summary' => mb_substr($summary, 0, 1200),
                'sim_slot' => $simSlot,
                'expires_in_seconds' => 30,
            ],
        ];
    }

    public function ackHandoff(array $binding, int $jobId, string $stage): array
    {
        if (! in_array($stage, ['dial_started', 'failed', 'call_ended_unverified'], true)) {
            throw new InvalidArgumentException('invalid_manager_notification_stage');
        }

        $accountId = (int) $binding['account_id'];
        $siteId = (int) $binding['site_id'];
        $deviceId = (int) $binding['id'];
        $simSlot = (int) $binding['sim_slot'];

        $job = DB::table('human_escalation_jobs')
            ->where('id', $jobId)
            ->where('account_id', $accountId)
            ->where('site_id', $siteId)
            ->where('device_binding_id', $deviceId)
            ->where('sim_slot', $simSlot)
            ->first(['status']);

        if (! $job) {
            throw new InvalidArgumentException('manager_notification_not_found');
        }

        if ($job->status === $stage) {
            return ['saved' => true, 'duplicate' => true, 'status' => $stage];
        }
        if ($job->status !== 'claimed' && ! (in_array($stage, ['call_ended_unverified', 'failed'], true) && $job->status === 'dial_started')) {
            throw new InvalidArgumentException('manager_notification_not_claimed');
        }
        if ($stage === 'call_ended_unverified' && $job->status !== 'dial_started') {
            throw new InvalidArgumentException('cannot_finish_undialed_manager_call');
        }

        $now = $this->ctx->now();
        $updates = [
            'status' => $stage,
            'result' => $stage,
        ];
        if ($stage === 'dial_started') {
            $updates['dial_at'] = $now;
            $updates['finished_at'] = null;
        } else {
            $updates['finished_at'] = $now;
        }

        DB::table('human_escalation_jobs')
            ->where('id', $jobId)
            ->where('account_id', $accountId)
            ->where('device_binding_id', $deviceId)
            ->where('status', $job->status)
            ->update($updates);

        return ['saved' => true, 'duplicate' => false, 'status' => $stage];
    }

    public function buildCsvString(int $accountId, array $filters = []): string
    {
        $rows = $this->filteredQuery($accountId, $filters)->limit(1000)->get();
        $out = fopen('php://temp', 'w+b');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'التاريخ UTC', 'الاتجاه', 'الهاتف', 'جهة الاتصال', 'الإيجنت', 'الشريحة', 'الحالة', 'المدة (ث)', 'النتيجة', 'التقييم', 'ملخص المكالمة']);
        foreach ($rows as $r) {
            fputcsv($out, [
                (string) $r->id,
                $this->safeCsvCell((string) $r->created_at),
                $this->safeCsvCell((string) ($r->direction ?? 'inbound')),
                $this->safeCsvCell((string) $r->phone),
                $this->safeCsvCell((string) $r->name),
                $this->safeCsvCell((string) ($r->resolved_agent_name ?? '')),
                'SIM '.((int) ($r->sim_slot ?? 0) + 1),
                $this->safeCsvCell((string) $r->status),
                (string) ((int) ($r->duration_seconds ?? 0)),
                $this->safeCsvCell(self::OUTCOMES[$r->report_outcome ?? 'unknown'] ?? (string) ($r->report_outcome ?? '')),
                (string) ($r->ai_rating ?? ''),
                $this->safeCsvCell((string) ($r->report_summary ?? '')),
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public function cairoLocalToUtc(?string $raw, string $default = '+0 hours'): string
    {
        $tzCairo = new DateTimeZone('Africa/Cairo');
        $tzUtc = new DateTimeZone('UTC');
        $raw = trim((string) $raw);
        if ($raw === '') {
            return (new DateTimeImmutable('now', $tzUtc))->modify($default)->format('Y-m-d H:i:s');
        }
        try {
            $dt = new DateTimeImmutable(str_replace('T', ' ', $raw), $tzCairo);

            return $dt->setTimezone($tzUtc)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return (new DateTimeImmutable('now', $tzUtc))->modify($default)->format('Y-m-d H:i:s');
        }
    }
}
