<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

class SupportLearningPattern extends Model
{
    protected $table = 'support_learning_patterns';

    protected $fillable = [
        'account_id',
        'pattern_key',
        'category',
        'problem_signature',
        'recommended_solution',
        'evidence_count',
        'confidence',
        'status',
    ];

    protected $casts = [
        'evidence_count' => 'integer',
        'confidence' => 'decimal:4',
    ];
}
