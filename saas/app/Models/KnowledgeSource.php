<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSource extends Model
{
    protected $fillable = [
        'tenant_id', 'workspace_id', 'project_id', 'agent_id',
        'name', 'kind', 'scope', 'status', 'source_hash',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function versions(): HasMany { return $this->hasMany(KnowledgeSourceVersion::class); }
}