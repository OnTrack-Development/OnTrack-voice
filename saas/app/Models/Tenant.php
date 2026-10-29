<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'industry', 'status', 'created_by'];

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}