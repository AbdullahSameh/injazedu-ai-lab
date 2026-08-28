<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * P2-owned (data-model.md §10). Never overwritten — a re-run produces a
 * comparison row, not a replacement, following the `source_snapshots`
 * pattern. Progressive calibration writes one row per wave.
 */
class DuplicateEvalRun extends Model
{
    protected $connection = 'pgsql';

    public $timestamps = false;

    protected $fillable = [
        'run_kind', 'embedder_model', 'embedder_dimension', 'embedding_config_version',
        'eval_pair_count', 'sample_wave', 'positive_class_count',
        'recall_at_20', 'precision_at_threshold', 'precision_ci_low', 'precision_ci_high',
        'recall_at_threshold', 'recall_ci_low', 'recall_ci_high', 'expansion_decision',
        'threshold_low', 'threshold_high', 'projected_uncertain_band_count',
        'storage_mb', 'time_per_1k_ms', 'gate_passed', 'is_selected', 'inter_rater_agreement',
        'computed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'gate_passed' => 'boolean',
            'is_selected' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }
}
