<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'kind', 'parent_id', 'name', 'slug', 'status',
        'brand_name', 'logo_path', 'domain', 'created_at',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'account_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'account_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'account_id');
    }
}
