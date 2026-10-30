<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AgentFlowEdge extends Model
{
    protected $fillable=['tenant_id','flow_revision_id','source_node_id','target_node_id','label','type','condition_json','priority','is_default'];
    protected $casts=['condition_json'=>'array','is_default'=>'boolean'];
    public function revision():BelongsTo{return $this->belongsTo(AgentFlowRevision::class,'flow_revision_id');}
    public function source():BelongsTo{return $this->belongsTo(AgentFlowNode::class,'source_node_id');}
    public function target():BelongsTo{return $this->belongsTo(AgentFlowNode::class,'target_node_id');}
}