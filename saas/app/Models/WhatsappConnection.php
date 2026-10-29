<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappConnection extends Model
{
    protected $fillable = ['tenant_id','workspace_id','label','public_id','method','phone_e164','waba_id','phone_number_id','encrypted_credentials','state','pause_override_minutes','status_reason_redacted','last_credentials_test_at','last_webhook_verified_at','last_send_test_at','last_receive_test_at'];
    protected $casts = ['pause_override_minutes'=>'integer','last_credentials_test_at'=>'datetime','last_webhook_verified_at'=>'datetime','last_send_test_at'=>'datetime','last_receive_test_at'=>'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}