<?php

namespace App\Services\Campaigns;

use App\Services\Console\ConsoleContextService;
use App\Services\Mobile\CallLedgerService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class CampaignDialerService
{
    public function __construct(
        private readonly ConsoleContextService $ctx,
        private readonly CallLedgerService $calls,
    ) {}

    public function listCampaigns(int $accountId, int $limit = 30): array
    {
        $rows = DB::table('tenant_campaigns as c')
            ->leftJoin('device_bindings as d', 'd.id', '=', 'c.device_binding_id')
            ->where('c.account_id', $accountId)
            ->orderByDesc('c.created_at')
            ->limit(max(1, min(100, $limit)))
            ->select('c.*', 'd.device_name')
            ->get();

        return $rows->map(function ($r) {
            $arr = (array) $r;
            $ids = json_decode((string) $r->contact_ids_json, true);
            $arr['target_count'] = is_array($ids) ? count($ids) : 0;
            $arr['starts_at_cairo'] = $this->calls->formatCairo((string) ($r->starts_at ?? ''));
            $arr['ends_at_cairo'] = $this->calls->formatCairo((string) ($r->ends_at ?? ''));
            $arr['attempts_done'] = DB::table('tenant_campaign_attempts')
                ->where('campaign_id', $r->id)
                ->whereIn('status', ['finished', 'completed', 'failed', 'skipped_dnc'])
                ->count();

            return $arr;
        })->all();
    }

    public function addContactsToCampaign(int $accountId, int $siteId, string $campaignId, array $input): int
    {
        $campaign = DB::table('tenant_campaigns')
            ->where('id', $campaignId)
            ->where('account_id', $accountId)
            ->first();

        if (! $campaign) {
            throw new InvalidArgumentException('الحملة المطلوبة غير موجودة أو لا تتبع هذا الحساب.');
        }

        $targetSiteId = (int) ($campaign->site_id ?: $siteId);
        $now = $this->ctx->now();
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['contact_ids'] ?? [])))));

        $validSelected = [];
        if (! empty($selectedIds)) {
            $rows = DB::table('contacts')
                ->where('account_id', $accountId)
                ->whereIn('id', $selectedIds)
                ->where('dnc', 0)
                ->get(['id', 'phone']);
            foreach ($rows as $row) {
                $key = $this->ctx->dncPhoneKey((string) $row->phone);
                if (strlen($key) < 8) {
                    continue;
                }
                $suppressed = DB::table('dnc_suppressions')->where('account_id', $accountId)->where('phone_key', $key)->exists();
                if ($suppressed) {
                    continue;
                }
                DB::table('tenant_campaign_consents')->insertOrIgnore([
                    'account_id' => $accountId,
                    'site_id' => $targetSiteId,
                    'contact_id' => (int) $row->id,
                    'phone_key' => $key,
                    'method' => 'campaign_upload',
                    'proof_hash' => hash('sha256', $accountId.'|'.((int) $row->id).'|'.$key),
                    'created_at' => $now,
                ]);
                $validSelected[] = (int) $row->id;
            }
        }

        if (! empty($input['raw_contacts'])) {
            $imported = $this->importContactsBulk($accountId, $targetSiteId, (string) $input['raw_contacts'], true);
            $validSelected = array_values(array_unique(array_merge($validSelected, $imported['contact_ids'])));
        }

        if (empty($validSelected)) {
            throw new InvalidArgumentException('اختر جهة اتصال صالحة أو أضف أرقاماً جديدة (غير مدرجة في DNC).');
        }

        $existing = json_decode((string) $campaign->contact_ids_json, true);
        $existingIds = is_array($existing) ? array_values(array_filter(array_map('intval', $existing))) : [];
        $merged = array_values(array_unique(array_merge($existingIds, $validSelected)));

        DB::table('tenant_campaigns')
            ->where('id', $campaignId)
            ->where('account_id', $accountId)
            ->update(['contact_ids_json' => json_encode($merged, JSON_THROW_ON_ERROR)]);

        return count($validSelected);
    }

    public function importContactsBulk(int $accountId, int $siteId, string $rawLines, bool $withConsent = true): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawLines)) ?: [];
        $addedIds = [];
        $skippedDnc = 0;
        $skippedInvalid = 0;
        $now = $this->ctx->now();

        $suppressedKeys = DB::table('dnc_suppressions')
            ->where('account_id', $accountId)
            ->pluck('phone_key')
            ->all();
        $dncMap = array_flip($suppressedKeys);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with(strtolower($line), 'name,')) {
                continue;
            }

            // Support formats: "Phone" OR "Name,Phone" OR "Phone;Name" OR TAB-separated
            $parts = preg_split('/[,;\t]+/', $line);
            $name = '';
            $rawPhone = '';
            if (count($parts) === 1) {
                $rawPhone = trim($parts[0]);
            } else {
                $firstDigits = preg_replace('/[^0-9]/', '', $parts[0]);
                $secondDigits = preg_replace('/[^0-9]/', '', $parts[1]);
                if (strlen($secondDigits) >= 7) {
                    $name = trim($parts[0]);
                    $rawPhone = trim($parts[1]);
                } else {
                    $rawPhone = trim($parts[0]);
                    $name = trim($parts[1]);
                }
            }

            $key = $this->ctx->dncPhoneKey($rawPhone);
            if (strlen($key) < 8 || strlen($key) > 15) {
                $skippedInvalid++;
                continue;
            }
            if (isset($dncMap[$key])) {
                $skippedDnc++;
                continue;
            }

            $e164 = '+'.$key;
            $existing = DB::table('contacts')
                ->where('account_id', $accountId)
                ->where(function ($q) use ($e164, $rawPhone): void {
                    $q->where('phone_e164', $e164)->orWhere('phone', $rawPhone);
                })
                ->first();

            if ($existing) {
                $contactId = (int) $existing->id;
            } else {
                $displayName = $name !== '' ? mb_substr($name, 0, 150) : $e164;
                $contactId = (int) DB::table('contacts')->insertGetId([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'name' => $displayName,
                    'display_name' => $displayName,
                    'phone' => $e164,
                    'phone_e164' => $e164,
                    'dnc' => 0,
                    'lead_status' => 'new',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($withConsent) {
                DB::table('tenant_campaign_consents')->insertOrIgnore([
                    'account_id' => $accountId,
                    'site_id' => $siteId,
                    'contact_id' => $contactId,
                    'phone_key' => $key,
                    'method' => 'campaign_upload',
                    'proof_hash' => hash('sha256', $accountId.'|'.$contactId.'|'.$key),
                    'created_at' => $now,
                ]);
            }

            $addedIds[$contactId] = true;
        }

        return [
            'contact_ids' => array_keys($addedIds),
            'imported_count' => count($addedIds),
            'skipped_dnc' => $skippedDnc,
            'skipped_invalid' => $skippedInvalid,
        ];
    }

    public function createCampaign(int $accountId, int $siteId, array $input): string
    {
        $name = trim(mb_substr((string) ($input['name'] ?? ''), 0, 150));
        $deviceId = (int) ($input['device_binding_id'] ?? 0);
        $scenario = trim(mb_substr((string) ($input['scenario'] ?? ''), 0, 4000));

        if ($name === '' || $deviceId < 1 || $scenario === '') {
            throw new InvalidArgumentException('أكمل اسم الحملة، اختيار الشريحة النشطة، وسيناريو المكالمة.');
        }

        $binding = DB::table('device_bindings')
            ->where('id', $deviceId)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->first();

        if (! $binding) {
            throw new InvalidArgumentException('الشريحة المختارة غير نشطة أو لا تتبع الحساب.');
        }

        $rawSelectedIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['contact_ids'] ?? [])))));
        $contactIds = [];
        if (! empty($rawSelectedIds)) {
            $selectedRows = DB::table('contacts')
                ->where('account_id', $accountId)
                ->whereIn('id', $rawSelectedIds)
                ->where('dnc', 0)
                ->get(['id', 'phone']);
            $suppressedKeys = array_flip(DB::table('dnc_suppressions')->where('account_id', $accountId)->pluck('phone_key')->all());
            $now = $this->ctx->now();
            foreach ($selectedRows as $row) {
                $key = $this->ctx->dncPhoneKey((string) $row->phone);
                if (strlen($key) < 8 || isset($suppressedKeys[$key])) {
                    continue;
                }
                DB::table('tenant_campaign_consents')->insertOrIgnore([
                    'account_id' => $accountId,
                    'site_id' => (int) $binding->site_id,
                    'contact_id' => (int) $row->id,
                    'phone_key' => $key,
                    'method' => 'campaign_upload',
                    'proof_hash' => hash('sha256', $accountId.'|'.((int) $row->id).'|'.$key),
                    'created_at' => $now,
                ]);
                $contactIds[] = (int) $row->id;
            }
        }
        if (! empty($input['raw_contacts'])) {
            $imported = $this->importContactsBulk($accountId, (int) $binding->site_id, (string) $input['raw_contacts'], true);
            $contactIds = array_values(array_unique(array_merge($contactIds, $imported['contact_ids'])));
        }

        if (empty($contactIds)) {
            throw new InvalidArgumentException('أضف أو اختر جهة اتصال صالحة واحدة على الأقل (غير مدرجة في DNC).');
        }

        $startsAt = $this->calls->cairoLocalToUtc($input['starts_at'] ?? null, '0 minutes');
        $endsAt = $this->calls->cairoLocalToUtc($input['ends_at'] ?? null, '+7 days');
        $campaignId = bin2hex(random_bytes(12));

        DB::table('tenant_campaigns')->insert([
            'id' => $campaignId,
            'account_id' => $accountId,
            'site_id' => (int) $binding->site_id,
            'device_binding_id' => $deviceId,
            'sim_slot' => (int) $binding->sim_slot,
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'scenario' => $scenario,
            'contact_ids_json' => json_encode($contactIds, JSON_THROW_ON_ERROR),
            'status' => ! empty($input['arm_now']) ? 'ready' : 'draft',
            'created_at' => $this->ctx->now(),
        ]);

        return $campaignId;
    }

    public function transitionStatus(int $accountId, string $campaignId, string $nextStatus): void
    {
        $allowed = ['ready', 'armed', 'paused', 'cancelled'];
        if (! in_array($nextStatus, $allowed, true)) {
            throw new InvalidArgumentException('حالة حملة غير صالحة.');
        }

        $affected = DB::table('tenant_campaigns')
            ->where('id', $campaignId)
            ->where('account_id', $accountId)
            ->update(['status' => $nextStatus]);

        if ($affected === 0) {
            throw new InvalidArgumentException('الحملة غير موجودة.');
        }
    }

    public function opaqueId(int $id): string
    {
        return str_pad(dechex($id), 24, '0', STR_PAD_LEFT);
    }

    public function parseOpaqueId(mixed $raw): int
    {
        if (! is_string($raw) || preg_match('/^[a-f0-9]{24}$/D', $raw) !== 1) {
            throw new InvalidArgumentException('invalid_id');
        }
        $value = hexdec(ltrim($raw, '0') ?: '0');
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException('invalid_id');
        }

        return $value;
    }

    public function parseContactsFile(string $tmp, string $filename): array
    {
        $size = @filesize($tmp);
        if ($size === false || $size > 1024 * 1024 || $size <= 0) {
            throw new InvalidArgumentException('invalid_file_size');
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
            throw new InvalidArgumentException('file_type');
        }
        if ($ext !== 'xlsx') {
            $lines = @file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                throw new InvalidArgumentException('cannot_read_file');
            }
            $rows = [];
            foreach ($lines as $line) {
                $line = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $line));
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, ',') || str_contains($line, ';')) {
                    $delim = (str_contains($line, ';') && ! str_contains($line, ',')) ? ';' : ',';
                    $parts = str_getcsv($line, $delim);
                    $rows[] = ['name' => (string) ($parts[0] ?? ''), 'phone' => (string) ($parts[1] ?? '')];
                } else {
                    $rows[] = ['name' => '', 'phone' => $line];
                }
                if (count($rows) > 2000) {
                    throw new InvalidArgumentException('too_many_contacts');
                }
            }

            return $rows;
        }

        if (! class_exists(ZipArchive::class) || ! class_exists('SimpleXMLElement')) {
            throw new RuntimeException('xlsx_parser_extension_missing');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new InvalidArgumentException('xlsx_invalid_archive');
        }
        try {
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false || strlen($sheet) > 1500000) {
                throw new InvalidArgumentException('xlsx_sheet_unavailable');
            }
            $strings = [];
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            if ($shared !== false) {
                if (strlen($shared) > 1000000) {
                    throw new InvalidArgumentException('xlsx_strings_too_large');
                }
                $xml = @simplexml_load_string($shared, 'SimpleXMLElement', LIBXML_NONET);
                if ($xml === false) {
                    throw new InvalidArgumentException('xlsx_strings_invalid');
                }
                foreach ($xml->si as $si) {
                    $s = (string) $si->t;
                    foreach ($si->r as $r) {
                        $s .= (string) $r->t;
                    }
                    $strings[] = $s;
                }
            }
            $xml = @simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml === false) {
                throw new InvalidArgumentException('xlsx_sheet_invalid');
            }
            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    if (preg_match('/^([A-Z]{1,3})[0-9]{1,7}$/D', $ref, $match) !== 1) {
                        continue;
                    }
                    if ((string) $cell['t'] === 'e' || (isset($cell->f) && $cell->f->count() > 0)) {
                        continue;
                    }
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = (string) ($strings[(int) $value] ?? '');
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $value = (string) $cell->is->t;
                    }
                    $cells[$match[1]] = $value;
                }
                $rows[] = ['name' => (string) ($cells['A'] ?? ''), 'phone' => (string) ($cells['B'] ?? '')];
                if (count($rows) > 2000) {
                    throw new InvalidArgumentException('too_many_contacts');
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    public function deviceContactsList(array $binding): array
    {
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        $rows = DB::table('contacts')
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->orderByDesc('id')
            ->limit(250)
            ->get(['id', 'name', 'phone', 'dnc']);

        $contacts = [];
        foreach ($rows as $r) {
            $contacts[] = [
                'id' => $this->opaqueId((int) $r->id),
                'name' => (string) ($r->name ?? ''),
                'phone' => (string) ($r->phone ?? ''),
                'do_not_call' => (int) ($r->dnc ?? 0) === 1,
            ];
        }

        $total = DB::table('contacts')->where('account_id', $a)->where('site_id', $s)->count();

        return ['contacts' => $contacts, 'total' => $total];
    }

    public function deviceImportContacts(array $binding, array $rows): array
    {
        if (count($rows) > 2000) {
            throw new InvalidArgumentException('too_many_contacts');
        }
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        $now = $this->ctx->now();

        $known = [];
        foreach (DB::table('contacts')->where('account_id', $a)->where('site_id', $s)->pluck('phone') as $p) {
            $known[$this->ctx->dncPhoneKey((string) $p)] = true;
        }

        $suppressed = array_flip(
            DB::table('dnc_suppressions')->where('account_id', $a)->pluck('phone_key')->all()
        );

        $counts = ['added' => 0, 'duplicates' => 0, 'invalid' => 0];
        foreach ($rows as $r) {
            if (! is_array($r) || ! is_string($r['phone'] ?? null) || ! is_string($r['name'] ?? null)) {
                ++$counts['invalid'];
                continue;
            }
            $phone = trim($r['phone']);
            $name = trim($r['name']);
            if (mb_strlen($name) > 120 || preg_match('/^\+?[0-9 ()-]{8,30}$/D', $phone) !== 1) {
                ++$counts['invalid'];
                continue;
            }
            $key = $this->ctx->dncPhoneKey($phone);
            if (strlen($key) < 8) {
                ++$counts['invalid'];
                continue;
            }
            if (isset($known[$key])) {
                ++$counts['duplicates'];
                continue;
            }
            $dnc = isset($suppressed[$key]) ? 1 : 0;
            $displayName = $name !== '' ? $name : $phone;
            DB::table('contacts')->insert([
                'account_id' => $a,
                'site_id' => $s,
                'name' => $name,
                'display_name' => $displayName,
                'phone' => $phone,
                'phone_e164' => '+'.$key,
                'dnc' => $dnc,
                'lead_status' => 'new',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $known[$key] = true;
            ++$counts['added'];
        }

        return $counts;
    }

    public function deviceSetDnc(array $binding, mixed $id, bool $blocked): array
    {
        $contactId = $this->parseOpaqueId($id);
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];

        $row = DB::table('contacts')
            ->where('id', $contactId)
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->first(['id', 'phone']);

        if (! $row) {
            throw new InvalidArgumentException('contact_unavailable');
        }
        if (! $blocked) {
            throw new InvalidArgumentException('verified_reconsent_required');
        }

        $key = $this->ctx->dncPhoneKey((string) $row->phone);
        if ($key !== '') {
            DB::table('dnc_suppressions')->insertOrIgnore([
                'account_id' => $a,
                'phone_key' => $key,
                'source' => 'device_dnc',
                'created_at' => $this->ctx->now(),
            ]);
        }
        DB::table('contacts')
            ->where('id', $contactId)
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->update(['dnc' => 1]);

        return ['updated' => true, 'do_not_call' => true];
    }

    public function deviceRecordConsent(array $binding, string $opaque, string $method, string $evidence): array
    {
        $contactId = $this->parseOpaqueId($opaque);
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];

        $row = DB::table('contacts')
            ->where('id', $contactId)
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->first(['id', 'phone', 'dnc']);

        if (! $row) {
            throw new InvalidArgumentException('contact_unavailable');
        }
        if (
            ! in_array($method, ['verbal', 'form', 'written', 'owner_test'], true)
            || mb_strlen(trim($evidence)) < 8
            || mb_strlen($evidence) > 240
        ) {
            throw new InvalidArgumentException('consent_proof_required');
        }

        $phoneKey = $this->ctx->dncPhoneKey((string) $row->phone);
        $suppressed = $phoneKey !== '' && DB::table('dnc_suppressions')
            ->where('account_id', $a)
            ->where('phone_key', $phoneKey)
            ->exists();

        if ((int) ($row->dnc ?? 0) === 1 || $suppressed) {
            throw new InvalidArgumentException('dnc_cannot_be_overridden');
        }
        if (strlen($phoneKey) < 8) {
            throw new InvalidArgumentException('consent_phone_invalid');
        }

        $hash = hash('sha256', $method.':'.trim($evidence));
        $now = $this->ctx->now();
        $existing = DB::table('tenant_campaign_consents')
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('contact_id', $contactId)
            ->first(['id']);

        if ($existing) {
            DB::table('tenant_campaign_consents')
                ->where('id', $existing->id)
                ->update([
                    'phone_key' => $phoneKey,
                    'method' => $method,
                    'proof_hash' => $hash,
                    'created_at' => $now,
                ]);
        } else {
            DB::table('tenant_campaign_consents')->insert([
                'account_id' => $a,
                'site_id' => $s,
                'contact_id' => $contactId,
                'phone_key' => $phoneKey,
                'method' => $method,
                'proof_hash' => $hash,
                'created_at' => $now,
            ]);
        }

        return ['recorded' => true, 'consent_kind' => 'self_attested', 'contact_id' => $opaque];
    }

    public function deviceListCampaigns(array $binding): array
    {
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        $d = (int) $binding['id'];
        $slot = (int) $binding['sim_slot'];

        $campaigns = DB::table('tenant_campaigns')
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('device_binding_id', $d)
            ->where('sim_slot', $slot)
            ->orderByDesc('created_at')
            ->limit(60)
            ->get(['id', 'name', 'starts_at', 'ends_at', 'status'])
            ->map(fn ($r) => (array) $r)
            ->all();

        $attempts = DB::table('tenant_campaign_attempts')
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('device_binding_id', $d)
            ->where('sim_slot', $slot)
            ->orderByDesc('leased_epoch')
            ->limit(80)
            ->get(['id', 'campaign_id', 'status', 'result', 'finished_at'])
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'campaigns' => $campaigns,
            'attempts' => $attempts,
            'dispatch_enabled' => $this->dialEnabled(),
        ];
    }

    public function deviceCreateCampaign(array $binding, array $p): array
    {
        if (($p['confirmed'] ?? false) !== true) {
            throw new InvalidArgumentException('confirmation_required');
        }
        $name = trim((string) ($p['name'] ?? ''));
        $starts = (string) ($p['starts'] ?? '');
        $ends = (string) ($p['ends'] ?? '');
        $scenario = trim((string) ($p['scenario'] ?? ''));
        $ids = $p['contact_ids'] ?? [];

        if ($name === '' || mb_strlen($name) > 160 || $scenario === '' || mb_strlen($scenario) > 8000 || ! is_array($ids) || count($ids) > 60) {
            throw new InvalidArgumentException('invalid_campaign');
        }
        if (
            preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D', $starts) !== 1
            || preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D', $ends) !== 1
            || $ends <= $starts
        ) {
            throw new InvalidArgumentException('invalid_campaign_schedule');
        }

        $utcStart = $this->cairoStrictToUtc($starts);
        $utcEnd = $this->cairoStrictToUtc($ends);
        if (strtotime($utcEnd) - strtotime($utcStart) > 31 * 86400) {
            throw new InvalidArgumentException('campaign_window_too_long');
        }

        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        foreach ($ids as $opaque) {
            $uid = $this->parseOpaqueId($opaque);
            $exists = DB::table('contacts')
                ->where('id', $uid)
                ->where('account_id', $a)
                ->where('site_id', $s)
                ->exists();
            if (! $exists) {
                throw new InvalidArgumentException('foreign_contact');
            }
        }

        $id = bin2hex(random_bytes(12));
        DB::table('tenant_campaigns')->insert([
            'id' => $id,
            'account_id' => $a,
            'site_id' => $s,
            'device_binding_id' => (int) $binding['id'],
            'sim_slot' => (int) $binding['sim_slot'],
            'name' => $name,
            'starts_at' => $utcStart,
            'ends_at' => $utcEnd,
            'scenario' => $scenario,
            'contact_ids_json' => json_encode($ids, JSON_THROW_ON_ERROR),
            'status' => 'draft',
            'created_at' => $this->ctx->now(),
        ]);

        return ['campaign' => ['id' => $id, 'name' => $name, 'status' => 'draft'], 'calls_started' => 0];
    }

    public function deviceCampaignAction(array $binding, array $p): array
    {
        if (($p['confirmed'] ?? false) !== true) {
            throw new InvalidArgumentException('confirmation_required');
        }
        $id = (string) ($p['id'] ?? '');
        $action = (string) ($p['action'] ?? '');
        if (preg_match('/^[a-f0-9]{24}$/D', $id) !== 1) {
            throw new InvalidArgumentException('invalid_campaign');
        }

        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        $d = (int) $binding['id'];
        $slot = (int) $binding['sim_slot'];

        $row = DB::table('tenant_campaigns')
            ->where('id', $id)
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('device_binding_id', $d)
            ->where('sim_slot', $slot)
            ->first();

        if (! $row) {
            throw new InvalidArgumentException('campaign_unavailable');
        }
        $rowArr = (array) $row;

        if ($action === 'preview') {
            return ['preview' => $this->previewTargets($binding, $rowArr)];
        }
        if ($action === 'arm' || $action === 'resume') {
            return $this->armCampaign($binding, $rowArr);
        }
        if (! in_array($action, ['pause', 'cancel'], true)) {
            throw new InvalidArgumentException('campaign_action_denied');
        }
        if ($action === 'pause' && ! in_array($rowArr['status'], ['ready', 'armed', 'draft'], true)) {
            throw new InvalidArgumentException('campaign_not_pausable');
        }
        if ($action === 'cancel' && $rowArr['status'] === 'cancelled') {
            return ['campaign' => ['id' => $id, 'status' => 'cancelled'], 'calls_started' => 0];
        }

        $status = $action === 'pause' ? 'paused' : 'cancelled';
        DB::table('tenant_campaigns')
            ->where('id', $id)
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('device_binding_id', $d)
            ->where('sim_slot', $slot)
            ->update(['status' => $status]);

        return ['campaign' => ['id' => $id, 'status' => $status], 'calls_started' => 0];
    }

    public function deviceClaimAttempt(array $binding): array
    {
        if (! $this->dialEnabled()) {
            throw new RuntimeException('campaign_device_e2e_gate_disabled');
        }
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        $d = (int) $binding['id'];
        $slot = (int) $binding['sim_slot'];

        return DB::transaction(function () use ($binding, $a, $s, $d, $slot): array {
            $active = DB::table('tenant_campaign_attempts')
                ->where('account_id', $a)
                ->where('device_binding_id', $d)
                ->where('sim_slot', $slot)
                ->whereIn('status', ['leased', 'verified'])
                ->get(['id', 'leased_epoch']);

            foreach ($active as $row) {
                if (time() - (int) $row->leased_epoch <= 60) {
                    return ['claim' => null, 'reason' => 'active_lease', 'dial_requested' => false];
                }
                DB::table('tenant_campaign_attempts')
                    ->where('id', $row->id)
                    ->whereIn('status', ['leased', 'verified'])
                    ->update([
                        'status' => 'unknown',
                        'result' => 'expired',
                        'finished_at' => $this->ctx->now(),
                    ]);
            }

            $uncertain = DB::table('tenant_campaign_attempts')
                ->where('account_id', $a)
                ->where('device_binding_id', $d)
                ->where('sim_slot', $slot)
                ->where('status', 'unknown')
                ->exists();

            if ($uncertain) {
                return ['claim' => null, 'reason' => 'uncertain_call_requires_review', 'dial_requested' => false];
            }

            $campaigns = DB::table('tenant_campaigns')
                ->where('account_id', $a)
                ->where('site_id', $s)
                ->where('device_binding_id', $d)
                ->where('sim_slot', $slot)
                ->whereIn('status', ['ready', 'armed'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(20)
                ->get();

            foreach ($campaigns as $cmpObj) {
                $campaign = (array) $cmpObj;
                if (! $this->inCampaignWindow($campaign, time())) {
                    continue;
                }
                foreach ($this->campaignTargets($binding, $campaign) as $opaque) {
                    try {
                        $contactId = $this->parseOpaqueId($opaque);
                    } catch (InvalidArgumentException) {
                        continue;
                    }
                    $alreadyTried = DB::table('tenant_campaign_attempts')
                        ->where('campaign_id', $campaign['id'])
                        ->where('contact_id', $contactId)
                        ->exists();
                    if ($alreadyTried || ! $this->contactAllowed($binding, $contactId)) {
                        continue;
                    }
                    $contact = DB::table('contacts')
                        ->where('id', $contactId)
                        ->where('account_id', $a)
                        ->where('site_id', $s)
                        ->first(['id', 'name', 'phone', 'dnc']);
                    if (! $contact) {
                        continue;
                    }
                    $attemptId = bin2hex(random_bytes(16));
                    DB::table('tenant_campaign_attempts')->insert([
                        'id' => $attemptId,
                        'campaign_id' => $campaign['id'],
                        'account_id' => $a,
                        'site_id' => $s,
                        'device_binding_id' => $d,
                        'sim_slot' => $slot,
                        'contact_id' => $contactId,
                        'phone_key' => $this->ctx->dncPhoneKey((string) $contact->phone),
                        'status' => 'leased',
                        'leased_epoch' => time(),
                    ]);

                    return [
                        'claim' => [
                            'id' => $attemptId,
                            'campaign_id' => $campaign['id'],
                            'contact_id' => $opaque,
                            'name' => (string) $contact->name,
                            'phone' => (string) $contact->phone,
                            'scenario' => (string) $campaign['scenario'],
                            'expires_in_seconds' => 30,
                        ],
                        'dial_requested' => false,
                    ];
                }
            }

            return ['claim' => null, 'dial_requested' => false];
        });
    }

    public function deviceVerifyAttempt(array $binding, string $attemptId): array
    {
        if (! $this->dialEnabled()) {
            return ['allowed' => false, 'reason' => 'device_e2e_gate_disabled'];
        }
        $a = $this->findAttempt($binding, $attemptId);
        if (! $a || $a['status'] !== 'leased') {
            return ['allowed' => false, 'reason' => 'lease_not_active'];
        }

        $physical = DB::table('device_bindings')
            ->where('id', (int) $binding['id'])
            ->where('account_id', (int) $binding['account_id'])
            ->where('site_id', (int) $binding['site_id'])
            ->where('sim_slot', (int) $binding['sim_slot'])
            ->where('status', 'active')
            ->exists();
        if (! $physical) {
            return ['allowed' => false, 'reason' => 'device_revoked_or_unverified'];
        }
        if (time() - (int) $a['leased_epoch'] > 30) {
            return ['allowed' => false, 'reason' => 'lease_expired'];
        }

        $campaign = DB::table('tenant_campaigns')
            ->where('id', $a['campaign_id'])
            ->where('account_id', (int) $binding['account_id'])
            ->where('site_id', (int) $binding['site_id'])
            ->where('device_binding_id', (int) $binding['id'])
            ->where('sim_slot', (int) $binding['sim_slot'])
            ->first();
        if (! $campaign || ! in_array($campaign->status, ['ready', 'armed'], true) || ! $this->inCampaignWindow((array) $campaign, time())) {
            return ['allowed' => false, 'reason' => 'campaign_paused_or_outside_hours'];
        }
        if (! $this->contactAllowed($binding, (int) $a['contact_id'])) {
            return ['allowed' => false, 'reason' => 'consent_or_dnc_changed'];
        }

        $contact = DB::table('contacts')
            ->where('id', (int) $a['contact_id'])
            ->where('account_id', (int) $binding['account_id'])
            ->where('site_id', (int) $binding['site_id'])
            ->first(['id', 'phone']);
        if (! $contact || ! hash_equals((string) $a['phone_key'], $this->ctx->dncPhoneKey((string) $contact->phone))) {
            return ['allowed' => false, 'reason' => 'number_changed'];
        }

        $updated = DB::table('tenant_campaign_attempts')
            ->where('id', $attemptId)
            ->where('status', 'leased')
            ->update(['status' => 'verified', 'verified_epoch' => time()]);
        if ($updated !== 1) {
            return ['allowed' => false, 'reason' => 'already_verified'];
        }

        return [
            'allowed' => true,
            'reason' => 'ok',
            'must_dial_within_seconds' => 10,
            'sim_slot' => (int) $binding['sim_slot'],
        ];
    }

    public function deviceFinishAttempt(array $binding, string $attemptId, string $result): array
    {
        if (! in_array($result, ['completed', 'failed', 'busy', 'no_answer', 'unknown'], true)) {
            throw new InvalidArgumentException('invalid_attempt_result');
        }
        $a = $this->findAttempt($binding, $attemptId);
        if (! $a) {
            throw new InvalidArgumentException('attempt_unavailable');
        }
        if (in_array($a['status'], ['finished', 'unknown'], true)) {
            if ($a['result'] === $result) {
                return ['saved' => true, 'duplicate' => true, 'result' => $result];
            }
            throw new InvalidArgumentException('attempt_already_finished');
        }
        if (! in_array($a['status'], ['leased', 'verified'], true)) {
            throw new InvalidArgumentException('attempt_not_active');
        }
        if ($a['status'] !== 'verified' && in_array($result, ['completed', 'busy', 'no_answer'], true)) {
            throw new InvalidArgumentException('campaign_result_requires_verified_dial');
        }

        $stored = $result === 'unknown' ? 'unknown' : 'finished';
        DB::table('tenant_campaign_attempts')
            ->where('id', $attemptId)
            ->where('account_id', (int) $binding['account_id'])
            ->where('device_binding_id', (int) $binding['id'])
            ->whereIn('status', ['leased', 'verified'])
            ->update([
                'status' => $stored,
                'result' => $result,
                'finished_at' => $this->ctx->now(),
            ]);

        return ['saved' => true, 'duplicate' => false, 'result' => $result];
    }

    private function dialEnabled(): bool
    {
        return (bool) config('ontrack.campaign_dial_enabled', false)
            || getenv('ONTRACK_TENANT_CAMPAIGN_DIAL_ENABLED') === '1';
    }

    private function cairoStrictToUtc(string $local): string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new DateTimeZone('Africa/Cairo'));
        if (! $dt || $dt->format('Y-m-d\TH:i') !== $local) {
            throw new InvalidArgumentException('invalid_campaign_schedule');
        }

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function inCampaignWindow(array $campaign, int $epoch): bool
    {
        $from = (string) $campaign['starts_at'];
        $to = (string) $campaign['ends_at'];
        if (str_contains($from, 'T')) {
            $from = $this->cairoStrictToUtc($from);
        }
        if (str_contains($to, 'T')) {
            $to = $this->cairoStrictToUtc($to);
        }
        $current = gmdate('Y-m-d H:i:s', $epoch);

        return $current >= $from && $current < $to;
    }

    private function contactAllowed(array $binding, int $contactId): bool
    {
        $a = (int) $binding['account_id'];
        $s = (int) $binding['site_id'];
        $r = DB::table('contacts')->where('id', $contactId)->where('account_id', $a)->where('site_id', $s)->first(['phone', 'dnc']);
        if (! $r || (int) ($r->dnc ?? 0) === 1) {
            return false;
        }
        $key = $this->ctx->dncPhoneKey((string) $r->phone);
        $consent = DB::table('tenant_campaign_consents')
            ->where('account_id', $a)
            ->where('site_id', $s)
            ->where('contact_id', $contactId)
            ->value('phone_key');
        $suppressed = $key !== '' && DB::table('dnc_suppressions')
            ->where('account_id', $a)
            ->where('phone_key', $key)
            ->exists();

        return is_string($consent) && hash_equals($consent, $key) && ! $suppressed;
    }

    private function campaignTargets(array $binding, array $campaign): array
    {
        $list = json_decode((string) $campaign['contact_ids_json'], true);
        if (! is_array($list) || ! array_is_list($list)) {
            throw new InvalidArgumentException('invalid_campaign_targets');
        }
        if ($list === []) {
            $ids = DB::table('contacts')
                ->where('account_id', (int) $binding['account_id'])
                ->where('site_id', (int) $binding['site_id'])
                ->orderBy('id')
                ->limit(2000)
                ->pluck('id');

            return $ids->map(fn ($id) => $this->opaqueId((int) $id))->all();
        }

        return array_map(fn ($item) => is_int($item) ? $this->opaqueId($item) : (string) $item, $list);
    }

    private function previewTargets(array $binding, array $campaign): array
    {
        $eligible = [];
        $excluded = 0;
        foreach ($this->campaignTargets($binding, $campaign) as $opaque) {
            try {
                $id = $this->parseOpaqueId($opaque);
            } catch (InvalidArgumentException) {
                $excluded++;
                continue;
            }
            if ($this->contactAllowed($binding, $id)) {
                $eligible[] = ['id' => $opaque];
            } else {
                $excluded++;
            }
        }

        return [
            'eligible' => $eligible,
            'excluded' => $excluded,
            'dialed' => false,
            'dispatch_enabled' => $this->dialEnabled(),
        ];
    }

    private function armCampaign(array $binding, array $campaign): array
    {
        if (! in_array($campaign['status'], ['draft', 'paused'], true)) {
            throw new InvalidArgumentException('campaign_transition_denied');
        }
        $end = (string) $campaign['ends_at'];
        if (str_contains($end, 'T')) {
            $end = $this->cairoStrictToUtc($end);
        }
        if ($end <= gmdate('Y-m-d H:i:s')) {
            throw new InvalidArgumentException('campaign_expired');
        }
        $preview = $this->previewTargets($binding, $campaign);
        if (empty($preview['eligible'])) {
            throw new InvalidArgumentException('no_consented_contacts');
        }

        DB::table('tenant_campaigns')
            ->where('id', $campaign['id'])
            ->where('account_id', (int) $binding['account_id'])
            ->where('site_id', (int) $binding['site_id'])
            ->where('device_binding_id', (int) $binding['id'])
            ->where('sim_slot', (int) $binding['sim_slot'])
            ->update(['status' => 'ready']);

        return [
            'campaign' => ['id' => $campaign['id'], 'status' => 'ready'],
            'calls_started' => 0,
            'dispatch_enabled' => $this->dialEnabled(),
        ];
    }

    private function findAttempt(array $binding, string $attemptId): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $attemptId) !== 1) {
            throw new InvalidArgumentException('invalid_attempt_id');
        }

        $row = DB::table('tenant_campaign_attempts')
            ->where('id', $attemptId)
            ->where('account_id', (int) $binding['account_id'])
            ->where('site_id', (int) $binding['site_id'])
            ->where('device_binding_id', (int) $binding['id'])
            ->where('sim_slot', (int) $binding['sim_slot'])
            ->first();

        return $row ? (array) $row : null;
    }
}
