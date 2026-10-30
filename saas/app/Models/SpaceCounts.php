<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

final class SpaceCounts
{
    public static function of(): array
    {
        return [
            'tenants' => (int) DB::table('tenants')->count(),
            'users' => (int) DB::table('users')->count(),
            'agents' => (int) DB::table('agents')->count(),
            'conversations' => (int) DB::table('conversations')->count(),
            'providers' => (int) DB::table('platform_ai_provider_profiles')->count(),
            'plans' => (int) DB::table('plans')->count(),
        ];
    }
}