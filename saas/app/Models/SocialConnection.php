<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialConnection extends Model
{
    protected $fillable = ['tenant_id','workspace_id','channel','account_label','external_account_id','encrypted_config','encrypted_webhook_secret','state','capabilities','status_reason_redacted','last_tested_at'];
    protected $casts = ['capabilities'=>'array','last_tested_at'=>'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}