<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRevision extends Model
{
    protected $fillable = ['tenant_id','agent_id','version','name','kind','instructions','persona','created_by','change_notes'];
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}