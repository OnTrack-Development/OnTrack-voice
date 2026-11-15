<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportConsultation extends Model
{
    protected $table = 'support_consultations';

    protected $fillable = [
        'ticket_id',
        'account_id',
        'requester_agent_slug',
        'specialist_agent_slug',
        'focus',
        'query',
        'response',
        'evidence',
        'status',
        'elapsed_ms',
    ];

    protected $casts = [
        'evidence' => 'array',
        'elapsed_ms' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
