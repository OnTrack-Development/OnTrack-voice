<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_hash',
        'status',
        'auth_version',
    ];

    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'auth_version' => 'integer',
        ];
    }

    public function getAuthPassword(): string
    {
        $p = (string) ($this->attributes['password'] ?? '');
        if ($p !== '') {
            return $p;
        }

        return (string) ($this->attributes['password_hash'] ?? '');
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value;
        if ($value !== null && $value !== '' && empty($this->attributes['password_hash'])) {
            $this->attributes['password_hash'] = $value;
        }
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }
}
