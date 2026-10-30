<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialWebhookEvent extends Model
{
    protected $fillable = ['tenant_id','social_connection_id','event_key','processing_status','payload_redacted'];
    protected $casts = ['payload_redacted'=>'array'];
    public function connection(): BelongsTo { return $this->belongsTo(SocialConnection::class, 'social_connection_id'); }
}