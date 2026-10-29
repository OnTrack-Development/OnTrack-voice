<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetSession extends Model
{
    protected $fillable = ['tenant_id','widget_key_id','conversation_id','session_hash','subject_hash','last_seen_at'];
    protected $casts = ['last_seen_at' => 'datetime'];
    public function widgetKey(): BelongsTo { return $this->belongsTo(WidgetKey::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}