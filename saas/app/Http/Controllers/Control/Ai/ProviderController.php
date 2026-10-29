<?php

namespace App\Http\Controllers\Control\Ai;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdminGrant;
use App\Models\PlatformAiAuditEvent;
use App\Models\PlatformAiProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $this->requirePlatformAdmin($request);

        return view('control.ai.providers', [
            'providers' => PlatformAiProviderProfile::withCount('models')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requirePlatformAdmin($request);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:google,openai_compatible,custom'],
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['nullable', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'extra' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'اسم المزود',
            'base_url' => 'عنوان الخدمة',
            'api_key' => 'مفتاح API',
        ]);

        $apiKey = $data['api_key'] ?? null;
        $extra = $data['extra'] ?? null;
        $baseUrl = $data['base_url'] ?? null;
        $credentials = [];
        if (! blank($apiKey)) {
            $credentials['api_key'] = $apiKey;
        }
        if (! blank($extra)) {
            $credentials['extra'] = $extra;
        }

        $profile = PlatformAiProviderProfile::create([
            'type' => $data['type'],
            'name' => $data['name'],
            'base_url' => $baseUrl,
            'encrypted_credentials' => $credentials === [] ? null : Crypt::encryptString(json_encode($credentials, JSON_UNESCAPED_SLASHES)),
            'connection_status' => $credentials === [] ? 'not_configured' : 'pending_test',
        ]);

        PlatformAiAuditEvent::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'provider.created',
            'resource_type' => 'provider_profile',
            'resource_id' => $profile->id,
            'redacted_summary' => 'تم إنشاء ملف مزود «'.$profile->name.'» دون عرض الأسرار.',
        ]);

        return redirect()->route('control.ai.providers.index')
            ->with('status', 'تم حفظ الملف كمسودة. يُختبَر الاتصال قبل إدخال الموديلات وتفعيلها.');
    }

    public function destroy(Request $request, PlatformAiProviderProfile $profile)
    {
        $this->requirePlatformAdmin($request);

        $profile->delete();

        PlatformAiAuditEvent::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'provider.deleted',
            'resource_type' => 'provider_profile',
            'resource_id' => $profile->id,
            'redacted_summary' => 'تم حذف ملف مزود دون عرض الأسرار.',
        ]);

        return back()->with('status', 'تم حذف ملف المزود.');
    }

    private function requirePlatformAdmin(Request $request): void
    {
        $grant = PlatformAdminGrant::query()
            ->where('user_id', $request->user()->id)
            ->exists();

        abort_if(! $grant, 403, 'هذه المنطقة مخصصة لمشرفي OnTrack فقط.');
    }
}