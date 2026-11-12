<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Console\ConsoleContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly ConsoleContextService $ctx) {}

    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect('/');
        }

        $theme = session('iovoice_theme', 'dark');

        return view('enterprise.login', [
            'theme' => $theme,
            'showDemoFill' => ! app()->isProduction(),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
        ]);

        $email = Str::lower(trim($credentials['email']));
        $key = $email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "محاولات تسجيل دخول متكررة. حاول مرة أخرى بعد {$seconds} ثانية.",
            ]);
        }

        $user = User::where('email', $email)->first();
        $hash = $user ? $user->getAuthPassword() : '';

        if (! $user || ($user->status ?? 'active') !== 'active' || $hash === '' || ! Hash::check($credentials['password'], $hash)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة أو الحساب غير نشط.',
            ]);
        }

        RateLimiter::clear($key);

        // No 2FA / TOTP required on web login per owner directive
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->ctx->ensureUserHasAccount($user);
        $memberships = $this->ctx->memberships($user->id);

        if (! empty($memberships)) {
            $firstAccountId = (int) $memberships[0]['account_id'];
            session([
                'account_id' => $firstAccountId,
                'auth_version' => (int) ($user->auth_version ?? 0),
            ]);
            $this->ctx->audit($user->id, $firstAccountId, 'auth.login.success', ['ip' => $request->ip()]);
        }

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->ctx->audit($user->id, (int) session('account_id', 0) ?: null, 'auth.logout');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('enterprise.register', [
            'theme' => session('iovoice_theme', 'dark'),
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'name' => 'الاسم الكامل',
            'company' => 'اسم الشركة',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
        ]);

        $now = $this->ctx->now();
        $user = DB::transaction(function () use ($data, $now): User {
            $hash = Hash::make($data['password']);
            $user = User::create([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'password' => $hash,
                'password_hash' => $hash,
                'status' => 'active',
                'auth_version' => 0,
            ]);

            $baseSlug = Str::slug($data['company']) ?: 'company';
            $slug = substr($baseSlug, 0, 48);
            $i = 1;
            while (DB::table('accounts')->where('slug', $slug)->exists()) {
                $slug = substr($baseSlug, 0, 44).'-'.(++$i);
            }

            $accountId = DB::table('accounts')->insertGetId([
                'kind' => 'customer',
                'parent_id' => 0,
                'name' => trim($data['company']),
                'slug' => $slug,
                'status' => 'active',
                'brand_name' => trim($data['company']),
                'created_at' => $now,
            ]);

            DB::table('sites')->insert([
                'account_id' => $accountId,
                'name' => 'الموقع الرئيسي',
                'domain' => $slug.'.'.config('ontrack.public_domain', 'app.ontrackegy.com'),
                'status' => 'active',
                'created_at' => $now,
            ]);

            DB::table('memberships')->insert([
                'user_id' => $user->id,
                'account_id' => $accountId,
                'role' => 'owner',
                'created_at' => $now,
            ]);

            $tenantSlug = $slug;
            $ti = 1;
            while (Tenant::where('slug', $tenantSlug)->exists()) {
                $tenantSlug = substr($baseSlug, 0, 44).'-'.(++$ti);
            }
            $tenant = Tenant::create([
                'name' => trim($data['company']),
                'slug' => $tenantSlug,
                'status' => 'active',
                'created_by' => $user->id,
            ]);
            $workspace = Workspace::create([
                'tenant_id' => $tenant->id,
                'name' => 'المساحة الرئيسية',
                'slug' => $tenantSlug.'-main',
            ]);
            TenantMembership::create([
                'tenant_id' => $tenant->id,
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
            ]);

            // Auto-attach free starter plan if one exists so user can explore immediately
            $freePlan = DB::table('plans')->where('status', 'active')->where('price_minor', 0)->orderBy('id')->first();
            if ($freePlan) {
                $end = gmdate('Y-m-d H:i:s', time() + 30 * 86400);
                DB::table('subscriptions')->insert([
                    'account_id' => $accountId,
                    'plan_id' => $freePlan->id,
                    'status' => 'active',
                    'starts_at' => $now,
                    'ends_at' => $end,
                    'started_at' => $now,
                    'renews_at' => $end,
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $memberships = $this->ctx->memberships($user->id);
        if (! empty($memberships)) {
            session(['account_id' => (int) $memberships[0]['account_id'], 'auth_version' => 0]);
        }

        return redirect()->route('app.home')->with('success', 'تم إنشاء حسابك في IO Voice وتفعيل مساحة العمل بنجاح.');
    }

    public function showForgotPassword()
    {
        return view('enterprise.auth_message', [
            'title' => 'استعادة كلمة المرور',
            'theme' => session('iovoice_theme', 'dark'),
            'mode' => 'forgot',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        return back()->with('success', 'إذا كان البريد مسجلاً لدينا، سيتم إرسال تعليمات الاستعادة عبر قناة الدعم أو البريد التشغيلي.');
    }

    public function showChangePassword()
    {
        return redirect('/?tab=settings');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        /** @var User $user */
        $user = Auth::user();
        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            return back()->with('error', 'كلمة المرور الحالية غير صحيحة.');
        }

        $nextHash = Hash::make($data['password']);
        DB::table('users')->where('id', $user->id)->update([
            'password' => $nextHash,
            'password_hash' => $nextHash,
            'auth_version' => ((int) ($user->auth_version ?? 0)) + 1,
        ]);

        session(['auth_version' => ((int) ($user->auth_version ?? 0)) + 1]);

        return back()->with('success', 'تم تحديث كلمة المرور بنجاح.');
    }

    public function brandLogo(Request $request)
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" rx="28" fill="#09090B"/><rect x="4" y="4" width="112" height="112" rx="24" fill="none" stroke="#E11D48" stroke-width="4"/><circle cx="42" cy="60" r="14" fill="none" stroke="#FAFAFA" stroke-width="7"/><path d="M68 38 L68 82 M82 46 L94 60 L82 74" stroke="#E11D48" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>';

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }
}
