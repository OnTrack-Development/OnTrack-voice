<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    protected $fillable = [
        'account_id', 'site_id', 'agent_id', 'name', 'trigger_event',
        'condition_field', 'condition_operator', 'condition_value',
        'action_type', 'webhook_url', 'webhook_secret_enc',
        'whatsapp_template', 'crm_status', 'enabled',
        'trigger_count', 'last_triggered_at',
    ];

    protected $hidden = ['webhook_secret_enc'];

    protected $casts = [
        'enabled' => 'boolean',
        'trigger_count' => 'integer',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class, 'automation_rule_id');
    }
}
