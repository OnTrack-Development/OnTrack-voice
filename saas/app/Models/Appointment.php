<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'duration_minutes' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
