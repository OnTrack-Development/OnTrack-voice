<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['tenant_id','conversation_id','role','body','delivery_status','provider_message_id','occurred_at'];
    protected $casts = ['occurred_at' => 'datetime'];
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}