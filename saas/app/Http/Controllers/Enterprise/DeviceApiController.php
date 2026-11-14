<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Services\Automation\PostCallAutomationService;
use App\Services\Campaigns\CampaignDialerService;
use App\Services\Console\ConsoleContextService;
use App\Services\Mobile\CallLedgerService;
use App\Services\Mobile\DevicePairingService;
use App\Services\Security\VaultService;
use App\Services\Voice\VoiceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DeviceApiController extends Controller
{
    public function __construct(
        private readonly ConsoleContextService $ctx,
        private readonly VaultService $vault,
        private readonly DevicePairingService $pairing,
        private readonly VoiceSessionService $voiceSession,
        private readonly CallLedgerService $calls,
        private readonly PostCallAutomationService $automation,
        private readonly CampaignDialerService $campaigns,
    ) {}

    public function handle(Request $request)
    {
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->method() !== 'POST') {
            return $this->deviceJson(['ok' => false, 'error' => 'method_not_allowed'], 405, $headers);
        }

        if (! app()->runningUnitTests() && ! $request->isSecure()) {
            return $this->deviceJson(['ok' => false, 'error' => 'https_required'], 426, $headers);
        }

        $raw = (string) $request->getContent();
        if (strlen($raw) > 1490000) {
            return $this->deviceJson(['ok' => false, 'error' => 'request_too_large'], 413, $headers);
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return $this->deviceJson(['ok' => false, 'error' => 'invalid_json'], 422, $headers);
        }

        $operation = (string) ($data['operation'] ?? '');
        $maxBody = match ($operation) {
            'workspace_file_import' => 1490000,
            'workspace_import' => 350000,
            'workspace_call_analysis', 'workspace_call_transcript', 'workspace_call_report' => 65536,
            'workspace_call_event' => 16384,
            default => 8192,
        };
        if (strlen($raw) > $maxBody) {
            return $this->deviceJson(['ok' => false, 'error' => 'request_too_large'], 413, $headers);
        }

        try {
            if ($operation === 'pair') {
                $slot = $data['sim_slot'] ?? null;
                if (! is_int($slot)) {
                    return $this->deviceJson(['ok' => false, 'error' => 'invalid_slot'], 422, $headers);
                }
                $this->pairing->rateGuard((string) ($request->ip() ?: '127.0.0.1'), (string) ($data['installation_id'] ?? ''));
                $bound = $this->pairing->redeemPairing(
                    (string) ($data['code'] ?? ''),
                    (string) ($data['installation_id'] ?? ''),
                    $slot,
                    trim((string) ($data['device_name'] ?? 'Android Device'))
                );

                return $this->deviceJson(['ok' => true, 'binding' => $bound, 'state' => 'paired_control_plane_only'], 201, $headers);
            }

            $supported = [
                'heartbeat', 'disconnect', 'published_flow', 'voice_session', 'voice_failover', 'voice_preflight',
                'caller_identify', 'caller_verify', 'workspace_contacts', 'workspace_contact_dnc', 'workspace_import',
                'workspace_calls', 'workspace_call_transcript', 'workspace_call_event', 'workspace_call_report',
                'workspace_call_analysis', 'workspace_handoff_claim', 'workspace_handoff_ack', 'workspace_campaigns',
                'workspace_campaign_create', 'workspace_campaign_action', 'workspace_campaign_claim',
                'workspace_campaign_verify', 'workspace_campaign_finish', 'workspace_contact_consent',
                'workspace_file_import', 'workspace_call_timeline', 'engine_manifest', 'engine_download',
                'flow_start', 'flow_step', 'flow_state',
            ];
            if (! in_array($operation, $supported, true)) {
                return $this->deviceJson(['ok' => false, 'error' => 'unknown_operation'], 404, $headers);
            }

            $auth = (string) ($request->header('Authorization') ?? $request->server('REDIRECT_HTTP_AUTHORIZATION', ''));
            if (! str_starts_with($auth, 'Bearer ')) {
                return $this->deviceJson(['ok' => false, 'error' => 'authorization_required'], 401, $headers);
            }
            $binding = $this->pairing->authorizeBearer($auth);
            if (! $binding) {
                return $this->deviceJson(['ok' => false, 'error' => 'device_not_authorized'], 401, $headers);
            }

            $accountId = (int) $binding['account_id'];
            $siteId = (int) $binding['site_id'];
            $deviceId = (int) $binding['id'];
            $simSlot = (int) $binding['sim_slot'];

            if ($operation === 'engine_manifest' || $operation === 'engine_download') {
                return $this->deviceJson(['ok' => false, 'error' => 'engine_release_not_configured'], 409, $headers);
            }

            if ($operation === 'disconnect') {
                if (! $this->pairing->revokeDevice($accountId, $deviceId)) {
                    return $this->deviceJson(['ok' => false, 'error' => 'device_disconnect_conflict'], 409, $headers);
                }

                return $this->deviceJson(['ok' => true, 'state' => 'revoked'], 200, $headers);
            }

            if (str_starts_with($operation, 'workspace_')) {
                switch ($operation) {
                    case 'workspace_contacts':
                        return $this->deviceJson(['ok' => true] + $this->campaigns->deviceContactsList($binding), 200, $headers);

                    case 'workspace_calls':
                        $cols = [
                            'c.external_call_id', 'c.phone', 'c.name', 'c.status', 'c.direction',
                            'c.duration_seconds', 'c.created_at', 'c.caller_gender', 'c.ai_rating', 'c.ai_mood',
                            'r.summary', 'r.customer_notes', 'r.outcome', 'r.handoff_status', 'r.handoff_reason',
                            't.transcript as raw_transcript',
                        ];
                        if (Schema::hasColumn('calls', 'summary')) {
                            $cols[] = 'c.summary as call_summary';
                        }
                        $rows = DB::table('calls as c')
                            ->leftJoin('tenant_call_reports as r', function ($join): void {
                                $join->on('r.device_binding_id', '=', 'c.device_binding_id')
                                    ->on('r.call_id', '=', 'c.external_call_id')
                                    ->on('r.account_id', '=', 'c.account_id')
                                    ->on('r.site_id', '=', 'c.site_id');
                            })
                            ->leftJoin('tenant_call_transcripts as t', function ($join): void {
                                $join->on('t.device_binding_id', '=', 'c.device_binding_id')
                                    ->on('t.call_id', '=', 'c.external_call_id')
                                    ->on('t.account_id', '=', 'c.account_id')
                                    ->on('t.site_id', '=', 'c.site_id')
                                    ->on('t.sim_slot', '=', 'c.sim_slot');
                            })
                            ->where('c.account_id', $accountId)
                            ->where('c.site_id', $siteId)
                            ->where('c.device_binding_id', $deviceId)
                            ->where('c.sim_slot', $simSlot)
                            ->orderByDesc('c.id')
                            ->limit(100)
                            ->get($cols);

                        $calls = [];
                        foreach ($rows as $r) {
                            $hasTx = ! empty($r->raw_transcript);
                            $excerpt = null;
                            if ($hasTx) {
                                $plain = $this->vault->unsealTranscript((string) $r->raw_transcript);
                                $excerpt = is_string($plain) ? mb_substr($plain, 0, 240) : null;
                            }
                            $resolvedSummary = trim((string) ($r->summary ?? '')) !== ''
                                ? (string) $r->summary
                                : (trim((string) ($r->call_summary ?? '')) !== '' ? (string) $r->call_summary : null);
                            $calls[] = [
                                'id' => (string) $r->external_call_id,
                                'call_id' => (string) $r->external_call_id,
                                'external_call_id' => (string) $r->external_call_id,
                                'phone' => (string) ($r->phone ?? ''),
                                'name' => (string) ($r->name ?? ''),
                                'status' => (string) ($r->status ?? ''),
                                'direction' => (string) ($r->direction ?? 'inbound'),
                                'duration' => (int) ($r->duration_seconds ?? 0),
                                'duration_seconds' => (int) ($r->duration_seconds ?? 0),
                                'started' => str_replace(' ', 'T', (string) $r->created_at).'Z',
                                'started_at' => (string) $r->created_at,
                                'started_at_cairo' => $this->calls->formatCairo((string) $r->created_at),
                                'caller_gender' => $r->caller_gender,
                                'ai_rating' => $r->ai_rating,
                                'ai_mood' => $r->ai_mood,
                                'identity_status' => ((string) ($r->phone ?? '')) !== '' ? 'available' : 'unverified',
                                'summary' => $resolvedSummary,
                                'customer_notes' => $r->customer_notes,
                                'has_transcript' => $hasTx,
                                'transcript_excerpt' => $excerpt,
                                'outcome' => (string) ($r->outcome ?? 'unknown'),
                                'handoff_status' => (string) ($r->handoff_status ?? 'not_requested'),
                                'handoff_reason' => (string) ($r->handoff_reason ?? ''),
                            ];
                        }

                        return $this->deviceJson(['ok' => true, 'calls' => $calls, 'total' => count($calls)], 200, $headers);

                    case 'workspace_call_transcript':
                        $callId = trim((string) ($data['call_id'] ?? ''));
                        if (preg_match('/^[A-Za-z0-9_-]{4,64}$/D', $callId) !== 1) {
                            throw new InvalidArgumentException('invalid_transcript_call');
                        }
                        $callRow = DB::table('calls')
                            ->where('account_id', $accountId)
                            ->where('site_id', $siteId)
                            ->where('device_binding_id', $deviceId)
                            ->where('sim_slot', $simSlot)
                            ->where('external_call_id', $callId)
                            ->first(['id']);
                        if (! $callRow) {
                            throw new InvalidArgumentException('call_not_found_for_sim');
                        }

                        $now = $this->ctx->now();
                        if (array_key_exists('transcript', $data)) {
                            $text = trim((string) ($data['transcript'] ?? ''));
                            if ($text === '' || mb_strlen($text, 'UTF-8') > 12000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $text) === 1) {
                                throw new InvalidArgumentException('invalid_transcript');
                            }
                            $existing = DB::table('tenant_call_transcripts')
                                ->where('account_id', $accountId)
                                ->where('site_id', $siteId)
                                ->where('device_binding_id', $deviceId)
                                ->where('sim_slot', $simSlot)
                                ->where('call_id', $callId)
                                ->first();
                            if ($existing) {
                                $before = (string) $existing->transcript;
                                if (! str_starts_with($before, 'enc:v1:')) {
                                    DB::table('tenant_call_transcripts')
                                        ->where('account_id', $accountId)
                                        ->where('site_id', $siteId)
                                        ->where('device_binding_id', $deviceId)
                                        ->where('sim_slot', $simSlot)
                                        ->where('call_id', $callId)
                                        ->update(['transcript' => $this->vault->sealTranscript($before)]);
                                }

                                return $this->deviceJson(['ok' => true, 'saved' => true, 'duplicate' => true, 'call_id' => $callId], 200, $headers);
                            }

                            DB::table('tenant_call_transcripts')->insertOrIgnore([
                                'account_id' => $accountId,
                                'site_id' => $siteId,
                                'device_binding_id' => $deviceId,
                                'sim_slot' => $simSlot,
                                'call_id' => $callId,
                                'transcript' => $this->vault->sealTranscript($text),
                                'source' => 'gemini_live_device',
                                'created_at' => $now,
                            ]);

                            if (mb_strlen($text, 'UTF-8') >= 18) {
                                try {
                                    $this->voiceSession->analyzeCallTranscript(
                                        $binding,
                                        $callId,
                                        $text,
                                        fn (array $reportInput) => $this->calls->saveReport($accountId, $siteId, $deviceId, $reportInput['call_id'], $reportInput, 'device')
                                    );
                                } catch (Throwable) {
                                    // Keep transcript ingestion resilient even if AI quota temporarily throttles
                                }
                            }

                            return $this->deviceJson(['ok' => true, 'saved' => true, 'duplicate' => false, 'call_id' => $callId], 200, $headers);
                        }

                        $row = DB::table('tenant_call_transcripts')
                            ->where('account_id', $accountId)
                            ->where('site_id', $siteId)
                            ->where('device_binding_id', $deviceId)
                            ->where('sim_slot', $simSlot)
                            ->where('call_id', $callId)
                            ->first();
                        $plain = null;
                        if ($row) {
                            $stored = (string) $row->transcript;
                            if (str_starts_with($stored, 'enc:v1:')) {
                                $plain = $this->vault->unseal(substr($stored, 7));
                                if (! is_string($plain)) {
                                    throw new RuntimeException('tenant_transcript_decryption_failed');
                                }
                            } else {
                                $plain = $stored;
                                DB::table('tenant_call_transcripts')
                                    ->where('account_id', $accountId)
                                    ->where('site_id', $siteId)
                                    ->where('device_binding_id', $deviceId)
                                    ->where('sim_slot', $simSlot)
                                    ->where('call_id', $callId)
                                    ->update(['transcript' => $this->vault->sealTranscript($stored)]);
                            }
                        }

                        return $this->deviceJson([
                            'ok' => true,
                            'call_id' => $callId,
                            'transcript' => $plain,
                            'source' => 'gemini_live_device',
                        ], 200, $headers);

                    case 'workspace_call_timeline':
                        return $this->deviceJson(['ok' => true] + $this->calls->storeTimelineEvent($binding, $data), 200, $headers);

                    case 'workspace_call_event':
                        if (array_key_exists('phase', $data) && array_key_exists('sequence', $data)) {
                            $out = $this->handleStructuredCallEvent($binding, $data);

                            return $this->deviceJson(['ok' => true] + $out, 200, $headers);
                        }

                        return $this->deviceJson($this->handleLegacyCallEvent($binding, $data), 200, $headers);

                    case 'workspace_call_report':
                        $res = $this->calls->saveReport($accountId, $siteId, $deviceId, (string) ($data['call_id'] ?? ''), $data, 'device');

                        return $this->deviceJson($res, 200, $headers);

                    case 'workspace_call_analysis':
                        $res = $this->voiceSession->analyzeCallTranscript(
                            $binding,
                            (string) ($data['call_id'] ?? ''),
                            (string) ($data['transcript'] ?? ''),
                            fn (array $reportInput) => $this->calls->saveReport($accountId, $siteId, $deviceId, $reportInput['call_id'], $reportInput, 'device')
                        );

                        return $this->deviceJson(['ok' => true] + $res, 200, $headers);

                    case 'workspace_handoff_claim':
                        return $this->deviceJson(['ok' => true] + $this->calls->claimHandoff($binding), 200, $headers);

                    case 'workspace_handoff_ack':
                        return $this->deviceJson(['ok' => true] + $this->calls->ackHandoff($binding, (int) ($data['job_id'] ?? 0), (string) ($data['stage'] ?? '')), 200, $headers);

                    case 'workspace_campaigns':
                        return $this->deviceJson([
                            'ok' => true,
                            'queue' => $this->campaigns->campaignQueueStatusForBinding($binding),
                        ] + $this->campaigns->deviceListCampaigns($binding), 200, $headers);

                    case 'workspace_campaign_queue':
                        return $this->deviceJson([
                            'ok' => true,
                            'queue' => $this->campaigns->campaignQueueStatusForBinding($binding),
                        ], 200, $headers);

                    case 'workspace_campaign_dial_next':
                        return $this->deviceJson([
                            'ok' => true,
                            'queue' => $this->campaigns->dialNextNowForBinding($binding, is_string($data['campaign_id'] ?? null) ? $data['campaign_id'] : null),
                        ], 200, $headers);

                    case 'workspace_campaign_claim':
                        return $this->deviceJson(['ok' => true] + $this->campaigns->deviceClaimAttempt($binding), 200, $headers);

                    case 'workspace_campaign_verify':
                        return $this->deviceJson(['ok' => true, 'verification' => $this->campaigns->deviceVerifyAttempt($binding, (string) ($data['attempt_id'] ?? ''))], 200, $headers);

                    case 'workspace_campaign_finish':
                        return $this->deviceJson(['ok' => true, 'attempt' => $this->campaigns->deviceFinishAttempt($binding, (string) ($data['attempt_id'] ?? ''), (string) ($data['result'] ?? ''))], 200, $headers);
                }

                if (($data['confirmed'] ?? false) !== true) {
                    return $this->deviceJson(['ok' => false, 'error' => 'confirmation_required'], 422, $headers);
                }

                switch ($operation) {
                    case 'workspace_import':
                        if (! is_array($data['contacts'] ?? null)) {
                            return $this->deviceJson(['ok' => false, 'error' => 'invalid_contact_list'], 422, $headers);
                        }

                        return $this->deviceJson(['ok' => true, 'counts' => $this->campaigns->deviceImportContacts($binding, $data['contacts'])], 200, $headers);

                    case 'workspace_file_import':
                        $name = (string) ($data['name'] ?? '');
                        $encoded = $data['data_b64'] ?? null;
                        if (preg_match('/^.{1,140}\.(csv|txt|xlsx)$/iD', $name) !== 1 || ! is_string($encoded)) {
                            return $this->deviceJson(['ok' => false, 'error' => 'invalid_file'], 422, $headers);
                        }
                        $decoded = base64_decode($encoded, true);
                        if (! is_string($decoded) || strlen($decoded) < 1 || strlen($decoded) > 1048576) {
                            return $this->deviceJson(['ok' => false, 'error' => 'invalid_file'], 422, $headers);
                        }
                        $file = tempnam(sys_get_temp_dir(), 'otv-tenant-');
                        if ($file === false) {
                            return $this->deviceJson(['ok' => false, 'error' => 'temporary_file_failed'], 503, $headers);
                        }
                        try {
                            if (file_put_contents($file, $decoded, LOCK_EX) === false) {
                                throw new RuntimeException('file_write_failed');
                            }
                            $rows = $this->campaigns->parseContactsFile($file, $name);
                            $counts = $this->campaigns->deviceImportContacts($binding, $rows);
                        } finally {
                            if (is_file($file)) {
                                @unlink($file);
                            }
                        }

                        return $this->deviceJson(['ok' => true, 'counts' => $counts], 200, $headers);

                    case 'workspace_contact_dnc':
                        if (! is_bool($data['blocked'] ?? null)) {
                            return $this->deviceJson(['ok' => false, 'error' => 'invalid_blocked'], 422, $headers);
                        }

                        return $this->deviceJson(['ok' => true] + $this->campaigns->deviceSetDnc($binding, $data['id'] ?? null, $data['blocked']), 200, $headers);

                    case 'workspace_contact_consent':
                        return $this->deviceJson(['ok' => true] + $this->campaigns->deviceRecordConsent(
                            $binding,
                            (string) ($data['id'] ?? ''),
                            (string) ($data['method'] ?? ''),
                            (string) ($data['evidence'] ?? '')
                        ), 200, $headers);

                    case 'workspace_campaign_create':
                        return $this->deviceJson(['ok' => true] + $this->campaigns->deviceCreateCampaign($binding, $data), 200, $headers);

                    case 'workspace_campaign_action':
                        return $this->deviceJson(['ok' => true] + $this->campaigns->deviceCampaignAction($binding, $data), 200, $headers);
                }
            }

            $profile = $this->pairing->heartbeat($binding);

            switch ($operation) {
                case 'heartbeat':
                    return $this->deviceJson(['ok' => true, 'profile' => $profile], 200, $headers);

                case 'caller_identify':
                    $phone = $this->normalizeVoicePhone((string) ($data['phone'] ?? ''));
                    $match = DB::table('voice_staff_numbers as v')
                        ->join('memberships as m', function ($join): void {
                            $join->on('m.account_id', '=', 'v.account_id')->on('m.user_id', '=', 'v.user_id');
                        })
                        ->join('users as u', 'u.id', '=', 'v.user_id')
                        ->where('v.account_id', $accountId)
                        ->where('v.phone_e164', $phone)
                        ->where('v.status', 'active')
                        ->where('u.status', 'active')
                        ->first(['v.id']);

                    return $this->deviceJson([
                        'ok' => true,
                        'recognized' => $match !== null,
                        'pin_required' => true,
                        'facts' => [],
                        'authenticated' => false,
                    ], 200, $headers);

                case 'caller_verify':
                    $pin = $data['pin'] ?? null;
                    if (! is_string($pin) || strlen($pin) > 10) {
                        return $this->deviceJson(['ok' => false, 'error' => 'invalid_pin'], 422, $headers);
                    }
                    $principal = $this->authenticateStaffCaller($accountId, (string) ($data['phone'] ?? ''), $pin);
                    if (! $principal) {
                        return $this->deviceJson(['ok' => false, 'error' => 'caller_not_verified', 'facts' => []], 403, $headers);
                    }
                    $facts = $this->visibleStaffFacts($principal, 45);
                    $this->ctx->audit($principal['user_id'], $principal['account_id'], 'voice.caller.verified', [
                        'device_binding_id' => $deviceId,
                        'categories' => $principal['scopes'],
                    ]);

                    return $this->deviceJson([
                        'ok' => true,
                        'authenticated' => true,
                        'principal' => [
                            'name' => $principal['name'],
                            'role' => $principal['role'],
                            'scopes' => $principal['scopes'],
                        ],
                        'facts' => $facts,
                        'actions_enabled' => false,
                    ], 200, $headers);

                case 'voice_preflight':
                    try {
                        $ready = $this->voiceSession->preflightForBinding($binding);
                    } catch (RuntimeException $e) {
                        $reason = $e->getMessage();
                        if (in_array($reason, ['voice_subscription_inactive', 'premium_voice_not_available'], true)) {
                            return $this->deviceJson(['ok' => false, 'error' => $reason], 403, $headers);
                        }
                        if (in_array($reason, ['voice_provider_not_configured', 'voice_all_keys_unavailable', 'voice_account_inactive'], true)) {
                            return $this->deviceJson(['ok' => false, 'error' => $reason], 409, $headers);
                        }

                        return $this->deviceJson(['ok' => false, 'error' => 'voice_provider_unavailable'], 503, $headers);
                    }

                    return $this->deviceJson(['ok' => true, 'preflight' => $ready], 200, $headers);

                case 'voice_session':
                    try {
                        $session = $this->voiceSession->issueLiveVoiceSession($binding);
                    } catch (RuntimeException $e) {
                        $reason = $e->getMessage();
                        if (in_array($reason, ['voice_subscription_inactive', 'premium_voice_not_available'], true)) {
                            return $this->deviceJson(['ok' => false, 'error' => $reason], 403, $headers);
                        }
                        if (in_array($reason, ['voice_provider_not_configured', 'voice_all_keys_unavailable', 'voice_account_inactive'], true)) {
                            return $this->deviceJson(['ok' => false, 'error' => $reason], 409, $headers);
                        }

                        return $this->deviceJson(['ok' => false, 'error' => 'voice_provider_unavailable'], 503, $headers);
                    }

                    return $this->deviceJson(['ok' => true, 'session' => $session], 200, $headers);

                case 'voice_failover':
                    $generation = $data['failover_generation'] ?? null;
                    if (! is_int($generation)) {
                        return $this->deviceJson(['ok' => false, 'error' => 'invalid_failover_generation'], 422, $headers);
                    }
                    try {
                        $rotated = $this->voiceSession->rotateLiveVoiceSession(
                            $binding,
                            (string) ($data['failover_ticket'] ?? ''),
                            $generation,
                            (string) ($data['failure_code'] ?? '')
                        );
                    } catch (RuntimeException $e) {
                        $code = $e->getMessage();
                        if ($code === 'voice_subscription_inactive') {
                            return $this->deviceJson(['ok' => false, 'error' => $code], 403, $headers);
                        }
                        if (in_array($code, [
                            'voice_failover_ticket_invalid_or_stale',
                            'voice_failover_pool_changed',
                            'voice_failover_key_not_owned',
                            'voice_failover_concurrent_request',
                            'voice_failover_binding_revoked',
                        ], true)) {
                            return $this->deviceJson(['ok' => false, 'error' => 'voice_failover_conflict'], 409, $headers);
                        }

                        return $this->deviceJson(['ok' => false, 'error' => 'voice_failover_no_healthy_keys'], 503, $headers);
                    }

                    return $this->deviceJson(['ok' => true, 'session' => $rotated], 200, $headers);

                case 'flow_start':
                case 'flow_state':
                case 'flow_step':
                    if (! $this->voiceSession->entitled($binding)) {
                        return $this->deviceJson(['ok' => false, 'error' => 'voice_subscription_inactive'], 403, $headers);
                    }
                    if ($operation === 'flow_start') {
                        $callId = (string) ($data['call_id'] ?? '');
                        $opened = $this->voiceSession->flowStart($accountId, $siteId, (int) $binding['agent_id'], $callId, 'device', 0, $deviceId);
                        $row = $this->voiceSession->flowSession($accountId, (int) $opened['session_id'], 0, $deviceId);
                        $advanced = null;
                        if (! $opened['reused'] && $row['status'] === 'active') {
                            $advanced = $this->voiceSession->flowProcess($accountId, (int) $opened['session_id'], 0, null, $deviceId);
                            $row = $this->voiceSession->flowSession($accountId, (int) $opened['session_id'], 0, $deviceId);
                        }

                        return $this->deviceJson([
                            'ok' => true,
                            'session_id' => (int) $row['id'],
                            'status' => $row['status'],
                            'revision' => (int) $row['published_revision'],
                            'step_version' => (int) $row['step_version'],
                            'events' => $advanced['events'] ?? [],
                            'state' => json_decode((string) $row['state_json'], true, 32, JSON_THROW_ON_ERROR),
                            'reused' => $opened['reused'],
                            'tool_execution' => 'requires_separate_verified_human_approval',
                        ], 200, $headers);
                    }

                    $id = $data['session_id'] ?? null;
                    if (! is_int($id) || $id < 1) {
                        return $this->deviceJson(['ok' => false, 'error' => 'flow_session_id_invalid'], 422, $headers);
                    }
                    $row = $this->voiceSession->flowSession($accountId, $id, 0, $deviceId);
                    $this->voiceSession->flowRequireActiveContext($row);

                    if ($operation === 'flow_state') {
                        return $this->deviceJson([
                            'ok' => true,
                            'session_id' => $id,
                            'status' => $row['status'],
                            'step_version' => (int) $row['step_version'],
                            'state' => json_decode((string) $row['state_json'], true, 32, JSON_THROW_ON_ERROR),
                        ], 200, $headers);
                    }

                    $expected = $data['step_version'] ?? null;
                    $reply = $data['reply'] ?? null;
                    if (! is_int($expected) || $expected < 1 || $expected !== (int) $row['step_version']) {
                        return $this->deviceJson([
                            'ok' => false,
                            'error' => 'flow_step_version_conflict',
                            'step_version' => (int) $row['step_version'],
                        ], 409, $headers);
                    }
                    if (! is_string($reply) || trim($reply) === '' || mb_strlen($reply) > 500) {
                        return $this->deviceJson(['ok' => false, 'error' => 'flow_reply_invalid'], 422, $headers);
                    }
                    $out = $this->voiceSession->flowProcess($accountId, $id, 0, $reply, $deviceId);
                    $fresh = $this->voiceSession->flowSession($accountId, $id, 0, $deviceId);

                    return $this->deviceJson([
                        'ok' => true,
                        'session_id' => $id,
                        'status' => $out['status'],
                        'step_version' => (int) $fresh['step_version'],
                        'events' => $out['events'],
                        'state' => $out['state'],
                        'done' => $out['done'],
                        'tool_execution' => 'requires_separate_verified_human_approval',
                    ], 200, $headers);

                case 'published_flow':
                    try {
                        $def = $this->voiceSession->buildDefinitionForBinding($binding);
                    } catch (InvalidArgumentException $e) {
                        if ($e->getMessage() === 'agent_profile_not_published') {
                            return $this->deviceJson(['ok' => false, 'error' => 'agent_flow_not_published'], 409, $headers);
                        }
                        throw $e;
                    }

                    $compactPolicy = is_array($def['answer_policy'] ?? null) ? $def['answer_policy'] : [];
                    unset($compactPolicy['scenario_text']);

                    return $this->deviceJson([
                        'ok' => true,
                        'agent_id' => $def['agent_id'],
                        'site_id' => $def['site_id'],
                        'sim_slot' => $simSlot,
                        'revision' => $def['revision'],
                        'hash' => $def['hash'],
                        'voice' => $def['voice'],
                        'voice_label' => $def['voice_label'],
                        'instructions' => mb_substr((string) $def['instructions'], 0, 1000, 'UTF-8'),
                        'knowledge' => mb_substr((string) $def['knowledge'], 0, 1000, 'UTF-8'),
                        'answer_policy' => $compactPolicy,
                        'conversation_mode' => 'dynamic',
                        'flow' => $def['flow'],
                        'execution' => 'dynamic_gemini_live_profile_legacy_graph_ignored',
                    ], 200, $headers);
            }
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'device_pairing_rate_limited') {
                return $this->deviceJson(['ok' => false, 'error' => 'device_pairing_rate_limited'], 429, $headers);
            }

            return $this->deviceJson(['ok' => false, 'error' => $e->getMessage()], 422, $headers);
        } catch (RuntimeException $e) {
            return $this->deviceJson(['ok' => false, 'error' => $e->getMessage()], 409, $headers);
        } catch (Throwable $e) {
            return $this->deviceJson(['ok' => false, 'error' => 'internal_error'], 500, $headers);
        }
    }

    public function tts(Request $request)
    {
        $headers = [
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Buffering' => 'no',
            'Referrer-Policy' => 'no-referrer',
            'Connection' => 'keep-alive',
            'Keep-Alive' => 'timeout=30, max=1000',
        ];

        if ($request->method() !== 'POST') {
            return $this->deviceJson(['ok' => false, 'error' => 'method_not_allowed'], 405, $headers + ['Allow' => 'POST']);
        }
        if (! app()->runningUnitTests() && ! $request->isSecure()) {
            return $this->deviceJson(['ok' => false, 'error' => 'https_required'], 426, $headers);
        }

        $origin = (string) $request->header('Origin', '');
        $host = strtolower((string) $request->getHost());
        if ($origin !== '' && (parse_url($origin, PHP_URL_SCHEME) !== 'https' || strtolower((string) parse_url($origin, PHP_URL_HOST)) !== $host)) {
            return $this->deviceJson(['ok' => false, 'error' => 'wrong_origin'], 403, $headers);
        }

        $auth = (string) ($request->header('Authorization') ?? $request->server('REDIRECT_HTTP_AUTHORIZATION', ''));
        if (! str_starts_with($auth, 'Bearer ') || strlen($auth) > 128) {
            return $this->deviceJson(['ok' => false, 'error' => 'device_authorization_required'], 401, $headers);
        }

        $raw = (string) $request->getContent();
        if ((int) ($request->header('Content-Length') ?? 0) > 2400 || strlen($raw) > 2400) {
            return $this->deviceJson(['ok' => false, 'error' => 'request_too_large'], 413, $headers);
        }

        $body = json_decode($raw, true);
        $text = is_array($body) && is_string($body['text'] ?? null) ? trim($body['text']) : '';
        if ($text === '' || mb_strlen($text, 'UTF-8') > 400 || strlen($text) > 1600) {
            return $this->deviceJson(['ok' => false, 'error' => 'invalid_premium_text'], 422, $headers);
        }

        $binding = $this->pairing->authorizeBearer($auth);
        if (! $binding) {
            return $this->deviceJson(['ok' => false, 'error' => 'device_not_authorized'], 401, $headers);
        }

        try {
            $cfg = $this->voiceSession->resolveFishTtsConfig($binding);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if (in_array($msg, ['voice_subscription_inactive', 'premium_voice_not_published', 'premium_voice_not_available'], true)) {
                return $this->deviceJson(['ok' => false, 'error' => $msg], 403, $headers);
            }

            return $this->deviceJson(['ok' => false, 'error' => 'premium_voice_unavailable'], 503, $headers);
        }

        $circuit = $this->voiceSession->fishCircuitActive($cfg['secret_fingerprint']);
        if ($circuit !== null) {
            return $this->deviceJson(['ok' => false, 'error' => $circuit], $this->voiceSession->fishClientHttp($circuit), $headers);
        }

        if (! function_exists('curl_multi_init') || ! function_exists('curl_init')) {
            return $this->deviceJson(['ok' => false, 'error' => 'premium_tts_provider_unavailable'], 503, $headers);
        }

        $payload = json_encode([
            'text' => $text,
            'reference_id' => $cfg['reference_id'],
            'format' => 'pcm',
            'sample_rate' => 24000,
            'temperature' => 0.8,
            'top_p' => 0.85,
            'streaming' => true,
            'latency' => 'low',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $ch = curl_init('https://api.fish.audio/v1/tts');
        $mh = curl_multi_init();
        if ($ch === false || $mh === false) {
            if ($ch !== false) {
                curl_close($ch);
            }
            if ($mh !== false) {
                curl_multi_close($mh);
            }

            return $this->deviceJson(['ok' => false, 'error' => 'premium_voice_unavailable'], 503, $headers);
        }

        $upstreamStatus = 502;
        $firstBuffer = '';
        $errorBody = '';
        $sent = 0;
        $maxAudioBytes = 48000 * 2 * 55;
        $firstChunkReady = false;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$cfg['api_key'],
                'Content-Type: application/json',
                'Accept: application/octet-stream',
                'model: '.$cfg['model'],
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($c, string $header) use (&$upstreamStatus): int {
                if (preg_match('~^HTTP/[\d.]+\s+(\d{3})\b~', $header, $m) === 1) {
                    $upstreamStatus = (int) $m[1];
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use (
                &$upstreamStatus,
                &$firstBuffer,
                &$errorBody,
                &$sent,
                &$firstChunkReady,
                $maxAudioBytes
            ): int {
                $len = strlen($chunk);
                if ($upstreamStatus < 200 || $upstreamStatus >= 300) {
                    if (strlen($errorBody) < 4096) {
                        $errorBody .= substr($chunk, 0, 4096 - strlen($errorBody));
                    }

                    return $len;
                }
                $sent += $len;
                if ($sent > $maxAudioBytes) {
                    return 0;
                }
                $firstBuffer .= $chunk;
                $firstChunkReady = true;

                return $len;
            },
        ]);

        curl_multi_add_handle($mh, $ch);
        $running = 1;
        do {
            $mrc = curl_multi_exec($mh, $running);
            if ($firstChunkReady && $upstreamStatus >= 200 && $upstreamStatus < 300) {
                break;
            }
            if ($running > 0) {
                curl_multi_select($mh, 0.25);
            }
        } while ($running > 0 && $mrc === CURLM_OK);

        if (! $firstChunkReady || $upstreamStatus >= 400) {
            while ($running > 0 && $mrc === CURLM_OK) {
                $mrc = curl_multi_exec($mh, $running);
                if ($running > 0) {
                    curl_multi_select($mh, 0.25);
                }
            }
            $curlStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            curl_multi_close($mh);

            $status = $curlStatus > 0 ? $curlStatus : $upstreamStatus;
            if ($status >= 400) {
                $code = $this->voiceSession->fishClassifyError($status, $errorBody);
                $this->voiceSession->fishCircuitMark($cfg['secret_fingerprint'], $code);

                return $this->deviceJson(['ok' => false, 'error' => $code], $this->voiceSession->fishClientHttp($code), $headers);
            }
            if ($curlErrno !== 0) {
                return $this->deviceJson(['ok' => false, 'error' => 'premium_tts_network_error'], 503, $headers);
            }

            return $this->deviceJson(['ok' => false, 'error' => 'premium_tts_empty_audio'], 502, $headers);
        }

        return response()->stream(function () use ($mh, $ch, &$firstBuffer, &$running): void {
            if ($firstBuffer !== '') {
                echo $firstBuffer;
                $firstBuffer = '';
                @flush();
            }
            try {
                while ($running > 0) {
                    $mrc = curl_multi_exec($mh, $running);
                    if ($firstBuffer !== '') {
                        echo $firstBuffer;
                        $firstBuffer = '';
                        @flush();
                    }
                    if ($mrc !== CURLM_OK) {
                        break;
                    }
                    if ($running > 0) {
                        curl_multi_select($mh, 0.25);
                    }
                }
                if ($firstBuffer !== '') {
                    echo $firstBuffer;
                    $firstBuffer = '';
                    @flush();
                }
            } finally {
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                curl_multi_close($mh);
            }
        }, 200, $headers + [
            'Content-Type' => 'application/octet-stream',
            'X-PCM-Sample-Rate' => '24000',
            'X-PCM-Channels' => '1',
            'X-PCM-Format' => 's16le',
        ]);
    }

    private function handleStructuredCallEvent(array $binding, array $p): array
    {
        $id = $p['call_id'] ?? null;
        $seq = $p['sequence'] ?? null;
        $phase = $p['phase'] ?? null;
        $duration = $p['duration_seconds'] ?? 0;

        if (
            ! is_string($id)
            || preg_match('/^[A-Za-z0-9_-]{4,64}$/D', $id) !== 1
            || ! is_int($seq) || $seq < 1 || $seq > 100
            || ! in_array($phase, ['ringing', 'active', 'missed', 'ended', 'identified', 'human_takeover', 'customer_human_request', 'agent_report'], true)
            || ! is_int($duration) || $duration < 0 || $duration > 172800
        ) {
            throw new InvalidArgumentException('invalid_call_event');
        }

        $accountId = (int) $binding['account_id'];
        $siteId = (int) $binding['site_id'];
        $deviceId = (int) $binding['id'];
        $simSlot = (int) $binding['sim_slot'];
        $now = $this->ctx->now();

        $duplicate = DB::transaction(function () use ($binding, $p, $id, $seq, $phase, $duration, $accountId, $siteId, $deviceId, $simSlot, $now): bool {
            $exists = DB::table('tenant_call_events')
                ->where('device_binding_id', $deviceId)
                ->where('call_id', $id)
                ->where('sequence', $seq)
                ->exists();

            if ($exists) {
                return true;
            }

            if (! DB::table('calls')->where('device_binding_id', $deviceId)->where('external_call_id', $id)->exists()) {
                DB::table('calls')->insert([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'phone' => '',
                    'name' => '',
                    'duration_seconds' => 0,
                    'direction' => in_array($p['direction'] ?? 'inbound', ['inbound', 'outbound'], true) ? ($p['direction'] ?? 'inbound') : 'inbound',
                    'status' => 'ringing',
                    'is_demo' => 0,
                    'created_at' => $now,
                    'external_call_id' => $id,
                    'device_binding_id' => $deviceId,
                    'sim_slot' => $simSlot,
                    'agent_id' => (int) $binding['agent_id'],
                    'agent_name' => (string) ($binding['agent_name'] ?? ''),
                ]);
            }

            if ($phase === 'human_takeover') {
                $reason = 'تم استلام التحكم من الإيجنت يدويًا عبر زر الهاتف؛ لا يوجد تحويل خارجي مؤكد.';
                $report = DB::table('tenant_call_reports')
                    ->where('account_id', $accountId)
                    ->where('site_id', $siteId)
                    ->where('device_binding_id', $deviceId)
                    ->where('call_id', $id)
                    ->first();

                if (! $report) {
                    DB::table('tenant_call_reports')->insert([
                        'account_id' => $accountId,
                        'site_id' => $siteId,
                        'device_binding_id' => $deviceId,
                        'call_id' => $id,
                        'summary' => '',
                        'customer_notes' => '',
                        'outcome' => 'unknown',
                        'handoff_status' => 'requested',
                        'handoff_reason' => $reason,
                        'source' => 'device_handoff',
                        'revision' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif ($report->handoff_status !== 'completed') {
                    DB::table('tenant_call_reports')
                        ->where('account_id', $accountId)
                        ->where('site_id', $siteId)
                        ->where('device_binding_id', $deviceId)
                        ->where('call_id', $id)
                        ->update([
                            'handoff_status' => 'requested',
                            'handoff_reason' => trim((string) $report->handoff_reason) === '' ? $reason : $report->handoff_reason,
                            'revision' => ((int) $report->revision) + 1,
                            'updated_at' => $now,
                        ]);
                }
            } elseif ($phase === 'customer_human_request') {
                $reason = $p['handoff_reason'] ?? '';
                $notes = $p['customer_notes'] ?? '';
                if (! is_string($reason) || ! is_string($notes) || mb_strlen($reason) > 350 || mb_strlen($notes) > 700) {
                    throw new InvalidArgumentException('invalid_handoff_details');
                }
                $reason = trim($reason);
                if ($reason === '') {
                    $reason = 'المتصل طلب التحدث مع موظف بشري أثناء المكالمة.';
                }
                $notes = trim($notes);

                $report = DB::table('tenant_call_reports')
                    ->where('account_id', $accountId)
                    ->where('site_id', $siteId)
                    ->where('device_binding_id', $deviceId)
                    ->where('call_id', $id)
                    ->first();

                if (! $report) {
                    DB::table('tenant_call_reports')->insert([
                        'account_id' => $accountId,
                        'site_id' => $siteId,
                        'device_binding_id' => $deviceId,
                        'call_id' => $id,
                        'summary' => '',
                        'customer_notes' => $notes,
                        'outcome' => 'unknown',
                        'handoff_status' => 'requested',
                        'handoff_reason' => $reason,
                        'source' => 'device',
                        'revision' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif ($report->handoff_status !== 'completed') {
                    DB::table('tenant_call_reports')
                        ->where('account_id', $accountId)
                        ->where('site_id', $siteId)
                        ->where('device_binding_id', $deviceId)
                        ->where('call_id', $id)
                        ->update([
                            'handoff_status' => 'requested',
                            'handoff_reason' => trim((string) $report->handoff_reason) === '' ? $reason : $report->handoff_reason,
                            'customer_notes' => trim((string) $report->customer_notes) === '' ? $notes : $report->customer_notes,
                            'source' => 'device',
                            'revision' => ((int) $report->revision) + 1,
                            'updated_at' => $now,
                        ]);
                }
            } elseif ($phase === 'agent_report') {
                $summary = $p['summary'] ?? '';
                $notes = $p['customer_notes'] ?? '';
                if (! is_string($summary) || ! is_string($notes) || mb_strlen($summary) > 1000 || mb_strlen($notes) > 1000) {
                    throw new InvalidArgumentException('invalid_live_report');
                }
                $summary = trim($summary);
                $notes = trim($notes);
                if ($summary !== '' || $notes !== '') {
                    if ($summary !== '') {
                        $summary = 'ملخص أولي غير مؤكد: '.$summary;
                    }
                    $current = DB::table('tenant_call_reports')
                        ->where('account_id', $accountId)
                        ->where('site_id', $siteId)
                        ->where('device_binding_id', $deviceId)
                        ->where('call_id', $id)
                        ->first();
                    if (! $current) {
                        DB::table('tenant_call_reports')->insert([
                            'account_id' => $accountId,
                            'site_id' => $siteId,
                            'device_binding_id' => $deviceId,
                            'call_id' => $id,
                            'summary' => $summary,
                            'customer_notes' => $notes,
                            'outcome' => 'unknown',
                            'handoff_status' => 'not_requested',
                            'handoff_reason' => '',
                            'source' => 'device',
                            'revision' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $replaceSummary = trim((string) $current->summary) === ''
                            || str_starts_with((string) $current->summary, 'ملخص أولي غير مؤكد:')
                            || str_starts_with((string) $current->summary, 'تعذر التلخيص الآلي');
                        DB::table('tenant_call_reports')
                            ->where('account_id', $accountId)
                            ->where('site_id', $siteId)
                            ->where('device_binding_id', $deviceId)
                            ->where('call_id', $id)
                            ->update([
                                'summary' => ($replaceSummary && $summary !== '') ? $summary : $current->summary,
                                'customer_notes' => $notes !== '' ? $notes : $current->customer_notes,
                                'revision' => ((int) $current->revision) + 1,
                                'updated_at' => $now,
                            ]);
                    }
                    if ($summary !== '' && Schema::hasColumn('calls', 'summary')) {
                        DB::table('calls')
                            ->where('account_id', $accountId)
                            ->where('device_binding_id', $deviceId)
                            ->where('external_call_id', $id)
                            ->where(function ($q): void {
                                $q->whereNull('summary')
                                    ->orWhere('summary', '')
                                    ->orWhere('summary', 'like', 'ملخص أولي غير مؤكد:%')
                                    ->orWhere('summary', 'like', 'تعذر التلخيص الآلي%');
                            })
                            ->update(['summary' => $summary]);
                    }
                }
            } elseif ($phase === 'identified') {
                $phone = (string) ($p['phone_number'] ?? '');
                $name = trim((string) ($p['contact_name'] ?? ''));
                if ($phone !== '' && mb_strlen($name) <= 100 && preg_match('/^\+?[0-9 ()-]{8,30}$/D', $phone) === 1) {
                    if ($name === '') {
                        $known = DB::table('contacts')
                            ->where('account_id', $accountId)
                            ->where('site_id', $siteId)
                            ->where('phone', $phone)
                            ->limit(2)
                            ->pluck('name')
                            ->all();
                        if (count($known) === 1) {
                            $name = (string) $known[0];
                        }
                    }
                    DB::table('calls')
                        ->where('device_binding_id', $deviceId)
                        ->where('external_call_id', $id)
                        ->update(['phone' => $phone, 'name' => $name]);
                }
            } else {
                $call = DB::table('calls')
                    ->where('account_id', $accountId)
                    ->where('site_id', $siteId)
                    ->where('device_binding_id', $deviceId)
                    ->where('sim_slot', $simSlot)
                    ->where('external_call_id', $id)
                    ->first(['status', 'duration_seconds', 'ended_at']);

                $current = (string) ($call->status ?? 'ringing');
                $terminal = in_array($current, ['ended', 'missed', 'busy', 'completed'], true);
                $regression = ($current === 'active' && $phase === 'ringing');
                $nextDur = max((int) ($call->duration_seconds ?? 0), $duration);

                if (! $terminal && ! $regression) {
                    $upd = ['status' => $phase, 'duration_seconds' => $nextDur];
                    if ($phase === 'ended' && empty($call->ended_at)) {
                        $upd['ended_at'] = $now;
                    }
                    DB::table('calls')
                        ->where('account_id', $accountId)
                        ->where('site_id', $siteId)
                        ->where('device_binding_id', $deviceId)
                        ->where('sim_slot', $simSlot)
                        ->where('external_call_id', $id)
                        ->update($upd);
                } elseif ($duration > 0) {
                    DB::table('calls')
                        ->where('account_id', $accountId)
                        ->where('site_id', $siteId)
                        ->where('device_binding_id', $deviceId)
                        ->where('sim_slot', $simSlot)
                        ->where('external_call_id', $id)
                        ->update(['duration_seconds' => $nextDur]);
                }
            }

            DB::table('tenant_call_events')->insert([
                'device_binding_id' => $deviceId,
                'call_id' => $id,
                'sequence' => $seq,
                'created_at' => $now,
            ]);

            if (in_array($phase, ['active', 'ended'], true)) {
                $this->calls->updateUsageOnCallPhase($binding, $id, $phase);
            }

            return false;
        });

        if (! $duplicate && $phase === 'ended') {
            $callRow = DB::table('calls')->where('device_binding_id', $deviceId)->where('external_call_id', $id)->first();
            $existingRep = DB::table('tenant_call_reports')
                ->where('account_id', $accountId)
                ->where('site_id', $siteId)
                ->where('device_binding_id', $deviceId)
                ->where('call_id', $id)
                ->first();
            $needsSummary = ! $existingRep
                || trim((string) $existingRep->summary) === ''
                || str_starts_with((string) $existingRep->summary, 'ملخص أولي غير مؤكد:')
                || str_starts_with((string) $existingRep->summary, 'تعذر التلخيص الآلي');

            if ($needsSummary) {
                $txRow = DB::table('tenant_call_transcripts')
                    ->where('account_id', $accountId)
                    ->where('site_id', $siteId)
                    ->where('device_binding_id', $deviceId)
                    ->where('sim_slot', $simSlot)
                    ->where('call_id', $id)
                    ->first();

                if ($txRow && ! empty($txRow->transcript)) {
                    $plainTx = trim($this->vault->unsealTranscript((string) $txRow->transcript));
                    if (mb_strlen($plainTx, 'UTF-8') >= 18) {
                        try {
                            $this->voiceSession->analyzeCallTranscript(
                                $binding,
                                $id,
                                $plainTx,
                                fn (array $reportInput) => $this->calls->saveReport($accountId, $siteId, $deviceId, $reportInput['call_id'], $reportInput, 'device')
                            );
                        } catch (Throwable) {
                        }
                    }
                } elseif ($callRow && Schema::hasColumn('calls', 'summary') && trim((string) ($callRow->summary ?? '')) === '') {
                    $tlLines = DB::table('voice_call_timeline')
                        ->where('account_id', $accountId)
                        ->where('device_binding_id', $deviceId)
                        ->where('call_id', $id)
                        ->orderBy('occurred_at')
                        ->orderBy('id')
                        ->get(['speaker', 'content'])
                        ->map(fn ($t) => ($t->speaker === 'caller' ? 'المتصل' : 'الإيجنت').': '.$t->content)
                        ->all();

                    if (! empty($tlLines)) {
                        $tlFallback = $this->voiceSession->buildExtractiveFallbackReport(implode("\n", $tlLines));
                        DB::table('calls')->where('id', $callRow->id)->update(['summary' => $tlFallback['summary']]);
                    } else {
                        DB::table('calls')->where('id', $callRow->id)->update([
                            'summary' => 'مكالمة انتهت بدون تفريغ نصي مسجل ('.((int) ($callRow->duration_seconds ?? $duration)).' ثانية).',
                        ]);
                    }
                }
            }

            $escResult = $this->calls->scheduleHumanEscalation($binding, $id);
            $callRow = DB::table('calls')->where('device_binding_id', $deviceId)->where('external_call_id', $id)->first();
            $this->automation->evaluateAndExecute($accountId, 'call.ended', [
                'call_id' => $id,
                'phone' => (string) ($callRow->phone ?? ''),
                'name' => (string) ($callRow->name ?? ''),
                'agent_id' => (int) $binding['agent_id'],
                'status' => 'ended',
                'duration_seconds' => (int) ($callRow->duration_seconds ?? $duration),
                'ai_mood' => (string) ($callRow->ai_mood ?? ''),
            ]);

            $cbConfig = $this->calls->resolveManagerCallbackConfig($accountId, $siteId, (int) ($callRow->agent_id ?? $binding['agent_id']));

            return [
                'accepted' => true,
                'duplicate' => $duplicate,
                'call_id' => $id,
                'status' => $phase,
                'escalation' => $escResult,
                'manager_callback' => [
                    'enabled' => $cbConfig['enabled'],
                    'phone' => $cbConfig['phone'],
                    'delay_seconds' => $cbConfig['delay_seconds'],
                    'queued' => (bool) ($escResult['queued'] ?? false),
                ],
            ];
        }

        return ['accepted' => true, 'duplicate' => $duplicate, 'call_id' => $id, 'status' => $phase];
    }

    private function handleLegacyCallEvent(array $binding, array $data): array
    {
        $callId = trim((string) ($data['call_id'] ?? ''));
        if ($callId === '') {
            throw new InvalidArgumentException('missing_call_id');
        }
        $accountId = (int) $binding['account_id'];
        $siteId = (int) $binding['site_id'];
        $deviceId = (int) $binding['id'];
        $status = (string) ($data['status'] ?? 'ringing');
        $phone = trim((string) ($data['phone'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $duration = max(0, (int) ($data['duration_seconds'] ?? 0));
        $direction = in_array($data['direction'] ?? 'inbound', ['inbound', 'outbound'], true) ? ($data['direction'] ?? 'inbound') : 'inbound';
        $now = $this->ctx->now();

        $existing = DB::table('calls')
            ->where('device_binding_id', $deviceId)
            ->where('external_call_id', $callId)
            ->first();

        if (! $existing) {
            DB::table('calls')->insert([
                'account_id' => $accountId,
                'site_id' => $siteId,
                'device_binding_id' => $deviceId,
                'external_call_id' => $callId,
                'sim_slot' => (int) $binding['sim_slot'],
                'agent_id' => (int) $binding['agent_id'],
                'agent_name' => (string) ($binding['agent_name'] ?? ''),
                'phone' => $phone,
                'name' => $name,
                'direction' => $direction,
                'status' => $status,
                'duration_seconds' => $duration,
                'caller_gender' => $data['caller_gender'] ?? null,
                'ai_rating' => $data['ai_rating'] ?? null,
                'ai_mood' => $data['ai_mood'] ?? null,
                'is_demo' => 0,
                'created_at' => $now,
                'ended_at' => in_array($status, ['ended', 'completed', 'missed', 'rejected'], true) ? $now : null,
            ]);
        } else {
            DB::table('calls')->where('id', $existing->id)->update([
                'status' => $status,
                'duration_seconds' => max((int) $existing->duration_seconds, $duration),
                'name' => $name !== '' ? $name : $existing->name,
                'caller_gender' => $data['caller_gender'] ?? $existing->caller_gender,
                'ai_rating' => $data['ai_rating'] ?? $existing->ai_rating,
                'ai_mood' => $data['ai_mood'] ?? $existing->ai_mood,
                'ended_at' => in_array($status, ['ended', 'completed', 'missed', 'rejected'], true) ? $now : $existing->ended_at,
            ]);
        }

        if (in_array($status, ['ended', 'completed'], true)) {
            $this->automation->evaluateAndExecute($accountId, 'call.ended', [
                'call_id' => $callId,
                'phone' => $phone ?: (string) ($existing->phone ?? ''),
                'name' => $name ?: (string) ($existing->name ?? ''),
                'agent_id' => (int) $binding['agent_id'],
                'status' => $status,
                'duration_seconds' => $duration,
                'ai_mood' => (string) ($data['ai_mood'] ?? ''),
            ]);
        }

        return ['ok' => true, 'call_id' => $callId, 'status' => $status];
    }

    private function normalizeVoicePhone(string $raw): string
    {
        $phone = preg_replace('/[\s().-]+/u', '', trim($raw));
        if (! is_string($phone) || preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone) !== 1) {
            throw new InvalidArgumentException('invalid_phone');
        }

        return $phone;
    }

    private function voiceRoleScopes(string $role): array
    {
        return match ($role) {
            'owner', 'admin', 'manager' => ['general', 'tickets', 'bookings', 'finance', 'websites', 'operations'],
            'operator', 'reception' => ['general', 'bookings', 'tickets'],
            'finance' => ['general', 'finance'],
            'support' => ['general', 'tickets'],
            'viewer' => ['general'],
            default => [],
        };
    }

    private function authenticateStaffCaller(int $accountId, string $rawPhone, string $pin): ?array
    {
        $phone = $this->normalizeVoicePhone($rawPhone);
        if (preg_match('/^[0-9]{6,10}$/D', $pin) !== 1) {
            return null;
        }
        $now = $this->ctx->now();
        $row = DB::table('voice_staff_numbers as v')
            ->join('memberships as m', function ($join): void {
                $join->on('m.account_id', '=', 'v.account_id')->on('m.user_id', '=', 'v.user_id');
            })
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->join('accounts as a', 'a.id', '=', 'v.account_id')
            ->where('v.account_id', $accountId)
            ->where('v.phone_e164', $phone)
            ->where('v.status', 'active')
            ->where('u.status', 'active')
            ->where('a.status', 'active')
            ->select(['v.*', 'm.role', 'u.name'])
            ->first();

        if (! $row || ($row->locked_until !== null && (string) $row->locked_until > $now)) {
            return null;
        }
        if (! password_verify($pin, (string) $row->pin_hash)) {
            $attempts = ((int) $row->failed_attempts) + 1;
            $lock = $attempts >= 5 ? gmdate('Y-m-d H:i:s', time() + 900) : null;
            DB::table('voice_staff_numbers')->where('id', $row->id)->update([
                'failed_attempts' => $attempts,
                'locked_until' => $lock,
                'updated_at' => $now,
            ]);

            return null;
        }

        DB::table('voice_staff_numbers')->where('id', $row->id)->update([
            'failed_attempts' => 0,
            'locked_until' => null,
            'updated_at' => $now,
        ]);

        $stored = json_decode((string) $row->scopes_json, true);
        $scopes = array_values(array_intersect(is_array($stored) ? $stored : [], $this->voiceRoleScopes((string) $row->role)));

        return [
            'account_id' => $accountId,
            'user_id' => (int) $row->user_id,
            'number_id' => (int) $row->id,
            'role' => (string) $row->role,
            'name' => (string) $row->name,
            'scopes' => $scopes,
        ];
    }

    private function visibleStaffFacts(array $principal, int $limit = 45): array
    {
        $accountId = (int) ($principal['account_id'] ?? 0);
        $userId = (int) ($principal['user_id'] ?? 0);
        $numberId = (int) ($principal['number_id'] ?? 0);
        if ($accountId < 1 || $userId < 1 || $numberId < 1) {
            return [];
        }

        $live = DB::table('voice_staff_numbers as v')
            ->join('memberships as m', function ($join): void {
                $join->on('m.account_id', '=', 'v.account_id')->on('m.user_id', '=', 'v.user_id');
            })
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->join('accounts as a', 'a.id', '=', 'v.account_id')
            ->where('v.id', $numberId)
            ->where('v.account_id', $accountId)
            ->where('v.user_id', $userId)
            ->where('v.status', 'active')
            ->where('u.status', 'active')
            ->where('a.status', 'active')
            ->select(['v.scopes_json', 'm.role'])
            ->first();

        if (! $live) {
            return [];
        }
        $stored = json_decode((string) $live->scopes_json, true);
        if (! is_array($stored)) {
            return [];
        }

        $scopes = array_values(array_unique(array_intersect(
            array_filter((array) ($principal['scopes'] ?? []), 'is_string'),
            $stored,
            $this->voiceRoleScopes((string) $live->role),
            $this->voiceRoleScopes((string) ($principal['role'] ?? ''))
        )));

        if (empty($scopes)) {
            return [];
        }

        return DB::table('integration_facts as f')
            ->join('integrations as i', function ($join): void {
                $join->on('i.id', '=', 'f.integration_id')->on('i.account_id', '=', 'f.account_id');
            })
            ->where('f.account_id', $accountId)
            ->whereIn('f.category', $scopes)
            ->where('i.status', 'active')
            ->orderByDesc('f.updated_at')
            ->limit(max(1, min($limit, 60)))
            ->get(['f.category', 'f.fact_key', 'f.fact_value', 'f.updated_at', 'i.name as integration_name'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    private function deviceJson(array $data, int $status = 200, array $headers = [])
    {
        return response()->json($data, $status, $headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
