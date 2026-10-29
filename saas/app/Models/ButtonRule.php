<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ButtonRule extends Model
{
    protected $fillable = ['tenant_id','workspace_id','project_id','agent_id','label_ar','action_key','min_identity_level','business_state_in','condition_ast','priority','version','status'];
    protected $casts = ['business_state_in' => 'array', 'condition_ast' => 'array'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
}