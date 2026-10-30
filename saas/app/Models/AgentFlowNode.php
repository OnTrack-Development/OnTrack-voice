<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AgentFlowNode extends Model
{
    protected $fillable=['tenant_id','flow_revision_id','stable_key','type','title','description','position_x','position_y','config_json','status'];
    protected $casts=['position_x'=>'float','position_y'=>'float','config_json'=>'array'];
    public function revision():BelongsTo{return $this->belongsTo(AgentFlowRevision::class,'flow_revision_id');}
}