<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectorEndpoint extends Model
{
    protected $fillable = ['tenant_id','connector_profile_id','name','method','path','permission_policy','request_schema','response_schema','status'];
    protected $casts = ['permission_policy' => 'array', 'request_schema' => 'array', 'response_schema' => 'array'];
    public function connector(): BelongsTo { return $this->belongsTo(BusinessConnectorProfile::class, 'connector_profile_id'); }
}