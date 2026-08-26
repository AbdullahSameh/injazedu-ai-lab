<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One row per `lab:import` invocation (data-model.md §2, FR-028, FR-041). */
class ImportRun extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'snapshot_id', 'kind', 'started_at', 'finished_at', 'status',
        'rows_read', 'rows_inserted', 'rows_updated', 'rows_unchanged',
        'error_count', 'elapsed_seconds', 'resume_cursor', 'ran_via',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'elapsed_seconds' => 'decimal:3',
            'resume_cursor' => 'array',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SourceSnapshot::class, 'snapshot_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class, 'import_run_id');
    }
}
