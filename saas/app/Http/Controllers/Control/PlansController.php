<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use Illuminate\Http\Request;

class PlansController extends Controller
{
    public function index()
    {
        return view('control.plans', [
            'plans' => Plan::with('entitlements')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'monthly_quota_credits' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = Plan::create([
            'name' => $data['name'],
            'status' => 'active',
            'monthly_quota_credits' => $data['monthly_quota_credits'],
        ]);

        if (! blank($data['features'] ?? null)) {
            foreach (array_filter(array_map('trim', explode(',', $data['features']))) as $feature) {
                PlanEntitlement::firstOrCreate(['plan_id' => $plan->id, 'feature' => $feature], ['unit' => 'credits', 'quota' => $data['monthly_quota_credits']]);
            }
        }

        return back()->with('status', 'تم إنشاء الباقة وحصصها. لا يُعرض أي سعر قبل اعتماده.');
    }
}