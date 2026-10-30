<?php

namespace App\Http\Controllers\Control\Ai;

use App\Http\Controllers\Controller;
use App\Models\PlatformAiAuditEvent;
use App\Models\PlatformAiModel;
use App\Models\PlatformAiRoutingPolicy;
use App\Models\Plan;
use Illuminate\Http\Request;

class RoutingController extends Controller
{
    public function index()
    {
        return view('control.ai.routing', [
            'policies' => PlatformAiRoutingPolicy::with('plan')->latest()->get(),
            'plans' => Plan::orderBy('name')->get(),
            'publishedModels' => PlatformAiModel::where('test_status', 'verified')->orWhere('test_status', 'untested')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['nullable','integer'],
            'feature' => ['required','string','max:80'],
            'risk_class' => ['required','string','max:40'],
            'writer_model_id' => ['nullable','string','max:200'],
            'reviewer_model_id' => ['nullable','string','max:200'],
            'fallback_model_id' => ['nullable','string','max:200'],
        ]);

        $policy = PlatformAiRoutingPolicy::updateOrCreate(
            ['plan_id'=>$data['plan_id'], 'feature'=>$data['feature'], 'risk_class'=>$data['risk_class']],
            $data
        );

        PlatformAiAuditEvent::create([
            'actor_user_id'=>$request->user()->id,
            'action'=>'routing.upsert',
            'resource_type'=>'routing_policy',
            'resource_id'=>$policy->id,
            'redacted_summary'=>'حُدّثت سياسة توجيه writer/reviewer/fallback على مستوى المنصة.',
        ]);

        return back()->with('status','تم حفظ سياسة التوجيه. الأسماء محفوظة حرفيًا ولا تُعرض لأي عميل.');
    }
}