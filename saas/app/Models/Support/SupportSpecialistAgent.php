<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportSpecialistAgent extends Model
{
    protected $table = 'support_specialist_agents';

    protected $fillable = [
        'account_id',
        'slug',
        'display_name',
        'role_title',
        'agent_type',
        'personality',
        'system_prompt',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(
            SupportContract::class,
            'support_agent_contracts',
            'agent_id',
            'contract_id'
        )->withPivot('priority', 'is_enabled')->withTimestamps();
    }
}
