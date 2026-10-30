<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageOutbox extends Model
{
    protected $table = 'message_outbox';
    protected $fillable = ['tenant_id','conversation_id','agent_id','kind','idempotency_key','body','scheduled_at','evaluated_conversation_version','status','last_block_reason','attempt_count','provider_message_id','occurred_at'];
    protected $casts = ['scheduled_at'=>'datetime','occurred_at'=>'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}