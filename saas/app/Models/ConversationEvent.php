<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationEvent extends Model
{
    protected $fillable = ['tenant_id','conversation_id','type','payload_redacted','occurred_at'];
    protected $casts = ['payload_redacted' => 'array', 'occurred_at' => 'datetime'];
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}