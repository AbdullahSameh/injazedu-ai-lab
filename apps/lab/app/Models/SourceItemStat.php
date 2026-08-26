<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-question item statistics, derived by pushdown (ADR-022). Not a mirror:
 * no `source_id`, no `payload_hash`. One row per (question, scope).
 */
class SourceItemStat extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'question_source_id', 'scope',
        'n', 'n_correct', 'p_value',
        'm1_corrected', 'm0_corrected', 'sd_corrected',
        'computed_at', 'import_run_id', 'snapshot_id', 'stats_hash',
    ];

    protected function casts(): array
    {
        return [
            'p_value' => 'float',
            'm1_corrected' => 'float',
            'm0_corrected' => 'float',
            'sd_corrected' => 'float',
            'computed_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SourceQuestion::class, 'question_source_id', 'source_id');
    }

    public function optionStats(): HasMany
    {
        return $this->hasMany(SourceOptionStat::class, 'question_source_id', 'question_source_id');
    }

    public function scopeActive($query)
    {
        return $query->where('scope', 'active');
    }
}
