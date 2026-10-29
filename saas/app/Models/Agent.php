<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agent extends Model
{
    protected $fillable = ['tenant_id', 'project_id', 'name', 'kind', 'status', 'instructions', 'persona', 'published_at'];

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

    public function statusLabel(): string
    {
        return match ($this->status) {
            'published' => 'منشور',
            'testing' => 'تحت التجربة',
            'paused' => 'متوقف مؤقتًا',
            default => 'مسودة',
        };
    }
}