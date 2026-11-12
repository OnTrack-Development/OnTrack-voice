<?php

namespace App\Services\Automation;

use App\Services\Console\ConsoleContextService;
use App\Services\Security\VaultService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PostCallAutomationService
{
    public function __construct(
        private readonly VaultService $vault,
        private readonly ConsoleContextService $ctx,
    ) {}

    public function listRules(int $accountId): array
    {
        return DB::table('automation_rules')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function listWebhookLogs(int $accountId, int $limit = 40): array
    {
        return DB::table('webhook_logs')
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function assertSafeWebhookUrl(string $url, ?callable $dnsResolver = null): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('رابط الـ Webhook غير صالح.');
        }

        $parsed = parse_url($url);
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            throw new InvalidArgumentException('رابط الـ Webhook يجب أن يستخدم HTTPS آمن.');
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'metadata.google.internal'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('ممنوع توجيه الـ Webhook إلى عناوين داخلية أو localhost.');
        }

        // Reject IP literals directly if private/reserved
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('عنوان IP المستهدف خاص أو محجوز (SSRF Blocked).');
            }

            return $url;
        }

        $ips = $dnsResolver ? (array) $dnsResolver($host) : (@gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('النطاق المستهدف يشير إلى عنوان IP داخلي (SSRF Blocked).');
            }
        }

        return $url;
    }

    public function createRule(int $accountId, array $input, ?callable $dnsResolver = null): int
    {
        $name = trim(mb_substr((string) ($input['name'] ?? ''), 0, 150));
        if ($name === '') {
            throw new InvalidArgumentException('اكتب اسماً واضحاً لقاعدة الأتمتة.');
        }

        $triggerEvent = (string) ($input['trigger_event'] ?? 'call.ended');
        if (! in_array($triggerEvent, ['call.ended', 'report.saved', 'handoff.requested', 'dnc.detected'], true)) {
            $triggerEvent = 'call.ended';
        }

        $condField = (string) ($input['condition_field'] ?? 'outcome');
        if (! in_array($condField, ['outcome', 'ai_mood', 'status', 'any'], true)) {
            $condField = 'outcome';
        }
        $condOp = (string) ($input['condition_operator'] ?? 'equals');
        if (! in_array($condOp, ['equals', 'contains', 'any'], true)) {
            $condOp = 'equals';
        }
        $condVal = trim(mb_substr((string) ($input['condition_value'] ?? 'any'), 0, 150));
        if ($condVal === '') {
            $condVal = 'any';
        }

        $actionType = (string) ($input['action_type'] ?? 'webhook');
        if (! in_array($actionType, ['webhook', 'crm_update', 'dnc_suppress', 'whatsapp_template'], true)) {
            throw new InvalidArgumentException('نوع الإجراء غير مدعوم.');
        }

        $webhookUrl = null;
        $secretEnc = null;
        if ($actionType === 'webhook') {
            $webhookUrl = $this->assertSafeWebhookUrl((string) ($input['webhook_url'] ?? ''), $dnsResolver);
            $rawSecret = trim((string) ($input['webhook_secret'] ?? ''));
            if ($rawSecret === '') {
                $rawSecret = 'whsec_'.bin2hex(random_bytes(24));
            }
            $secretEnc = $this->vault->seal($rawSecret);
        }

        $now = $this->ctx->now();

        return (int) DB::table('automation_rules')->insertGetId([
            'account_id' => $accountId,
            'site_id' => ! empty($input['site_id']) ? (int) $input['site_id'] : null,
            'agent_id' => ! empty($input['agent_id']) ? (int) $input['agent_id'] : null,
            'name' => $name,
            'trigger_event' => $triggerEvent,
            'condition_field' => $condField,
            'condition_operator' => $condOp,
            'condition_value' => $condVal,
            'action_type' => $actionType,
            'webhook_url' => $webhookUrl,
            'webhook_secret_enc' => $secretEnc,
            'whatsapp_template' => trim(mb_substr((string) ($input['whatsapp_template'] ?? ''), 0, 1500)) ?: null,
            'crm_status' => trim(mb_substr((string) ($input['crm_status'] ?? ''), 0, 40)) ?: null,
            'enabled' => true,
            'trigger_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function toggleRule(int $accountId, int $ruleId): bool
    {
        $row = DB::table('automation_rules')->where('id', $ruleId)->where('account_id', $accountId)->first();
        if (! $row) {
            throw new InvalidArgumentException('القاعدة غير موجودة.');
        }
        $next = ! ((bool) $row->enabled);
        DB::table('automation_rules')->where('id', $ruleId)->update([
            'enabled' => $next,
            'updated_at' => $this->ctx->now(),
        ]);

        return $next;
    }

    public function deleteRule(int $accountId, int $ruleId): void
    {
        DB::table('automation_rules')->where('id', $ruleId)->where('account_id', $accountId)->delete();
    }

    public function evaluateAndExecute(int $accountId, string $eventType, array $callContext, ?callable $httpTransport = null, ?callable $dnsResolver = null): array
    {
        $rules = DB::table('automation_rules')
            ->where('account_id', $accountId)
            ->where('enabled', true)
            ->where('trigger_event', $eventType)
            ->get();

        $results = [];
        $now = $this->ctx->now();

        foreach ($rules as $rule) {
            if ($rule->agent_id !== null && ! empty($callContext['agent_id']) && (int) $rule->agent_id !== (int) $callContext['agent_id']) {
                continue;
            }
            if (! $this->matchesCondition((array) $rule, $callContext)) {
                continue;
            }

            $res = match ($rule->action_type) {
                'webhook' => $this->dispatchWebhook((array) $rule, $eventType, $callContext, $httpTransport, $dnsResolver),
                'crm_update' => $this->applyCrmUpdate($accountId, (array) $rule, $callContext),
                'dnc_suppress' => $this->applyDnc($accountId, $callContext),
                'whatsapp_template' => $this->enqueueWhatsappOutbox($accountId, (array) $rule, $callContext),
                default => ['ok' => false, 'reason' => 'unknown_action'],
            };

            DB::table('automation_rules')->where('id', $rule->id)->update([
                'trigger_count' => ((int) $rule->trigger_count) + 1,
                'last_triggered_at' => $now,
                'updated_at' => $now,
            ]);

            $results[] = ['rule_id' => $rule->id, 'action' => $rule->action_type, 'result' => $res];
        }

        return $results;
    }

    public function testWebhookRule(int $accountId, int $ruleId, ?callable $httpTransport = null, ?callable $dnsResolver = null): array
    {
        $rule = DB::table('automation_rules')
            ->where('id', $ruleId)
            ->where('account_id', $accountId)
            ->first();

        if (! $rule || $rule->action_type !== 'webhook' || empty($rule->webhook_url)) {
            throw new InvalidArgumentException('قاعدة Webhook غير موجودة.');
        }

        return $this->dispatchWebhook((array) $rule, 'webhook.test', [
            'call_id' => 'test_call_'.bin2hex(random_bytes(4)),
            'phone' => '+201000000000',
            'outcome' => 'booked',
            'ai_rating' => 5,
            'ai_mood' => 'positive',
            'summary' => 'اختبار حي لربط Post-Call Webhook من لوحة IO Voice Enterprise.',
            'test' => true,
        ], $httpTransport, $dnsResolver);
    }

    private function matchesCondition(array $rule, array $ctx): bool
    {
        $val = mb_strtolower(trim((string) ($rule['condition_value'] ?? 'any')), 'UTF-8');
        $field = (string) ($rule['condition_field'] ?? 'any');
        $op = (string) ($rule['condition_operator'] ?? 'any');

        if ($field === 'any' || $op === 'any' || $val === 'any' || $val === '*') {
            return true;
        }
        $actual = mb_strtolower(trim((string) ($ctx[$field] ?? '')), 'UTF-8');
        if ($op === 'contains') {
            return $actual !== '' && str_contains($actual, $val);
        }

        return $actual === $val;
    }

    private function dispatchWebhook(array $rule, string $eventType, array $payloadData, ?callable $httpTransport = null, ?callable $dnsResolver = null): array
    {
        $url = (string) ($rule['webhook_url'] ?? '');
        try {
            $url = $this->assertSafeWebhookUrl($url, $dnsResolver);
        } catch (Throwable $e) {
            DB::table('webhook_logs')->insert([
                'account_id' => (int) $rule['account_id'],
                'automation_rule_id' => (int) $rule['id'],
                'call_id' => (string) ($payloadData['call_id'] ?? ''),
                'event_type' => $eventType,
                'target_url' => $url,
                'payload_json' => '{}',
                'status' => 'blocked',
                'error_message' => mb_substr($e->getMessage(), 0, 240),
                'created_at' => $this->ctx->now(),
            ]);

            return ['ok' => false, 'status' => 'blocked', 'error' => $e->getMessage()];
        }

        $secret = $this->vault->unseal($rule['webhook_secret_enc'] ?? null) ?? '';
        $bodyPayload = json_encode([
            'event' => $eventType,
            'account_id' => (int) $rule['account_id'],
            'timestamp' => gmdate('c'),
            'data' => $payloadData,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $signature = $secret !== '' ? hash_hmac('sha256', $bodyPayload, $secret) : '';
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'IOVoice-Webhook/1.0',
            'X-IOVoice-Event' => $eventType,
            'X-IOVoice-Signature' => 'sha256='.$signature,
        ];

        $started = microtime(true);
        if ($httpTransport !== null) {
            $res = $httpTransport($url, $headers, $bodyPayload);
            $httpStatus = (int) ($res['status'] ?? 200);
            $respBody = (string) ($res['body'] ?? 'OK');
            $errorMsg = $res['error'] ?? null;
        } else {
            [$httpStatus, $respBody, $errorMsg] = $this->curlWebhookPost($url, $headers, $bodyPayload);
        }
        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $ok = $httpStatus >= 200 && $httpStatus < 300 && $errorMsg === null;

        DB::table('webhook_logs')->insert([
            'account_id' => (int) $rule['account_id'],
            'automation_rule_id' => (int) $rule['id'],
            'call_id' => (string) ($payloadData['call_id'] ?? ''),
            'event_type' => $eventType,
            'target_url' => $url,
            'request_headers_json' => json_encode(array_merge($headers, ['X-IOVoice-Signature' => 'sha256=redacted']), JSON_UNESCAPED_SLASHES),
            'payload_json' => $bodyPayload,
            'http_status' => $httpStatus ?: null,
            'response_body' => mb_substr($respBody, 0, 1000),
            'attempt_number' => 1,
            'status' => $ok ? 'delivered' : 'failed',
            'error_message' => $errorMsg ? mb_substr((string) $errorMsg, 0, 240) : null,
            'duration_ms' => max(0, $durationMs),
            'created_at' => $this->ctx->now(),
        ]);

        return ['ok' => $ok, 'http_status' => $httpStatus, 'duration_ms' => $durationMs, 'error' => $errorMsg];
    }

    private function curlWebhookPost(string $url, array $headers, string $body): array
    {
        if (! function_exists('curl_init')) {
            return [0, '', 'curl_unavailable'];
        }
        $flatHeaders = [];
        foreach ($headers as $k => $v) {
            $flatHeaders[] = $k.': '.$v;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $flatHeaders,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch) ?: null;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, is_string($resp) ? $resp : '', $err];
    }

    private function applyCrmUpdate(int $accountId, array $rule, array $ctx): array
    {
        $phoneKey = $this->ctx->dncPhoneKey((string) ($ctx['phone'] ?? ''));
        $newStatus = trim((string) ($rule['crm_status'] ?? 'qualified')) ?: 'qualified';
        if ($phoneKey === '') {
            return ['ok' => false, 'reason' => 'missing_phone'];
        }
        $contacts = DB::table('contacts')->where('account_id', $accountId)->get();
        $updated = 0;
        foreach ($contacts as $c) {
            if ($this->ctx->dncPhoneKey((string) ($c->phone_e164 ?: $c->phone)) === $phoneKey) {
                DB::table('contacts')->where('id', $c->id)->update(['lead_status' => $newStatus]);
                $updated++;
            }
        }

        return ['ok' => true, 'updated' => $updated, 'status' => $newStatus];
    }

    private function applyDnc(int $accountId, array $ctx): array
    {
        $phone = (string) ($ctx['phone'] ?? '');
        $key = $this->ctx->dncPhoneKey($phone);
        if ($key === '') {
            return ['ok' => false, 'reason' => 'missing_phone'];
        }
        DB::table('dnc_suppressions')->insertOrIgnore([
            'account_id' => $accountId,
            'phone_key' => $key,
            'source' => 'post_call_automation',
            'created_at' => $this->ctx->now(),
        ]);

        return ['ok' => true, 'phone_key' => $key];
    }

    private function enqueueWhatsappOutbox(int $accountId, array $rule, array $ctx): array
    {
        DB::table('outbox')->insert([
            'account_id' => $accountId,
            'kind' => 'automation.whatsapp_followup',
            'payload' => json_encode([
                'phone' => (string) ($ctx['phone'] ?? ''),
                'call_id' => (string) ($ctx['call_id'] ?? ''),
                'template' => (string) ($rule['whatsapp_template'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => $this->ctx->now(),
        ]);

        return ['ok' => true, 'queued' => true];
    }
}
