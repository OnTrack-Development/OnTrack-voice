<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $fillable = [
        'account_id',
        'external_ticket_id',
        'source',
        'client_id',
        'client_name',
        'client_email',
        'client_phone',
        'subject',
        'department',
        'priority',
        'status',
        'assigned_agent_id',
        'assigned_human_id',
        'is_human_handoff',
        'handoff_reason',
        'latest_message',
        'context_summary',
        'metadata',
    ];

    protected $casts = [
        'is_human_handoff' => 'boolean',
        'metadata' => 'array',
    ];

    public function drafts(): HasMany
    {
        return $this->hasMany(SupportTicketDraft::class, 'ticket_id')->latest();
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(SupportConsultation::class, 'ticket_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(SupportSpecialistAgent::class, 'assigned_agent_id');
    }
}
