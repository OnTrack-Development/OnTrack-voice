<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TenantMembership;

class HomeController extends Controller
{
    public function index()
    {
        $membership = TenantMembership::query()
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->with('workspace')
            ->orderBy('id')
            ->first();

        abort_if(! $membership || ! $membership->workspace, 403, 'لا توجد مساحة عمل مرتبطة بحسابك.');

        return redirect('/');
    }
}