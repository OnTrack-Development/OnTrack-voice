<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdminGrant;
use App\Models\SpaceCounts;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Gate: middleware platform.admin on the route group.
        return view('control.dashboard', [
            'adminRoles' => PlatformAdminGrant::with('user')->latest()->get(),
            'counts' => SpaceCounts::of(),
        ]);
    }
}