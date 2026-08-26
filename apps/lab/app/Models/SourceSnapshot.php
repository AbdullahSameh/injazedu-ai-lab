<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A register of `lab:profile` runs — one row per run, never overwritten
 * (FR-006). `source_row_counts` and `profiling_results` are the JSON this
 * project and its downstream consumers treat as authoritative
 * (contracts/profiling-results.md); nothing here is derived at read time.
 */
class SourceSnapshot extends Model
{
    /**
     * Explicit, not inherited from database.default: this Lab has two
     * connections (`pgsql` for its own data, `injazedu` for the guarded
     * source), and every mirror model belongs to the first regardless of
     * which one the environment happens to default to (tests default to
     * sqlite — see phpunit.xml).
     */
    protected $connection = 'pgsql';

    /** The table carries `created_at` only — it is a register, never updated in place. */
    public $timestamps = false;

    protected $fillable = [
        'snapshot_taken_at',
        'loaded_at',
        'mysql_version',
        'source_database_size_mb',
        'source_row_counts',
        'profiling_results',
        'profiling_report_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_taken_at' => 'date',
            'loaded_at' => 'datetime',
            'source_database_size_mb' => 'decimal:2',
            'source_row_counts' => 'array',
            'profiling_results' => 'array',
        ];
    }

    public function scopeLatestRun(Builder $query): Builder
    {
        return $query->latest('loaded_at');
    }
}
