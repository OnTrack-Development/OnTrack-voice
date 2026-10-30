<?php

namespace Database\Seeders;

use App\Models\PlatformAdminGrant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    /**
     * Seed a platform super-admin (OnTrack internal). NEVER seeded on tenants.
     */
    public function run(): void
    {
        $email = 'platform.admin@ontrack.local';
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'OnTrack Platform Admin', 'password' => Hash::make('ChangeMe-Now-123!')]
        );
        PlatformAdminGrant::firstOrCreate(['user_id' => $user->id], ['role' => 'platform_owner']);
    }
}