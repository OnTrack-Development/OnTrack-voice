<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AgentFlowRevision extends Model
{
    protected $fillable=['tenant_id','flow_id','version','graph_schema_version','status','entry_node_key','created_by','published_at','change_notes'];
    protected $casts=['published_at'=>'datetime'];
    public function flow():BelongsTo{return $this->belongsTo(AgentFlow::class,'flow_id');}
    public function nodes():HasMany{return $this->hasMany(AgentFlowNode::class,'flow_revision_id');}
    public function edges():HasMany{return $this->hasMany(AgentFlowEdge::class,'flow_revision_id');}
}