<?php

namespace App\Services\Voice;

use App\Services\Security\VaultService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class VoiceCatalogService
{
    // One professional, identical transcript for all voice comparison previews.
    // Saved samples are versioned to prevent stale provider demos being played.
    public const PREVIEW_TEXT = 'أهلًا بحضرتك في آي أو فويس. يسعدني أساعدك وأجاوب عن استفسارك بوضوح وسرعة.';

    public const GOOGLE_LIVE_VOICES = [
        'Zephyr' => 'Bright', 'Kore' => 'Firm', 'Orus' => 'Firm', 'Autonoe' => 'Bright',
        'Umbriel' => 'Easy-going', 'Erinome' => 'Clear', 'Laomedeia' => 'Upbeat', 'Schedar' => 'Even',
        'Achird' => 'Friendly', 'Sadachbia' => 'Lively', 'Puck' => 'Upbeat', 'Fenrir' => 'Excitable',
        'Aoede' => 'Breezy', 'Enceladus' => 'Breathy', 'Algieba' => 'Smooth', 'Algenib' => 'Gravelly',
        'Achernar' => 'Soft', 'Gacrux' => 'Mature', 'Zubenelgenubi' => 'Casual', 'Sadaltager' => 'Knowledgeable',
        'Charon' => 'Informative', 'Leda' => 'Youthful', 'Callirrhoe' => 'Easy-going', 'Iapetus' => 'Clear',
        'Despina' => 'Smooth', 'Rasalgethi' => 'Informative', 'Alnilam' => 'Firm', 'Pulcherrima' => 'Forward',
        'Vindemiatrix' => 'Gentle', 'Sulafat' => 'Warm',
    ];

    public function __construct(private readonly VaultService $vault) {}

    public function inferGender(string $hint): string
    {
        $lower = mb_strtolower($hint, 'UTF-8');
        foreach ([
            'aoede', 'kore', 'leda', 'zephyr', 'autonoe', 'erinome', 'laomedeia', 'despina',
            'callirrhoe', 'pulcherrima', 'vindemiatrix', 'sulafat', 'sadachbia',
            'female', 'woman', 'girl', 'lady', 'she/her', 'أنثى', 'بنت', 'فتاة', 'سيدة', 'نسائي',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return 'female';
            }
        }
        foreach ([
            'orus', 'puck', 'fenrir', 'charon', 'umbriel', 'schedar', 'achird', 'enceladus',
            'algieba', 'algenib', 'achernar', 'gacrux', 'zubenelgenubi', 'sadaltager', 'iapetus',
            'rasalgethi', 'alnilam', 'male', 'man', 'boy', 'gentleman', 'he/him', 'ذكر', 'رجل', 'شاب', 'رجالي',
        ] as $needle) {
            if (str_contains($lower, $needle)) {
                return 'male';
            }
        }

        return 'neutral';
    }

    public function normalizeDialect(array $item): string
    {
        $langs = array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            is_array($item['languages'] ?? null) ? $item['languages'] : []
        );
        $blob = mb_strtolower(
            implode(' ', array_map('strval', is_array($item['tags'] ?? null) ? $item['tags'] : [])).' '.
            (string) ($item['title'] ?? $item['name'] ?? '').' '.
            (string) ($item['description'] ?? ''),
            'UTF-8'
        );

        foreach ([
            'لبناني' => ['leban', 'لبنان', 'بيروت', 'beirut'],
            'مصري' => ['egypt', 'مصر', 'قاهر', 'صعيد'],
            'خليجي' => ['gulf', 'saudi', 'emirati', 'kuwait', 'qatar', 'خليج', 'سعود', 'إمارات', 'كويت'],
            'سوري' => ['syria', 'شام', 'سوري', 'دمشق'],
            'أردني' => ['jordan', 'أردن'],
            'مغربي' => ['morocc', 'maghreb', 'مغرب'],
        ] as $label => $needles) {
            foreach ($needles as $n) {
                if (str_contains($blob, $n)) {
                    return $label;
                }
            }
        }

        if (in_array('ar', $langs, true)) {
            return count($langs) > 1 ? 'عربي / متعدد' : 'عربي';
        }
        if (in_array('en', $langs, true)) {
            return count($langs) > 1 ? 'إنجليزي / متعدد' : 'إنجليزي';
        }

        return $langs ? 'متعدد اللغات' : '';
    }

    public function syncGoogleLiveVoices(): int
    {
        $existing = DB::table('voices')->where('provider', 'gemini_live')->get()->keyBy(fn ($r) => strtolower((string) $r->code));
        $count = 0;

        foreach (self::GOOGLE_LIVE_VOICES as $name => $description) {
            $code = 'google.'.strtolower($name);
            $gender = $this->inferGender($name);
            $row = $existing->get($code);

            if (! $row) {
                DB::table('voices')->insert([
                    'code' => $code,
                    'name' => $name.' · '.$description,
                    'provider' => 'gemini_live',
                    'tier' => 'basic',
                    'ready' => 0,
                    'status' => 'active',
                    'gender' => $gender,
                    'dialect' => 'متعدد اللغات',
                ]);
            } else {
                $updates = ['name' => $name.' · '.$description];
                if (empty($row->gender) || $row->gender === 'neutral') {
                    $updates['gender'] = $gender;
                }
                if (empty($row->dialect)) {
                    $updates['dialect'] = 'متعدد اللغات';
                }
                DB::table('voices')->where('id', $row->id)->update($updates);
            }
            $count++;
        }

        return $count;
    }

    public function ioPremiumProfile(): array
    {
        $data = $this->vault->readJsonFile('voice-lab/owner-1.json', ['keys' => [], 'settings' => []]);
        if (! is_array($data['keys'] ?? null)) {
            $data['keys'] = [];
        }
        if (! is_array($data['settings'] ?? null)) {
            $data['settings'] = [];
        }

        return $data;
    }

    public function ioPremiumState(): array
    {
        $profile = $this->ioPremiumProfile();
        $sealed = $profile['keys']['fish'] ?? null;
        $key = is_string($sealed) ? $this->vault->unseal($sealed) : null;
        $keyReady = is_string($key) && trim($key) !== '';
        $voiceId = (string) ($profile['settings']['voiceId'] ?? '');
        $model = (string) ($profile['settings']['fishModel'] ?? 'drama-3');
        $configured = $keyReady
            && preg_match('/^[a-f0-9]{32}$/D', $voiceId) === 1
            && in_array($model, ['drama-3', 's2.1-pro-free', 's2.1-pro'], true);

        return [
            'key_saved' => $keyReady,
            'voice_id' => $voiceId,
            'fish_model' => $model,
            'configured' => $configured,
        ];
    }

    public function ioPremiumAccessPolicy(): array
    {
        $settings = $this->ioPremiumProfile()['settings'];
        $mode = (string) ($settings['accessMode'] ?? 'all');
        if (! in_array($mode, ['all', 'limited', 'off'], true)) {
            $mode = 'off';
        }
        $limit = max(0, min(100000, (int) ($settings['accessLimit'] ?? 0)));

        return ['mode' => $mode, 'limit' => $limit];
    }

    public function ioPremiumAccessAllowed(int $accountId): bool
    {
        if ($accountId < 1) {
            return false;
        }
        $acct = DB::table('accounts')->where('id', $accountId)->first();
        if (! $acct || $acct->status !== 'active') {
            return false;
        }
        $p = $this->ioPremiumAccessPolicy();
        if ($p['mode'] === 'off') {
            return false;
        }
        $voice = DB::table('voices')->where('code', 'io.premium')->first();
        if (! $voice || (int) $voice->ready !== 1 || $voice->status !== 'active' || ! $this->ioPremiumState()['configured']) {
            return false;
        }
        if ($acct->kind === 'platform') {
            return true;
        }
        if ($p['mode'] === 'all') {
            return true;
        }
        if ($p['limit'] < 1) {
            return false;
        }
        $slots = DB::table('accounts')
            ->where('kind', '!=', 'platform')
            ->where('status', 'active')
            ->orderBy('id')
            ->limit($p['limit'])
            ->pluck('id')
            ->all();

        return in_array($accountId, array_map('intval', $slots), true);
    }

    public function savedFishKey(?string $overrideKey = null): string
    {
        $raw = trim((string) $overrideKey);
        if ($raw !== '') {
            if (strlen($raw) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $raw)) {
                throw new InvalidArgumentException('مفتاح Fish Audio يحتوي على رموز غير مسموحة.');
            }

            return $raw;
        }
        $sealed = $this->ioPremiumProfile()['keys']['fish'] ?? null;
        if (! is_string($sealed) || $sealed === '') {
            return '';
        }

        return trim((string) ($this->vault->unseal($sealed) ?? ''));
    }

    public function saveIoPremiumSettings(string $incomingKey, string $voiceId, string $model, ?callable $transport = null): void
    {
        if (strlen($incomingKey) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $incomingKey)) {
            throw new InvalidArgumentException('مفتاح Fish Audio غير صالح.');
        }
        $voiceId = strtolower(trim($voiceId));
        if (preg_match('/^[a-f0-9]{32}$/D', $voiceId) !== 1) {
            throw new InvalidArgumentException('Voice ID يجب أن يكون 32 حرف hex من Fish Audio.');
        }
        if (! in_array($model, ['drama-3', 's2.1-pro-free', 's2.1-pro'], true)) {
            throw new InvalidArgumentException('موديل Fish Audio غير مدعوم.');
        }

        $profile = $this->ioPremiumProfile();
        $sealed = $profile['keys']['fish'] ?? null;
        $oldKey = is_string($sealed) ? ($this->vault->unseal($sealed) ?? '') : '';
        $key = trim($incomingKey) !== '' ? trim($incomingKey) : $oldKey;
        if ($key === '') {
            throw new InvalidArgumentException('أدخل Fish Audio API Key أو احفظ مفتاحاً أولاً.');
        }

        $this->verifyFishKey($key, $voiceId, $model, $transport);

        $profile['keys']['fish'] = trim($incomingKey) !== '' ? $this->vault->seal($key) : $sealed;
        $profile['settings']['voiceId'] = $voiceId;
        $profile['settings']['fishModel'] = $model;
        $this->vault->writeJsonFileAtomic('voice-lab/owner-1.json', $profile);

        $ioVoice = DB::table('voices')->where('code', 'io.premium')->first();
        if (! $ioVoice) {
            $id = DB::table('voices')->insertGetId([
                'code' => 'io.premium',
                'name' => 'IO Premium',
                'provider' => 'fish_audio',
                'tier' => 'premium',
                'ready' => 1,
                'status' => 'active',
                'dialect' => 'متعدد اللهجات',
                'source_ref_id' => $voiceId,
            ]);
        } else {
            $id = (int) $ioVoice->id;
            DB::table('voices')->where('id', $id)->update([
                'ready' => 1,
                'status' => 'active',
                'source_ref_id' => $voiceId,
            ]);
        }

        foreach (DB::table('accounts')->where('kind', 'platform')->where('status', 'active')->pluck('id') as $acctId) {
            DB::table('voice_grants')->insertOrIgnore([
                'account_id' => (int) $acctId,
                'voice_id' => $id,
            ]);
        }
    }

    public function saveIoPremiumAccess(string $mode, int $limit): void
    {
        if (! in_array($mode, ['all', 'limited', 'off'], true) || $limit < 0 || $limit > 100000 || ($mode === 'limited' && $limit < 1)) {
            throw new InvalidArgumentException('سياسة توزيع IO Premium غير صالحة.');
        }
        $profile = $this->ioPremiumProfile();
        $profile['settings']['accessMode'] = $mode;
        $profile['settings']['accessLimit'] = $mode === 'limited' ? $limit : 0;
        $this->vault->writeJsonFileAtomic('voice-lab/owner-1.json', $profile);
    }

    private function verifyFishKey(string $key, string $voiceId, string $model, ?callable $transport = null): void
    {
        if ($transport !== null) {
            $res = $transport('https://api.fish.audio/v1/tts', $key, ['voiceId' => $voiceId, 'model' => $model]);
            if (! ($res['ok'] ?? false)) {
                throw new RuntimeException('تعذر التحقق من مفتاح Fish Audio والصوت المختار.');
            }

            return;
        }
        if (! function_exists('curl_init')) {
            throw new RuntimeException('cURL غير متاح للتحقق من Fish Audio.');
        }
        $payload = json_encode([
            'text' => 'أهلاً بيك.',
            'reference_id' => $voiceId,
            'format' => 'pcm',
            'sample_rate' => 44100,
            'streaming' => true,
            'latency' => 'low',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $ch = curl_init('https://api.fish.audio/v1/tts');
        $bytes = 0;
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$key,
                'Content-Type: application/json',
                'Accept: application/octet-stream',
                'model: '.$model,
            ],
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => static function ($h, string $chunk) use (&$bytes): int {
                $bytes += strlen($chunk);

                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($ok !== true || $status !== 200 || $bytes < 128) {
            throw new RuntimeException('تعذر التأكد من مفتاح Fish Audio والصوت المختار. لم يتم حفظ أي تغيير.');
        }
    }

    public function syncFishCatalog(?string $overrideKey = null, string $query = '', int $limit = 30, ?callable $transport = null, bool $markReady = false): array
    {
        $apiKey = $this->savedFishKey($overrideKey);
        if ($apiKey === '') {
            throw new InvalidArgumentException('أدخل مفتاح Fish Audio أو احفظه أولاً في إعدادات IO Premium.');
        }
        $limit = max(1, min(50, $limit));
        $cleanQuery = trim(mb_substr($query, 0, 80));
        $url = 'https://api.fish.audio/model?page_size='.$limit.'&page_number=1&sort_by=task_count';
        if ($cleanQuery !== '') {
            $url .= '&title='.rawurlencode($cleanQuery);
        }

        if ($transport !== null) {
            $res = $transport($url, $apiKey);
            $status = (int) ($res['status'] ?? 0);
            $body = $res['body'] ?? false;
        } else {
            if (! function_exists('curl_init')) {
                throw new RuntimeException('cURL غير متاح على الاستضافة.');
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$apiKey, 'Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            try {
                $body = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            } finally {
                curl_close($ch);
            }
        }

        if (! is_string($body) || $status !== 200) {
            throw new RuntimeException('تعذر جلب كتالوج Fish Audio (HTTP '.$status.').');
        }

        $json = json_decode($body, true);
        $items = is_array($json) ? ($json['items'] ?? $json['data'] ?? (array_is_list($json) ? $json : null)) : null;
        if (! is_array($items)) {
            throw new RuntimeException('استجابة Fish Audio لا تحتوي على قائمة أصوات صالحة.');
        }

        $inserted = 0;
        $updated = 0;
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $refId = strtolower(trim((string) ($item['_id'] ?? $item['id'] ?? '')));
            if (preg_match('/^[0-9a-f]{32}$/D', $refId) !== 1) {
                continue;
            }
            $code = 'fish.'.$refId;
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $title = trim((string) ($item['title'] ?? $item['name'] ?? 'Fish '.$refId));
            if ($title === '') {
                $title = 'Fish '.substr($refId, 0, 8);
            }
            $title = mb_substr($title, 0, 120);
            $desc = trim((string) ($item['description'] ?? ''));
            $tags = is_array($item['tags'] ?? null) ? implode(' ', array_map('strval', $item['tags'])) : '';
            $gender = $this->inferGender($title.' '.$desc.' '.$tags.' '.(string) ($item['gender'] ?? ''));
            $dialect = $this->normalizeDialect($item);

            $sampleUrl = '';
            if (is_array($item['samples'] ?? null) && is_array($item['samples'][0] ?? null)) {
                $cand = trim((string) ($item['samples'][0]['audio'] ?? $item['samples'][0]['url'] ?? ''));
                if ($cand !== '' && filter_var($cand, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($cand, PHP_URL_SCHEME)) === 'https' && strlen($cand) <= 500) {
                    $sampleUrl = $cand;
                }
            }

            $meta = json_encode([
                'description' => mb_substr($desc, 0, 300),
                'languages' => is_array($item['languages'] ?? null) ? array_values($item['languages']) : [],
                'tags' => is_array($item['tags'] ?? null) ? array_slice(array_values($item['tags']), 0, 8) : [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $existing = DB::table('voices')->where('code', $code)->first();
            if (! $existing) {
                DB::table('voices')->insert([
                    'code' => $code,
                    'name' => $title,
                    'provider' => 'fish_audio',
                    'tier' => 'premium',
                    'ready' => $markReady ? 1 : 0,
                    'status' => 'active',
                    'gender' => $gender,
                    'dialect' => $dialect,
                    'source_ref_id' => $refId,
                    'sample_url' => $sampleUrl,
                    'meta_json' => $meta,
                ]);
                $inserted++;
            } else {
                // Intentionally preserves existing sample_path for 0-token replay
                DB::table('voices')->where('id', $existing->id)->update([
                    'name' => $title,
                    'provider' => 'fish_audio',
                    'tier' => 'premium',
                    'gender' => $gender,
                    'dialect' => $dialect !== '' ? $dialect : ($existing->dialect ?? ''),
                    'source_ref_id' => $refId,
                    'sample_url' => $sampleUrl !== '' ? $sampleUrl : ($existing->sample_url ?? ''),
                    'meta_json' => $meta,
                    'ready' => $markReady ? 1 : (int) $existing->ready,
                ]);
                $updated++;
            }
        }

        return ['synced' => $inserted + $updated, 'inserted' => $inserted, 'updated' => $updated];
    }

    public function addCustomFishVoice(string $name, string $refId, string $gender = 'neutral', string $dialect = '', bool $ready = false): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('اكتب اسماً مناسباً للصوت (حتى 120 حرفاً).');
        }
        $refId = strtolower(trim($refId));
        if (preg_match('/^[0-9a-f]{32}$/D', $refId) !== 1) {
            throw new InvalidArgumentException('Reference ID يجب أن يكون 32 حرف hex من Fish Audio.');
        }
        if (! in_array($gender, ['female', 'male', 'neutral'], true)) {
            $gender = 'neutral';
        }
        $dialect = trim(mb_substr($dialect, 0, 80));
        $code = 'fish.'.$refId;
        $existing = DB::table('voices')->where('code', $code)->first();

        if ($existing) {
            DB::table('voices')->where('id', $existing->id)->update([
                'name' => $name,
                'provider' => 'fish_audio',
                'tier' => 'premium',
                'gender' => $gender,
                'dialect' => $dialect,
                'source_ref_id' => $refId,
                'ready' => $ready ? 1 : (int) $existing->ready,
                'status' => 'active',
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('voices')->insertGetId([
            'code' => $code,
            'name' => $name,
            'provider' => 'fish_audio',
            'tier' => 'premium',
            'ready' => $ready ? 1 : 0,
            'status' => 'active',
            'gender' => $gender,
            'dialect' => $dialect,
            'source_ref_id' => $refId,
        ]);
    }

    public function detectAudioExt(string $bytes): ?string
    {
        if (strlen($bytes) < 12) {
            return null;
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WAVE') {
            return 'wav';
        }
        if (str_starts_with($bytes, 'OggS')) {
            return 'ogg';
        }
        if (str_starts_with($bytes, 'ID3')) {
            return 'mp3';
        }
        if (ord($bytes[0]) === 0xFF && (ord($bytes[1]) & 0xE0) === 0xE0) {
            return 'mp3';
        }

        return null;
    }

    public function resolveSampleUrl(object $voice): ?string
    {
        $file = trim((string) ($voice->sample_path ?? ''));
        if ($file === '') {
            return null;
        }
        $clean = basename($file);
        foreach ([
            public_path('assets/voice-samples/'.$clean) => '/assets/voice-samples/'.$clean,
            public_path('saas/console/assets/voice-samples/'.$clean) => '/saas/console/assets/voice-samples/'.$clean,
            base_path('console/assets/voice-samples/'.$clean) => '/saas/console/assets/voice-samples/'.$clean,
        ] as $fsPath => $url) {
            if (is_file($fsPath) && filesize($fsPath) > 128) {
                return $url;
            }
        }
        $vaultFile = $this->vault->vaultDir().'/voice-samples/'.$clean;
        if (is_file($vaultFile) && filesize($vaultFile) > 128) {
            return '/assets/voice-samples/'.$clean;
        }

        return null;
    }

    public function ensureVoiceSample(int $voiceId, ?callable $transport = null): array
    {
        $voice = DB::table('voices')->where('id', $voiceId)->first();
        if (! $voice) {
            throw new InvalidArgumentException('الصوت المطلوب غير موجود.');
        }

        if ($voice->status !== 'active') {
            throw new InvalidArgumentException('هذا الصوت تم إخفاؤه من الكتالوج.');
        }
        $fixedSample = str_contains((string) ($voice->sample_path ?? ''), '-fixed-v2.');
        $cachedUrl = $fixedSample ? $this->resolveSampleUrl($voice) : null;
        if ($cachedUrl !== null) {
            $clean = basename((string) $voice->sample_path);
            $pub = public_path('assets/voice-samples/'.$clean);
            $vaultFile = $this->vault->vaultDir().'/voice-samples/'.$clean;
            if (! is_file($pub) && is_file($vaultFile)) {
                @mkdir(dirname($pub), 0755, true);
                @copy($vaultFile, $pub);
            }

            return [
                'ok' => true,
                'cached' => true,
                'tokens_spent' => 0,
                'url' => $cachedUrl,
                'voice_id' => $voiceId,
            ];
        }

        $bytes = null;
        $ext = null;

        if ($transport !== null) {
            $res = $transport((array) $voice);
            $bytes = is_string($res['bytes'] ?? null) ? $res['bytes'] : null;
            if ($bytes !== null) {
                $ext = $this->detectAudioExt($bytes);
            }
        } else {
            // Never replay arbitrary third-party voice samples; every preview
            // is synthesized from PREVIEW_TEXT and cached with -fixed-v2.

            if ($bytes === null && $voice->provider === 'fish_audio') {
                $apiKey = $this->savedFishKey();
                $refId = trim((string) ($voice->source_ref_id ?: (str_starts_with((string) $voice->code, 'fish.') ? substr((string) $voice->code, 5) : '')));
                if ($apiKey !== '' && preg_match('/^[0-9a-f]{32}$/D', $refId) === 1) {
                    $state = $this->ioPremiumState();
                    [$status, $body] = $this->httpPostJson('https://api.fish.audio/v1/tts', [
                        'text' => self::PREVIEW_TEXT,
                        'reference_id' => $refId,
                        'format' => 'mp3',
                        'mp3_bitrate' => 128,
                    ], [
                        'Authorization: Bearer '.$apiKey,
                        'model: '.($state['fish_model'] ?: 'drama-3'),
                    ]);
                    if ($status === 200 && is_string($body) && ($det = $this->detectAudioExt($body)) !== null) {
                        $bytes = $body;
                        $ext = $det;
                    }
                }
            }
            if ($bytes === null && $voice->provider === 'gemini_live') {
                $platformId = DB::table('accounts')->where('kind', 'platform')
                    ->where('status', 'active')->orderBy('id')->value('id');
                $row = $platformId ? DB::table('ai_relay_entries')
                    ->where('account_id', $platformId)->where('scope', 'voice')
                    ->whereIn('driver', ['gemini_live', 'google_live'])
                    ->where('enabled', 1)->orderBy('priority')->orderBy('id')->first() : null;
                $key = $row ? (string) ($this->vault->unseal((string) $row->secret_enc) ?? '') : '';
                if ($key === '') {
                    $key = trim((string) config('ontrack.gemini_api_key', ''));
                }
                $chosenVoice = ucfirst(substr((string) $voice->code, strlen('google.')));
                if ($key !== '' && array_key_exists($chosenVoice, self::GOOGLE_LIVE_VOICES)) {
                    // TTS is intentionally separate from the Live SIM call path.
                    // Legacy Gemini TTS returns headerless mono signed PCM 24k.
                    // No Login, AI-relay round trip, or test call is performed.
                    $model = 'gemini-2.5-flash-preview-tts';
                    [$status, $body] = $this->httpPostJson(
                        'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent',
                        [
                            'contents' => [['parts' => [['text' => 'Say naturally in Arabic: '.self::PREVIEW_TEXT]]]],
                            'generationConfig' => [
                                'responseModalities' => ['AUDIO'],
                                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $chosenVoice]]],
                            ],
                        ],
                        ['x-goog-api-key: '.$key]
                    );
                    if ($status === 200 && is_string($body) && strlen($body) < 6000000) {
                        $payload = json_decode($body, true);
                        $audio = $payload['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
                        $pcm = is_string($audio) ? base64_decode($audio, true) : false;
                        if (is_string($pcm) && strlen($pcm) >= 128 &&
                            strlen($pcm) <= 3000000 && strlen($pcm) % 2 === 0) {
                            $bytes = $this->pcm24kToWav($pcm);
                            $ext = 'wav';
                        }
                    }
                }
            }
        }

        if ($bytes === null || $ext === null) {
            throw new RuntimeException('تعذر توليد أو تحميل عينة صوتية صالحة لهذا الصوت حالياً. تأكد من مفاتيح المزود أو جرب صوتاً آخر.');
        }

        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $voice->code).'-'.$voiceId.'-fixed-v2.'.$ext;
        $pubPath = public_path('assets/voice-samples/'.$filename);
        $vaultFile = $this->vault->vaultDir().'/voice-samples/'.$filename;

        $this->atomicWriteBytes($pubPath, $bytes, 0644);
        $this->atomicWriteBytes($vaultFile, $bytes, 0600);

        DB::table('voices')->where('id', $voiceId)->update(['sample_path' => $filename]);

        return [
            'ok' => true,
            'cached' => false,
            'tokens_spent' => 1,
            'url' => '/assets/voice-samples/'.$filename,
            'voice_id' => $voiceId,
        ];
    }

    public function listVoicesForAccount(int $accountId, string $accountKind, array $filters = []): array
    {
        $grantedIds = DB::table('voice_grants')->where('account_id', $accountId)->pluck('voice_id')->map('intval')->all();
        $ioAllowed = $this->ioPremiumAccessAllowed($accountId);

        $query = DB::table('voices')->where('status', 'active');
        if (! empty($filters['provider'])) {
            $query->where('provider', (string) $filters['provider']);
        }
        if (! empty($filters['gender']) && in_array($filters['gender'], ['female', 'male', 'neutral'], true)) {
            $query->where('gender', (string) $filters['gender']);
        }
        if (! empty($filters['dialect'])) {
            $query->where('dialect', 'like', '%'.trim((string) $filters['dialect']).'%');
        }
        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($sub) use ($q): void {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('code', 'like', '%'.$q.'%')
                    ->orWhere('dialect', 'like', '%'.$q.'%');
            });
        }

        return $query->orderByDesc('provider')->orderByDesc('ready')->orderBy('name')->get()->map(function ($row) use ($accountKind, $grantedIds, $ioAllowed) {
            $arr = (array) $row;
            $granted = $accountKind === 'platform' || in_array((int) $row->id, $grantedIds, true);
            $isIoPremium = $row->code === 'io.premium';
            $selectable = $isIoPremium
                ? $ioAllowed
                : ((int) $row->ready === 1 && $granted);

            $arr['granted'] = $granted;
            $arr['selectable'] = $selectable;
            $arr['sample_local_url'] = str_contains((string) ($row->sample_path ?? ''), '-fixed-v2.')
                ? $this->resolveSampleUrl($row) : null;

            return $arr;
        })->all();
    }

    private function pcm24kToWav(string $pcm): string
    {
        $n = strlen($pcm);
        if ($n < 128 || $n > 3000000 || $n % 2 !== 0) {
            throw new InvalidArgumentException('invalid_voice_pcm');
        }
        return pack('a4Va4a4VvvVVvva4V', 'RIFF', 36 + $n, 'WAVE',
            'fmt ', 16, 1, 1, 24000, 48000, 2, 16, 'data', $n).$pcm;
    }

    private function atomicWriteBytes(string $dest, string $bytes, int $mode): void
    {
        $dir = dirname($dest);
        if (! is_dir($dir) && ! @mkdir($dir, $mode === 0600 ? 0700 : 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('تعذر إنشاء مجلد حفظ عينات الصوت.');
        }
        $tmp = $dest.'.'.bin2hex(random_bytes(6)).'.tmp';
        try {
            if (file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('تعذر كتابة ملف العينة.');
            }
            @chmod($tmp, $mode);
            if (! rename($tmp, $dest)) {
                throw new RuntimeException('تعذر اعتماد ملف العينة.');
            }
            @chmod($dest, $mode);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function httpGet(string $url, array $headers): array
    {
        if (! function_exists('curl_init')) {
            return [0, false];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, $body];
    }

    private function httpPostJson(string $url, array $payload, array $headers): array
    {
        if (! function_exists('curl_init')) {
            return [0, false];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, $body];
    }
}
