<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2-owned (data-model.md §8). The labelled set, three purposes. `label_round`
 * and `sample_wave` are orthogonal axes — see the migration header.
 */
class DuplicateEvalPair extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'question_a_source_id', 'question_b_source_id', 'purpose',
        'label_round', 'sample_wave', 'sampled_band',
        'sim_score_at_sampling', 'embedding_config_version_at_sampling', 'media_relation',
        'human_relation', 'human_same_learning_objective', 'human_same_correct_answer',
        'human_confidence', 'labelled_by', 'labelled_at',
        'ai_suggested_relation', 'ai_suggested_confidence', 'ai_prompt_version', 'ai_suggested_at',
        'ai_suggestion_shown', 'human_relation_revised',
        'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'human_same_learning_objective' => 'boolean',
            'human_same_correct_answer' => 'boolean',
            'labelled_at' => 'datetime',
            'ai_suggested_at' => 'datetime',
            'ai_suggestion_shown' => 'boolean',
            'human_relation_revised' => 'boolean',
            'created_at' => 'datetime',
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

    public function labeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'labelled_by');
    }
}
