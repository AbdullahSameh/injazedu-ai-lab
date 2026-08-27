<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mirrors `questions` — the central table (data-model.md §2). There is no status column in the source; the Lab's status is the only status. */
class SourceQuestion extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'section_source_id', 'order', 'raw_text', 'explanation_raw', 'hint_raw',
        'correct_option_count', 'answer_key_state', 'options_count', 'stem_char_length',
        'has_html', 'has_img', 'is_stem_image_only', 'requires_media_review', 'source_origin',
        'source_system', 'source_id',
        'source_created_at', 'source_updated_at', 'source_deleted_at',
        'imported_at', 'import_run_id', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'has_html' => 'boolean',
            'has_img' => 'boolean',
            'is_stem_image_only' => 'boolean',
            'requires_media_review' => 'boolean',
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SourceSection::class, 'section_source_id', 'source_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(SourceQuestionOption::class, 'question_source_id', 'source_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(SourceMedia::class, 'question_source_id', 'source_id');
    }

    /**
     * Per-scope item statistics, derived by pushdown rather than mirrored
     * from individual answer rows (ADR-022).
     */
    public function itemStats(): HasMany
    {
        return $this->hasMany(SourceItemStat::class, 'question_source_id', 'source_id');
    }

    public function optionStats(): HasMany
    {
        return $this->hasMany(SourceOptionStat::class, 'question_source_id', 'source_id');
    }
}
