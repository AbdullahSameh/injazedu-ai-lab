<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P2-owned (data-model.md §5). A grouping, never a deletion. `priority_tier`
 * is stored but derived by deterministic SQL from configured percentiles —
 * no model may write it (FR-150). The five `ai_triage_*` columns are
 * advisory only (FR-153).
 */
class DuplicateCluster extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = [
        'canonical_question_source_id', 'relation_type', 'status', 'source_layer',
        'affected_student_count', 'priority_tier',
        'ai_triage_recommendation', 'ai_triage_rationale', 'ai_triage_confidence',
        'ai_triage_prompt_version', 'ai_triage_at',
        'member_count',
    ];

    protected function casts(): array
    {
        return [
            'ai_triage_confidence' => 'float',
            'ai_triage_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function canonicalQuestion(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'canonical_question_source_id', 'source_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(DuplicateClusterMember::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DuplicateReview::class);
    }
}
