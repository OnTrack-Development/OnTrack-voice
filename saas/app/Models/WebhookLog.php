<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id', 'automation_rule_id', 'call_id', 'event_type',
        'target_url', 'request_headers_json', 'payload_json', 'http_status',
        'response_body', 'attempt_number', 'status', 'error_message',
        'duration_ms', 'created_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'attempt_number' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
