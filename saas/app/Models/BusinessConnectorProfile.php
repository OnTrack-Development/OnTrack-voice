<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessConnectorProfile extends Model
{
    protected $table = 'business_connector_profiles';
    protected $fillable = ['tenant_id','workspace_id','project_id','type','name','base_url','encrypted_credentials','status','last_tested_at'];
    protected $casts = ['last_tested_at' => 'datetime'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function endpoints(): HasMany { return $this->hasMany(ConnectorEndpoint::class); }
}