<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id', 'site_id', 'phone', 'name', 'duration_seconds',
        'status', 'direction', 'is_demo', 'created_at', 'external_call_id',
        'device_binding_id', 'sim_slot', 'ended_at', 'agent_id', 'agent_name',
        'caller_gender', 'ai_rating', 'ai_mood', 'recording_url', 'recording_duration',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'is_demo' => 'integer',
        'sim_slot' => 'integer',
        'ai_rating' => 'integer',
        'recording_duration' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function deviceBinding(): BelongsTo
    {
        return $this->belongsTo(DeviceBinding::class, 'device_binding_id');
    }
}
