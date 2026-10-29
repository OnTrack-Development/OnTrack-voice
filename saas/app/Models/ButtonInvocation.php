<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ButtonInvocation extends Model
{
    protected $fillable = ['tenant_id','button_rule_id','idempotency_key','state_revision','status','result_summary'];
}