<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevicePairing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id', 'site_id', 'agent_id', 'code_hash', 'status',
        'expires_at', 'created_by', 'created_at', 'redeemed_at', 'device_id',
    ];

    protected $hidden = ['code_hash'];
}
