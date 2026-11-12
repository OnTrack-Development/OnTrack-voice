<?php

namespace App\Services\Console;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsoleContextService
{
    public const ROLE_GRANTS = [
        'manager' => ['read', 'sites.write', 'agents.write', 'campaigns.write', 'contacts.write', 'calls.write', 'calls.read', 'staff.read', 'devices.read', 'integrations.read'],
        'reception' => ['read', 'calls.write', 'calls.read', 'contacts.read', 'devices.read'],
        'operator' => ['read', 'agents.read', 'campaigns.read', 'campaigns.write', 'calls.write', 'calls.read', 'contacts.read', 'devices.read'],
        'support' => ['read', 'calls.read', 'staff.read', 'devices.read', 'integrations.read'],
        'finance' => ['read', 'billing.read', 'billing.write'],
        'viewer' => ['read'],
    ];

    public function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public function dncPhoneKey(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (! is_string($digits) || $digits === '') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^01[0-9]{9}$/D', $digits) === 1) {
            $digits = '20'.substr($digits, 1);
        }

        return $digits;
    }

    public function normalizeE164(string $phone): string
    {
        $trim = trim($phone);
        $key = $this->dncPhoneKey($trim);
        if ($key === '') {
            return '';
        }

        return '+'.$key;
    }

    /**
     * Ensures users created via Laravel registration (/register) or overlay seeders
     * seamlessly bridge into the unified IO Voice account + site + membership tables.
     */
    public function ensureUserHasAccount(User $user): void
    {
        $has = DB::table('memberships as m')
            ->join('accounts as a', 'a.id', '=', 'm.account_id')
            ->where('m.user_id', $user->id)
            ->where('a.status', 'active')
            ->exists();

        if ($has) {
            return;
        }

        // Check platform admin grant from overlay
        $isPlatformOwner = DB::table('platform_admin_grants')->where('user_id', $user->id)->exists();
        $tenantMembership = DB::table('tenant_memberships as tm')
            ->join('tenants as t', 't.id', '=', 'tm.tenant_id')
            ->where('tm.user_id', $user->id)
            ->where('tm.status', 'active')
            ->select('t.name as tenant_name', 't.slug as tenant_slug', 'tm.role')
            ->first();

        DB::transaction(function () use ($user, $isPlatformOwner, $tenantMembership): void {
            if ($isPlatformOwner) {
                $platform = DB::table('accounts')->where('kind', 'platform')->orderBy('id')->first();
                if (! $platform) {
                    $accountId = DB::table('accounts')->insertGetId([
                        'kind' => 'platform',
                        'parent_id' => 0,
                        'name' => 'IO Voice Platform',
                        'slug' => 'iovoice-platform',
                        'status' => 'active',
                        'brand_name' => 'IO Voice',
                        'created_at' => $this->now(),
                    ]);
                    DB::table('sites')->insert([
                        'account_id' => $accountId,
                        'name' => 'المنصة الرئيسية',
                        'domain' => config('ontrack.public_domain', 'app.ontrackegy.com'),
                        'status' => 'active',
                        'created_at' => $this->now(),
                    ]);
                } else {
                    $accountId = (int) $platform->id;
                }
                DB::table('memberships')->insertOrIgnore([
                    'user_id' => $user->id,
                    'account_id' => $accountId,
                    'role' => 'owner',
                    'created_at' => $this->now(),
                ]);

                return;
            }

            $baseName = $tenantMembership->tenant_name ?? ($user->name ? $user->name.' Workspace' : 'IO Voice Workspace');
            $baseSlug = Str::slug($tenantMembership->tenant_slug ?? $baseName) ?: 'workspace';
            $slug = $baseSlug;
            $i = 1;
            while (DB::table('accounts')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.(++$i);
            }

            $accountId = DB::table('accounts')->insertGetId([
                'kind' => 'customer',
                'parent_id' => 0,
                'name' => mb_substr($baseName, 0, 180),
                'slug' => mb_substr($slug, 0, 180),
                'status' => 'active',
                'brand_name' => mb_substr($baseName, 0, 180),
                'created_at' => $this->now(),
            ]);

            DB::table('sites')->insert([
                'account_id' => $accountId,
                'name' => 'الفرع الرئيسي',
                'domain' => $slug.'.'.config('ontrack.public_domain', 'app.ontrackegy.com'),
                'status' => 'active',
                'created_at' => $this->now(),
            ]);

            $role = in_array((string) ($tenantMembership->role ?? 'owner'), ['owner', 'admin', 'manager', 'reception', 'operator', 'support', 'finance', 'viewer'], true)
                ? (string) $tenantMembership->role
                : 'owner';

            DB::table('memberships')->insertOrIgnore([
                'user_id' => $user->id,
                'account_id' => $accountId,
                'role' => $role,
                'created_at' => $this->now(),
            ]);
        });
    }

    public function memberships(int $userId): array
    {
        return DB::table('memberships as m')
            ->join('accounts as a', 'a.id', '=', 'm.account_id')
            ->where('m.user_id', $userId)
            ->where('a.status', 'active')
            ->orderBy('a.id')
            ->select([
                'm.account_id',
                'm.role',
                'a.kind',
                'a.name',
                'a.slug',
                'a.parent_id',
                'a.status',
                'a.brand_name',
                'a.logo_path',
                'a.domain',
            ])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function account(int $accountId): ?array
    {
        $row = DB::table('accounts')->where('id', $accountId)->first();

        return $row ? (array) $row : null;
    }

    public function accountServiceActive(?array $account): bool
    {
        $visited = [];
        for ($depth = 0; $depth < 8; $depth++) {
            if (! $account || ($account['status'] ?? '') !== 'active') {
                return false;
            }
            $id = (int) ($account['id'] ?? 0);
            if ($id < 1 || isset($visited[$id])) {
                return false;
            }
            $visited[$id] = true;
            $parentId = (int) ($account['parent_id'] ?? 0);
            if ($parentId < 1) {
                return true;
            }
            $account = $this->account($parentId);
        }

        return false;
    }

    public function supportMode(int $originAccountId): string
    {
        $row = DB::table('support_policies')->where('account_id', $originAccountId)->first()
            ?? DB::table('support_policies')->where('account_id', 0)->first();

        return ($row && $row->access_mode === 'read_only') ? 'read_only' : 'full';
    }

    public function isResellerChild(int $resellerId, int $customerId): bool
    {
        $a = $this->account($customerId);

        return $a !== null && ($a['kind'] ?? '') === 'customer' && (int) ($a['parent_id'] ?? 0) === $resellerId;
    }

    public function resolve(User $user, ?int $requestedSiteId = null): array
    {
        $this->ensureUserHasAccount($user);
        $memberships = $this->memberships($user->id);

        if (empty($memberships)) {
            abort(403, 'لا يوجد حساب نشط مرتبط بهذا المستخدم.');
        }

        $sessionAccountId = (int) session('account_id', 0);
        $activeAccount = null;
        $support = null;

        // Check active support impersonation
        $sp = session('support');
        if (is_array($sp) && (int) ($sp['expires'] ?? 0) > time()) {
            $originId = (int) ($sp['origin_id'] ?? 0);
            $targetId = (int) ($sp['account_id'] ?? 0);
            $origin = null;
            foreach ($memberships as $m) {
                if ((int) $m['account_id'] === $originId) {
                    $origin = $m;
                    break;
                }
            }
            $subject = $this->account($targetId);
            if ($origin && $subject && (
                $origin['kind'] === 'platform' ||
                ($origin['kind'] === 'reseller' && $this->isResellerChild($originId, $targetId))
            )) {
                $role = $this->supportMode($originId) === 'read_only' ? 'support_viewer' : 'owner';
                $activeAccount = array_merge($subject, [
                    'account_id' => $targetId,
                    'role' => $role,
                    'support_origin' => $originId,
                ]);
                $support = $sp;
            } else {
                session()->forget('support');
            }
        }

        if ($activeAccount === null) {
            foreach ($memberships as $m) {
                if ((int) $m['account_id'] === $sessionAccountId) {
                    $activeAccount = $m;
                    break;
                }
            }
            if ($activeAccount === null) {
                $activeAccount = $memberships[0];
                session(['account_id' => (int) $activeAccount['account_id']]);
            }
        }

        $accountId = (int) $activeAccount['account_id'];
        $sites = DB::table('sites')
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => (array) $s)
            ->all();

        $siteId = $requestedSiteId ?: (int) session('site_id', 0);
        $selectedSite = null;
        foreach ($sites as $s) {
            if ((int) $s['id'] === $siteId) {
                $selectedSite = $s;
                break;
            }
        }
        if ($selectedSite === null && ! empty($sites)) {
            $selectedSite = $sites[0];
        }
        if ($selectedSite !== null) {
            session(['site_id' => (int) $selectedSite['id']]);
        }

        // View mode: admin vs user presentation toggle for platform/reseller owners
        $canSwitchView = in_array((string) ($activeAccount['kind'] ?? ''), ['platform', 'reseller'], true)
            && in_array((string) ($activeAccount['role'] ?? ''), ['owner', 'admin'], true)
            && $support === null;

        $viewMode = 'admin';
        if ($support && ($activeAccount['kind'] ?? '') === 'reseller') {
            $viewMode = (($support['view'] ?? 'admin') === 'user') ? 'user' : 'admin';
        } elseif ($canSwitchView) {
            $viewMode = session('console_view', 'admin') === 'user' ? 'user' : 'admin';
        } elseif (($activeAccount['kind'] ?? '') === 'customer') {
            $viewMode = 'user';
        }

        $brand = $this->brandForAccount($activeAccount);
        $theme = session('iovoice_theme', 'dark');
        if (! in_array($theme, ['dark', 'light'], true)) {
            $theme = 'dark';
        }

        // Determine user's highest-privilege origin account for multi-tenant quick switcher
        $originAccount = $memberships[0];
        foreach ($memberships as $m) {
            if (($m['kind'] ?? '') === 'platform') {
                $originAccount = $m;
                break;
            }
            if (($m['kind'] ?? '') === 'reseller' && ($originAccount['kind'] ?? '') !== 'platform') {
                $originAccount = $m;
            }
        }
        if ($support && ! empty($support['origin_id'])) {
            foreach ($memberships as $m) {
                if ((int) $m['account_id'] === (int) $support['origin_id']) {
                    $originAccount = $m;
                    break;
                }
            }
        }
        $originAccountId = (int) $originAccount['account_id'];
        $originKind = (string) ($originAccount['kind'] ?? 'customer');
        $originIsPlatform = $originKind === 'platform';
        $originIsReseller = $originKind === 'reseller';

        $switchableAccounts = [];
        if ($originIsPlatform) {
            $switchableAccounts = DB::table('accounts')
                ->orderByRaw("CASE kind WHEN 'platform' THEN 0 WHEN 'reseller' THEN 1 ELSE 2 END")
                ->orderBy('id')
                ->limit(100)
                ->get(['id', 'name', 'kind', 'slug', 'parent_id', 'status'])
                ->map(fn ($r) => (array) $r)
                ->all();
        } elseif ($originIsReseller) {
            $switchableAccounts = DB::table('accounts')
                ->where(function ($q) use ($originAccountId): void {
                    $q->where('id', $originAccountId)->orWhere('parent_id', $originAccountId);
                })
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$originAccountId])
                ->orderBy('id')
                ->limit(100)
                ->get(['id', 'name', 'kind', 'slug', 'parent_id', 'status'])
                ->map(fn ($r) => (array) $r)
                ->all();
        } elseif (count($memberships) > 1) {
            $switchableAccounts = array_map(fn ($m) => [
                'id' => (int) $m['account_id'],
                'name' => (string) $m['name'],
                'kind' => (string) $m['kind'],
                'slug' => (string) $m['slug'],
                'parent_id' => (int) ($m['parent_id'] ?? 0),
                'status' => (string) $m['status'],
            ], $memberships);
        }

        return [
            'user' => $user,
            'account' => $activeAccount,
            'account_id' => $accountId,
            'kind' => (string) ($activeAccount['kind'] ?? 'customer'),
            'role' => (string) ($activeAccount['role'] ?? 'viewer'),
            'memberships' => $memberships,
            'switchable_accounts' => $switchableAccounts,
            'origin_account_id' => $originAccountId,
            'origin_kind' => $originKind,
            'origin_is_platform' => $originIsPlatform,
            'origin_is_reseller' => $originIsReseller,
            'sites' => $sites,
            'selected_site' => $selectedSite,
            'site_id' => (int) ($selectedSite['id'] ?? 0),
            'support' => $support,
            'view_mode' => $viewMode,
            'can_switch_view' => $canSwitchView,
            'is_platform' => ($activeAccount['kind'] ?? '') === 'platform',
            'is_reseller' => ($activeAccount['kind'] ?? '') === 'reseller',
            'brand' => $brand,
            'theme' => $theme,
            'resellers_visible' => $this->resellersVisible(),
        ];
    }

    public function resellersVisible(): bool
    {
        $row = DB::table('platform_feature_settings')->where('feature_key', 'resellers')->first();

        return $row === null || (int) $row->enabled === 1;
    }

    public function allowed(array $ctx, string $permission): bool
    {
        $role = (string) ($ctx['role'] ?? 'viewer');
        if ($role === 'support_viewer') {
            return $permission === 'read';
        }
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return in_array($permission, self::ROLE_GRANTS[$role] ?? [], true);
    }

    public function requirePermission(array $ctx, string $permission): void
    {
        if (! $this->allowed($ctx, $permission)) {
            abort(403, 'لا تملك صلاحية لهذا الإجراء.');
        }
    }

    public function siteLimit(int $accountId, string $key): int
    {
        $account = $this->account($accountId);
        if (! $this->accountServiceActive($account)) {
            return 0;
        }
        if (($account['kind'] ?? '') === 'platform') {
            return in_array($key, ['call_quota', 'site_limit', 'agent_limit', 'staff_limit', 'reseller_customers', 'google_key_limit'], true)
                ? 1000000000
                : 0;
        }

        if ($key === 'google_key_limit') {
            $override = DB::table('google_voice_key_overrides')->where('account_id', $accountId)->first();
            if ($override) {
                return max(0, (int) $override->max_keys);
            }
        }

        $now = $this->now();
        $row = DB::table('subscriptions as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.account_id', $accountId)
            ->where('s.status', 'active')
            ->where('s.starts_at', '<=', $now)
            ->where('s.ends_at', '>', $now)
            ->orderByDesc('s.id')
            ->select('p.*')
            ->first();

        if (! $row) {
            // Active accounts with no expired subscription get generous starter quota fallback only when platform features permit
            return 0;
        }

        return (int) ($row->{$key} ?? 0);
    }

    public function canCreate(int $accountId, string $kind): bool
    {
        $table = $kind === 'site' ? 'sites' : 'agents';
        $col = $kind === 'site' ? 'site_limit' : 'agent_limit';
        $current = DB::table($table)->where('account_id', $accountId)->where('status', '!=', 'archived')->count();

        return $current < $this->siteLimit($accountId, $col);
    }

    public function brandForAccount(array $account): array
    {
        $kind = (string) ($account['kind'] ?? 'customer');
        if ($kind === 'platform') {
            return [
                'name' => (string) (($account['brand_name'] ?? '') !== '' ? $account['brand_name'] : 'IO Voice'),
                'logo' => (string) ($account['logo_path'] ?? ''),
                'accent' => '#E11D48',
            ];
        }
        if ($kind === 'reseller') {
            return [
                'name' => (string) (($account['brand_name'] ?? '') !== '' ? $account['brand_name'] : ($account['name'] ?? 'IO Voice')),
                'logo' => (string) ($account['logo_path'] ?? ''),
                'accent' => '#E11D48',
            ];
        }
        if (! empty($account['parent_id'])) {
            $parent = $this->account((int) $account['parent_id']);
            if ($parent) {
                return [
                    'name' => (string) (($parent['brand_name'] ?? '') !== '' ? $parent['brand_name'] : ($parent['name'] ?? 'IO Voice')),
                    'logo' => (string) ($parent['logo_path'] ?? ''),
                    'accent' => '#E11D48',
                ];
            }
        }

        return [
            'name' => 'IO Voice',
            'logo' => '',
            'accent' => '#E11D48',
        ];
    }

    public function audit(int $actorUserId, ?int $accountId, string $action, array $details = []): void
    {
        DB::table('audit_events')->insert([
            'actor_user_id' => $actorUserId,
            'account_id' => $accountId,
            'action' => $action,
            'detail' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $this->now(),
        ]);
    }
}
