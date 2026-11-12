<?php

namespace App\Services\Mobile;

use App\Services\Console\ConsoleContextService;
use App\Services\Security\VaultService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DevicePairingService
{
    public function __construct(
        private readonly VaultService $vault,
        private readonly ConsoleContextService $ctx,
    ) {}

    public function pairingHash(string $code, int $bucket): string
    {
        return hash_hmac('sha256', 'sim6:'.$bucket.':'.$code, $this->vault->hmacSecret());
    }

    public function createPairing(int $accountId, int $siteId, int $agentId, int $userId, int $simSlot): string
    {
        if (! in_array($simSlot, [0, 1], true)) {
            throw new InvalidArgumentException('رقم الشريحة يجب أن يكون 0 (SIM1) أو 1 (SIM2).');
        }
        $account = $this->ctx->account($accountId);
        $site = DB::table('sites')->where('id', $siteId)->where('account_id', $accountId)->where('status', 'active')->first();
        $agent = DB::table('agents')->where('id', $agentId)->where('account_id', $accountId)->where('site_id', $siteId)->where('status', '!=', 'archived')->first();

        if (! $this->ctx->accountServiceActive($account) || ! $site || ! $agent) {
            throw new InvalidArgumentException('الحساب أو الموقع أو الإيجنت غير نشط.');
        }

        $bucket = intdiv(time(), 300);
        $now = $this->ctx->now();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $digits = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hash = $this->pairingHash($digits, $bucket);
            $older = $this->pairingHash($digits, $bucket - 1);

            $collision = DB::table('device_pairings')
                ->whereIn('code_hash', [$hash, $older])
                ->whereIn('status', ['pending_sim0', 'pending_sim1'])
                ->where('expires_at', '>', $now)
                ->exists();

            if ($collision) {
                continue;
            }

            return DB::transaction(function () use ($accountId, $siteId, $agentId, $userId, $simSlot, $digits, $hash, $now): string {
                DB::table('device_pairings')
                    ->where('account_id', $accountId)
                    ->where('status', 'pending_sim'.$simSlot)
                    ->where('expires_at', '>', $now)
                    ->update(['status' => 'cancelled']);

                $pendingCount = DB::table('device_pairings')
                    ->where('account_id', $accountId)
                    ->whereIn('status', ['pending', 'pending_sim0', 'pending_sim1'])
                    ->where('expires_at', '>', $now)
                    ->count();

                if ($pendingCount >= 5) {
                    throw new InvalidArgumentException('وصلت للحد الأقصى للأكواد المعلقة (5). ألغِ كوداً قديماً أولاً.');
                }

                DB::table('device_pairings')->insert([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'agent_id' => $agentId,
                    'code_hash' => $hash,
                    'status' => 'pending_sim'.$simSlot,
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
                    'created_by' => $userId,
                    'created_at' => $now,
                ]);

                return $digits;
            });
        }

        throw new RuntimeException('تعذر توليد كود ربط فريد، حاول مجدداً.');
    }

    public function rateGuard(string $peerIp, string $installationId): void
    {
        if (filter_var($peerIp, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('device_pairing_network_invalid');
        }
        if (preg_match('/^[a-zA-Z0-9_.:-]{12,128}$/D', $installationId) !== 1) {
            throw new InvalidArgumentException('device_pairing_invalid');
        }

        $window = intdiv(time(), 300);
        foreach ([['ip:'.$peerIp, 20], ['install:'.$installationId, 12], ['global', 300]] as [$subject, $max]) {
            $key = hash_hmac('sha256', 'sim-rate:'.$window.':'.$subject, $this->vault->hmacSecret());
            $existing = DB::table('device_pair_attempts')->where('bucket_hash', $key)->first();
            if ($existing) {
                $next = (int) $existing->attempts + 1;
                DB::table('device_pair_attempts')->where('bucket_hash', $key)->update(['attempts' => $next]);
            } else {
                DB::table('device_pair_attempts')->insertOrIgnore([
                    'bucket_hash' => $key,
                    'window_epoch' => $window,
                    'attempts' => 1,
                ]);
                $next = (int) (DB::table('device_pair_attempts')->where('bucket_hash', $key)->value('attempts') ?? 1);
            }
            if ($next > $max) {
                throw new InvalidArgumentException('device_pairing_rate_limited');
            }
        }
    }

    public function redeemPairing(string $code, string $installationId, int $simSlot, string $deviceLabel): array
    {
        $clean = trim($code);
        $deviceLabel = trim($deviceLabel);
        if (
            preg_match('/^[0-9]{6}$/D', $clean) !== 1
            || preg_match('/^[a-zA-Z0-9_.:-]{12,128}$/D', $installationId) !== 1
            || ! in_array($simSlot, [0, 1], true)
            || $deviceLabel === '' || mb_strlen($deviceLabel) > 100
        ) {
            throw new InvalidArgumentException('device_pairing_invalid');
        }

        $bucket = intdiv(time(), 300);
        $hashes = [$this->pairingHash($clean, $bucket), $this->pairingHash($clean, $bucket - 1)];
        $token = 'otdev_'.bin2hex(random_bytes(32));
        $claim = hash('sha256', $installationId.'|'.$simSlot);
        $now = $this->ctx->now();

        try {
            return DB::transaction(function () use ($hashes, $simSlot, $installationId, $claim, $deviceLabel, $token, $now): array {
                $pair = DB::table('device_pairings')
                    ->whereIn('code_hash', $hashes)
                    ->whereIn('status', ['pending_sim0', 'pending_sim1'])
                    ->where('expires_at', '>', $now)
                    ->first();

                if (! $pair) {
                    throw new InvalidArgumentException('device_pairing_expired_or_used');
                }
                if ($pair->status !== 'pending_sim'.$simSlot) {
                    throw new InvalidArgumentException('device_pairing_slot_mismatch');
                }

                $acct = $this->ctx->account((int) $pair->account_id);
                $site = DB::table('sites')->where('id', $pair->site_id)->where('account_id', $pair->account_id)->where('status', 'active')->first();
                $agent = DB::table('agents')->where('id', $pair->agent_id)->where('account_id', $pair->account_id)->where('site_id', $pair->site_id)->where('status', '!=', 'archived')->first();

                if (! $this->ctx->accountServiceActive($acct) || ! $site || ! $agent) {
                    throw new InvalidArgumentException('device_pairing_target_unavailable');
                }

                // Revoke any prior binding claiming the same installation+slot to prevent UNIQUE constraint collisions on re-pair
                DB::table('device_bindings')
                    ->where('slot_claim', $claim)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => $now,
                        'slot_claim' => null,
                    ]);

                $deviceId = DB::table('device_bindings')->insertGetId([
                    'account_id' => $pair->account_id,
                    'site_id' => $pair->site_id,
                    'agent_id' => $pair->agent_id,
                    'installation_id' => $installationId,
                    'sim_slot' => $simSlot,
                    'slot_claim' => $claim,
                    'device_name' => $deviceLabel,
                    'token_hash' => hash('sha256', $token),
                    'status' => 'active',
                    'created_at' => $now,
                    'last_seen_at' => $now,
                    'agent_revision' => 1,
                ]);

                DB::table('device_agent_revisions')->insert([
                    'device_id' => $deviceId,
                    'account_id' => $pair->account_id,
                    'site_id' => $pair->site_id,
                    'agent_id' => $pair->agent_id,
                    'agent_revision' => 1,
                    'created_at' => $now,
                ]);

                DB::table('device_pairings')
                    ->where('id', $pair->id)
                    ->update([
                        'status' => 'redeemed',
                        'device_id' => $deviceId,
                        'redeemed_at' => $now,
                    ]);

                return [
                    'ok' => true,
                    'device_id' => (int) $deviceId,
                    'device_token' => $token,
                    'account_id' => (int) $pair->account_id,
                    'site_id' => (int) $pair->site_id,
                    'agent_id' => (int) $pair->agent_id,
                    'agent_name' => (string) $agent->name,
                    'sim_slot' => $simSlot,
                    'agent_revision' => 1,
                ];
            });
        } catch (QueryException $e) {
            $code = (string) $e->getCode();
            if (str_starts_with($code, '23') || $code === '19') {
                throw new RuntimeException('sim_slot_already_paired', 0, $e);
            }
            throw $e;
        }
    }

    public function authorizeBearer(?string $header): ?array
    {
        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));
        if (! str_starts_with($token, 'otdev_') || strlen($token) < 20) {
            return null;
        }
        $hash = hash('sha256', $token);
        $binding = DB::table('device_bindings as d')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->join('sites as s', 's.id', '=', 'd.site_id')
            ->leftJoin('agents as ag', 'ag.id', '=', 'd.agent_id')
            ->where('d.token_hash', $hash)
            ->where('d.status', 'active')
            ->where('a.status', 'active')
            ->where('s.status', 'active')
            ->select([
                'd.*',
                'ag.name as agent_name',
                'ag.status as agent_status',
                's.name as site_name',
                'a.name as account_name',
            ])
            ->first();

        if (! $binding) {
            return null;
        }
        $arr = (array) $binding;
        if (! $this->ctx->accountServiceActive($this->ctx->account((int) $arr['account_id']))) {
            return null;
        }

        return $arr;
    }

    public function heartbeat(array $binding): array
    {
        $now = $this->ctx->now();
        DB::table('device_bindings')->where('id', $binding['id'])->update(['last_seen_at' => $now]);

        $fresh = DB::table('device_bindings as d')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->join('sites as s', 's.id', '=', 'd.site_id')
            ->leftJoin('agents as ag', 'ag.id', '=', 'd.agent_id')
            ->where('d.id', $binding['id'])
            ->select(
                'd.*',
                'ag.name as agent_name',
                'ag.status as agent_status',
                's.name as site_name',
                'a.name as account_name'
            )
            ->first();

        $agentId = (int) ($fresh->agent_id ?? $binding['agent_id']);
        $published = DB::table('agent_flow_versions')
            ->where('account_id', (int) $binding['account_id'])
            ->where('site_id', (int) $binding['site_id'])
            ->where('agent_id', $agentId)
            ->orderByDesc('revision')
            ->first();

        $voiceCode = $published ? (string) $published->voice : null;
        $voiceLabel = $voiceCode;
        if ($voiceCode !== null && $voiceCode !== '') {
            $vName = DB::table('voices')->where('code', $voiceCode)->value('name');
            if (is_string($vName) && trim($vName) !== '') {
                $voiceLabel = $vName;
            }
        }
        $policy = null;
        if ($published && ! empty($published->answer_policy_json)) {
            $decoded = json_decode((string) $published->answer_policy_json, true);
            if (is_array($decoded)) {
                unset($decoded['scenario_text']);
                if (isset($decoded['ivr_routes']) && is_array($decoded['ivr_routes'])) {
                    $decoded['ivr_routes'] = array_map(static fn ($rt) => [
                        'digit' => (string) ($rt['digit'] ?? ''),
                        'label' => mb_substr((string) ($rt['label'] ?? ''), 0, 40, 'UTF-8'),
                    ], array_slice($decoded['ivr_routes'], 0, 10));
                }
                $policy = $decoded;
            }
        }

        $readyCampaigns = DB::table('tenant_campaigns')
            ->where('account_id', (int) $binding['account_id'])
            ->where('site_id', (int) $binding['site_id'])
            ->where('device_binding_id', (int) $binding['id'])
            ->where('sim_slot', (int) $binding['sim_slot'])
            ->whereIn('status', ['ready', 'armed'])
            ->orderBy('id')
            ->get(['id', 'name', 'contact_ids_json']);

        $activeCampaignName = $readyCampaigns->first()?->name ?? '';
        $readyCampaignCount = $readyCampaigns->count();

        return [
            'ok' => true,
            'flow_revision' => $published ? (int) $published->revision : null,
            'flow_hash' => $published ? (string) $published->flow_hash : null,
            'flow_ready' => $published !== null,
            'device_id' => (int) $binding['id'],
            'account_id' => (int) $binding['account_id'],
            'account_name' => (string) ($fresh->account_name ?? $binding['account_name'] ?? ''),
            'site_id' => (int) $binding['site_id'],
            'site_name' => (string) ($fresh->site_name ?? $binding['site_name'] ?? ''),
            'agent_id' => $agentId,
            'agent_name' => (string) ($fresh->agent_name ?? ''),
            'agent_revision' => (int) ($fresh->agent_revision ?? 1),
            'voice' => $voiceCode,
            'voice_label' => $voiceLabel,
            'instruction' => $published ? mb_substr((string) $published->instructions, 0, 500, 'UTF-8') : null,
            'answer_policy' => $policy,
            'campaigns_summary' => [
                'ready_count' => $readyCampaignCount,
                'active_name' => mb_substr((string) $activeCampaignName, 0, 80, 'UTF-8'),
                'dial_enabled' => (bool) config('ontrack.campaign_dial_enabled', false) || getenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED') === '1',
            ],
            'sim_slot' => (int) $binding['sim_slot'],
            'heartbeat_seconds' => 10,
            'last_seen_at' => $now,
            'transport' => 'laravel_enterprise_v1',
        ];
    }

    /**
     * Hot-swap agent assigned to an already-paired SIM WITHOUT changing its token_hash or slot_claim.
     */
    public function changeAgent(int $accountId, int $deviceId, int $newAgentId): array
    {
        $device = DB::table('device_bindings')
            ->where('id', $deviceId)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->first();

        if (! $device) {
            throw new InvalidArgumentException('الشريحة غير موجودة أو تم فك ربطها.');
        }

        $agent = DB::table('agents')
            ->where('id', $newAgentId)
            ->where('account_id', $accountId)
            ->where('site_id', $device->site_id)
            ->where('status', '!=', 'archived')
            ->first();

        if (! $agent) {
            throw new InvalidArgumentException('الإيجنت المختار غير متاح أو لا يتبع نفس الموقع.');
        }

        if ((int) $device->agent_id === $newAgentId) {
            return [
                'device_id' => $deviceId,
                'agent_id' => $newAgentId,
                'agent_revision' => (int) $device->agent_revision,
                'unchanged' => true,
            ];
        }

        return DB::transaction(function () use ($device, $deviceId, $accountId, $newAgentId): array {
            $nextRevision = ((int) $device->agent_revision) + 1;
            $now = $this->ctx->now();

            DB::table('device_bindings')->where('id', $deviceId)->where('account_id', $accountId)->update([
                'agent_id' => $newAgentId,
                'agent_revision' => $nextRevision,
            ]);

            DB::table('device_agent_revisions')->insert([
                'device_id' => $deviceId,
                'account_id' => $accountId,
                'site_id' => (int) $device->site_id,
                'agent_id' => $newAgentId,
                'agent_revision' => $nextRevision,
                'created_at' => $now,
            ]);

            return [
                'device_id' => $deviceId,
                'agent_id' => $newAgentId,
                'agent_revision' => $nextRevision,
                'unchanged' => false,
            ];
        });
    }

    public function revokeDevice(int $accountId, int $deviceId): bool
    {
        $affected = DB::table('device_bindings')
            ->where('id', $deviceId)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_at' => $this->ctx->now(),
                'slot_claim' => null,
            ]);

        return $affected === 1;
    }

    public function cancelPairing(int $accountId, int $pairingId): void
    {
        DB::table('device_pairings')
            ->where('id', $pairingId)
            ->where('account_id', $accountId)
            ->whereIn('status', ['pending', 'pending_sim0', 'pending_sim1'])
            ->update(['status' => 'cancelled']);
    }
}
