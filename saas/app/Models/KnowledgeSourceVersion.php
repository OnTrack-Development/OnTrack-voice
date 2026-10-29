<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSourceVersion extends Model
{
    protected $fillable = [
        'tenant_id', 'knowledge_source_id', 'version', 'content',
        'sha256', 'ingestion_status', 'chunk_count', 'error_message',
    ];

    public function source(): BelongsTo { return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id'); }
    public function chunks(): HasMany { return $this->hasMany(KnowledgeChunk::class, 'source_version_id'); }
}