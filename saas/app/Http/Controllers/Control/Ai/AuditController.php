<?php

namespace App\Http\Controllers\Control\Ai;

use App\Http\Controllers\Controller;
use App\Models\PlatformAiAuditEvent;

class AuditController extends Controller
{
    public function index()
    {
        return view('control.ai.audit', [
            'events' => PlatformAiAuditEvent::with('actor')->latest()->limit(100)->get(),
        ]);
    }
}