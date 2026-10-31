<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoicePaymentOrder extends Model
{
    protected $fillable = [
        'tenant_id','plan_id','reference','amount_minor','currency','status',
        'mode','kashier_session_id','kashier_transaction_id','paid_at',
    ];
    protected $casts = ['paid_at'=>'datetime'];
}
