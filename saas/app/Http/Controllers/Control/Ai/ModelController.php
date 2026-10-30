<?php

namespace App\Http\Controllers\Control\Ai;

use App\Http\Controllers\Controller;
use App\Models\PlatformAiAuditEvent;
use App\Models\PlatformAiModel;
use App\Models\PlatformAiProviderProfile;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function index()
    {
        return view('control.ai.models', [
            'providers' => PlatformAiProviderProfile::with('models')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider_profile_id' => ['required', 'integer'],
            'model_id_exact' => ['required', 'string', 'max:200'],
            'label' => ['nullable', 'string', 'max:120'],
        ], [], [
            'model_id_exact' => 'المعرف الدقيق للموديل',
        ]);

        $provider = PlatformAiProviderProfile::findOrFail($data['provider_profile_id']);

        $model = PlatformAiModel::firstOrCreate([
            'provider_profile_id' => $provider->id,
            'model_id_exact' => $data['model_id_exact'],
        ], [
            'label' => $data['label'] ?? $data['model_id_exact'],
            'test_status' => 'untested',
        ]);

        PlatformAiAuditEvent::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'model.created',
            'resource_type' => 'platform_ai_model',
            'resource_id' => $model->id,
            'redacted_summary' => 'أُضيف موديل بالمعرّف الحرفي دون تعديل علامات الترقيم.',
        ]);

        return back()->with('status', 'تم حفظ الموديل بالمعرّف الحرفي. الاختبار الفعلي مطلوب قبل التفعيل.');
    }
}