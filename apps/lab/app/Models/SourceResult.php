<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors `results` — behavioural, pseudonymized (data-model.md §2,
 * FR-011, FR-037). There is no `user_id` column; `student_ref` is the
 * only identity carried, and it is a one-way hash.
 */
class SourceResult extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'quiz_source_id', 'total_points', 'student_ref', 'attempt_index', 'duration_estimate_seconds',
        'source_system', 'source_id',
        'source_created_at', 'source_updated_at', 'source_deleted_at',
        'imported_at', 'import_run_id', 'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'source_created_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'source_deleted_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(SourceQuiz::class, 'quiz_source_id', 'source_id');
    }

    // No answers() relation: individual answer rows are not mirrored
    // (ADR-022). Per-question and per-option statistics live in
    // `source_item_stats` and `source_option_stats`.
}
