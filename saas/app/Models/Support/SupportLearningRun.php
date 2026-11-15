<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

class SupportLearningRun extends Model
{
    protected $table = 'support_learning_runs';

    protected $fillable = [
        'account_id',
        'status',
        'discovered_count',
        'processed_count',
        'knowledge_version',
        'summary',
    ];

    protected $casts = [
        'discovered_count' => 'integer',
        'processed_count' => 'integer',
    ];
}
