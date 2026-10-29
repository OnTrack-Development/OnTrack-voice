<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [], [
            'name' => 'الاسم',
            'company' => 'اسم الشركة',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $tenant = Tenant::create([
                'name' => $data['company'],
                'slug' => $this->uniqueSlug(Tenant::class, $data['company']),
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            $workspace = Workspace::create([
                'tenant_id' => $tenant->id,
                'name' => 'المساحة الرئيسية',
                'slug' => 'main',
            ]);

            TenantMembership::create([
                'tenant_id' => $tenant->id,
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('app.home');
    }

    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name, '-', null) ?: 'tenant';
        $base = substr($base, 0, 48);
        $slug = $base;
        $i = 1;

        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}