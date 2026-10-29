<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWebhookEvent extends Model
{
    protected $fillable = ['tenant_id','connection_id','event_key','signature_verified_at','processing_status','payload_redacted'];
    protected $casts = ['signature_verified_at'=>'datetime','payload_redacted'=>'array'];
    public function connection(): BelongsTo { return $this->belongsTo(WhatsappConnection::class, 'connection_id'); }
}