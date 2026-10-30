<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiRequestCost extends Model
{
    protected $fillable = ['provider_model_id','tenant_id','units'];
}