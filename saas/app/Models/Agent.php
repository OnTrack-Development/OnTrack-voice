<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = [
        'tenant_id', 'project_id', 'account_id', 'site_id',
        'name', 'kind', 'voice', 'status', 'instructions',
        'knowledge', 'flow_json', 'persona', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(AgentRevision::class);
    }

    public function flowVersions(): HasMany
    {
        return $this->hasMany(AgentFlowVersion::class)->orderByDesc('revision');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'published' => 'منشور',
            'testing' => 'تحت التجربة',
            'paused' => 'متوقف مؤقتًا',
            'archived' => 'مؤرشف',
            default => 'مسودة',
        };
    }
}
