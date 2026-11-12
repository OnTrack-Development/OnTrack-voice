<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'tenant_id', 'account_id', 'site_id', 'display_name',
        'name', 'email', 'phone_e164', 'phone', 'dnc',
        'gender_hint', 'lead_status', 'assigned_user_id', 'tags', 'notes',
    ];

    protected $casts = [
        'tags' => 'array',
        'dnc' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(ContactIdentity::class);
    }
}
