<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AgentFlow extends Model
{
    protected $fillable=['tenant_id','workspace_id','project_id','agent_id','name','status','draft_revision_id','published_revision_id'];
    public function agent(): BelongsTo{return $this->belongsTo(Agent::class);}
    public function revisions(): HasMany{return $this->hasMany(AgentFlowRevision::class,'flow_id');}
}