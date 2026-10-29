<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantAutomationSetting extends Model
{
    protected $fillable = ['tenant_id', 'ai_reply_pause_minutes'];
    protected $casts = ['ai_reply_pause_minutes' => 'integer'];
}