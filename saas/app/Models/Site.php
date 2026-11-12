<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    public $timestamps = false;

    protected $fillable = ['account_id', 'name', 'domain', 'status', 'created_at'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'site_id');
    }
}
