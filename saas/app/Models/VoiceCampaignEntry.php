<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCampaignEntry extends Model
{
    protected $fillable = ['tenant_id','campaign_id','phone_e164','display_name','source_fields','queue_status','attempts','telephony_outcome','business_outcome','next_attempt_at','last_call_ended_at','notes'];
    protected $casts = ['source_fields'=>'array','next_attempt_at'=>'datetime','last_call_ended_at'=>'datetime'];
    public function campaign(): BelongsTo { return $this->belongsTo(VoiceCampaign::class, 'campaign_id'); }
}