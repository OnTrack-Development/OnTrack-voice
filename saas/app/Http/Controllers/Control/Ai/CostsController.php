<?php

namespace App\Http\Controllers\Control\Ai;

use App\Http\Controllers\Controller;
use App\Models\PlatformAiRequestCost;

class CostsController extends Controller
{
    public function index()
    {
        return view('control.ai.costs', [
            'costs' => PlatformAiRequestCost::query()->latest()->limit(100)->get(),
            'totalUnits' => (int) PlatformAiRequestCost::sum('units'),
        ]);
    }
}