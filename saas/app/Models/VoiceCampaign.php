<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceCampaign extends Model
{
    protected $fillable = ['tenant_id','workspace_id','agent_id','name','status','timezone','delay_seconds','max_attempts'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function entries(): HasMany { return $this->hasMany(VoiceCampaignEntry::class, 'campaign_id'); }
}