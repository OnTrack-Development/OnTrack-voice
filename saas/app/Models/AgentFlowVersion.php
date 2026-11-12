<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentFlowVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id', 'site_id', 'agent_id', 'revision', 'flow_hash', 'snapshot_hash',
        'flow_json', 'voice', 'instructions', 'knowledge', 'published_by',
        'published_at', 'answer_policy_json',
    ];
}
