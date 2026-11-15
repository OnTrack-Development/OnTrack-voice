<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

class SupportContract extends Model
{
    protected $table = 'support_contracts';

    protected $fillable = [
        'account_id',
        'code',
        'name',
        'description',
        'mission',
        'allowed_tools',
        'read_permissions',
        'action_permissions',
        'forbidden_actions',
        'escalation_rules',
        'status',
    ];

    protected $casts = [
        'allowed_tools' => 'array',
        'read_permissions' => 'array',
        'action_permissions' => 'array',
        'forbidden_actions' => 'array',
        'escalation_rules' => 'array',
    ];
}
