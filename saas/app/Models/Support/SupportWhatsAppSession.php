<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

class SupportWhatsAppSession extends Model
{
    protected $table = 'support_whatsapp_sessions';

    protected $fillable = [
        'account_id',
        'phone',
        'phone_hash',
        'driver',
        'client_id',
        'is_verified',
        'verified_at',
        'verification_token',
        'verification_expires_at',
        'state',
        'state_payload',
        'is_muted_for_human',
        'muted_until',
        'last_message_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_muted_for_human' => 'boolean',
        'verified_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'muted_until' => 'datetime',
        'last_message_at' => 'datetime',
        'state_payload' => 'array',
    ];
}
