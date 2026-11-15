<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketDraft extends Model
{
    protected $table = 'support_ticket_drafts';

    protected $fillable = [
        'ticket_id',
        'account_id',
        'agent_id',
        'draft_text',
        'reviewer_verdict',
        'reviewer_feedback',
        'evidence_summary',
        'confidence',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
