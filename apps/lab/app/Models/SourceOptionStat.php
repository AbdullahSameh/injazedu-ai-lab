<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-option selection counts, derived by pushdown (ADR-022). Includes
 * never-chosen options with `chosen_n = 0` — see the migration for why that
 * matters. One row per (option, scope).
 */
class SourceOptionStat extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'question_source_id', 'option_source_id', 'scope',
        'chosen_n', 'chosen_share', 'is_key',
        'computed_at', 'import_run_id', 'snapshot_id', 'stats_hash',
    ];

    protected function casts(): array
    {
        return [
            'chosen_share' => 'float',
            'is_key' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SourceQuestionOption::class, 'option_source_id', 'source_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_source_id', 'source_id');
    }

    public function scopeActive($query)
    {
        return $query->where('scope', 'active');
    }
}
