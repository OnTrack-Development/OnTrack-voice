<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactIdentity extends Model
{
    protected $fillable = ['tenant_id','contact_id','channel','external_subject_hash','verification_status','verified_at'];
    protected $casts = ['verified_at' => 'datetime'];
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
}