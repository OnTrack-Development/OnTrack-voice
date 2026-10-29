<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAiProviderProfile extends Model
{
    protected $fillable = [
        'type', 'name', 'base_url', 'encrypted_credentials',
        'config_json', 'connection_status', 'last_tested_at',
    ];

    protected $casts = [
        'config_json' => 'array',
        'last_tested_at' => 'datetime',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(PlatformAiModel::class, 'provider_profile_id');
    }
}