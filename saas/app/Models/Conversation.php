<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'tenant_id','workspace_id','project_id','agent_id','contact_key','channel','status',
        'human_locked_at','ai_pause_until_utc','last_human_reply_at_utc',
        'pause_generation','conversation_version','assigned_user_id',
    ];

    protected $casts = [
        'human_locked_at' => 'datetime',
        'ai_pause_until_utc' => 'datetime',
        'last_human_reply_at_utc' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function messages(): HasMany { return $this->hasMany(Message::class)->orderBy('occurred_at'); }
    public function events(): HasMany { return $this->hasMany(ConversationEvent::class); }

    public function isHumanLocked(): bool { return $this->human_locked_at !== null; }
    public function isAiPaused(): bool { return $this->ai_pause_until_utc !== null && $this->ai_pause_until_utc->isFuture(); }
}