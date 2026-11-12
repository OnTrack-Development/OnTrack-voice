<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'provider', 'tier', 'ready', 'status',
        'gender', 'dialect', 'source_ref_id', 'sample_path', 'sample_url', 'meta_json',
    ];

    protected $casts = [
        'ready' => 'boolean',
    ];
}
