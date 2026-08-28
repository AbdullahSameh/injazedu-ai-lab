<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2-owned (data-model.md §4). Every pair the trigram, vector and hash
 * layers proposed, canonicalised so `question_a_source_id` is always the
 * smaller. The seven `llm_*` fields are seven columns, not one blob, so a
 * verdict is queryable.
 */
class DuplicateCandidate extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'question_a_source_id', 'question_b_source_id',
        'trgm_score', 'stem_cosine_sim', 'full_cosine_sim',
        'hash_match_level', 'same_section', 'media_relation', 'band',
        'llm_verdict_relation', 'llm_same_learning_objective', 'llm_same_correct_answer',
        'llm_confidence', 'llm_issues', 'llm_recommended_action', 'llm_review_required',
        'llm_prompt_version', 'llm_verdict_at',
        'verdict_attempts', 'verdict_last_error', 'verdict_failed',
        'generated_at', 'embedding_config_version_at_generation',
    ];

    protected function casts(): array
    {
        return [
            'same_section' => 'boolean',
            'llm_same_learning_objective' => 'boolean',
            'llm_same_correct_answer' => 'boolean',
            'llm_review_required' => 'boolean',
            'llm_issues' => 'array',
            'verdict_failed' => 'boolean',
            'llm_verdict_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function questionA(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_a_source_id', 'source_id');
    }

    public function questionB(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_b_source_id', 'source_id');
    }
}
