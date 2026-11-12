<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceBinding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id', 'site_id', 'agent_id', 'installation_id',
        'sim_slot', 'slot_claim', 'device_name', 'token_hash',
        'status', 'created_at', 'last_seen_at', 'revoked_at', 'agent_revision',
    ];

    protected $hidden = ['token_hash', 'slot_claim'];

    protected $casts = [
        'sim_slot' => 'integer',
        'agent_revision' => 'integer',
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
}
