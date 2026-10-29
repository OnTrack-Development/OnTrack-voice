<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeChunk extends Model
{
    protected $fillable = ['tenant_id', 'source_version_id', 'chunk_index', 'content'];

    public function version(): BelongsTo { return $this->belongsTo(KnowledgeSourceVersion::class, 'source_version_id'); }
}