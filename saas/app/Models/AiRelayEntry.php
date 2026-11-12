<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRelayEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id', 'scope', 'driver', 'label', 'model', 'endpoint',
        'secret_enc', 'priority', 'enabled', 'cooldown_until',
        'last_status', 'last_checked_at', 'created_at',
    ];

    protected $hidden = ['secret_enc'];
}
