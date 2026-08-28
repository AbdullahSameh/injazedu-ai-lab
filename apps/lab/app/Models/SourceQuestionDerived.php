<?php

namespace App\Models;

use App\Casts\AsVector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2-owned (data-model.md §2). One row per `source_questions` row,
 * including soft-deleted ones. `question_source_id` joins the mirror
 * through `source_id`, never the surrogate `id` (data-model.md §1).
 */
class SourceQuestionDerived extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'source_question_derived'; // Eloquent's guesser wrongly pluralizes "derived" to "deriveds"

    public $timestamps = false;

    protected $fillable = [
        'question_source_id',
        'clean_text', 'search_text',
        'question_text_hash', 'question_with_options_hash',
        'fuzzy_text_hash', 'fuzzy_rules_version',
        'media_fingerprint', 'normalizer_version',
        'stem_embedding', 'full_embedding', 'embedding_config_version',
        'stem_truncated', 'full_truncated',
        'text_computed_at', 'embedded_at',
    ];

    protected function casts(): array
    {
        return [
            'stem_embedding' => AsVector::class,
            'full_embedding' => AsVector::class,
            'stem_truncated' => 'boolean',
            'full_truncated' => 'boolean',
            'text_computed_at' => 'datetime',
            'embedded_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_source_id', 'source_id');
    }
}
