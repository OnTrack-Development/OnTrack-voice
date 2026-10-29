<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAiModel extends Model
{
    protected $fillable = [
        'provider_profile_id', 'model_id_exact', 'label',
        'test_status', 'last_tested_at',
    ];

    protected $casts = [
        'last_tested_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PlatformAiProviderProfile::class, 'provider_profile_id');
    }
}