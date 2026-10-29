<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetKey extends Model
{
    protected $fillable = ['tenant_id','workspace_id','project_id','public_key','status','allowed_origins'];
    protected $casts = ['allowed_origins' => 'array'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}