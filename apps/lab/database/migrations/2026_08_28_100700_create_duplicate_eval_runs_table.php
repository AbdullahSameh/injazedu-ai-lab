<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror. Never overwritten — the
     * `source_snapshots` pattern applied to calibration: a re-run produces
     * a comparison, not a replacement (data-model.md §10).
     *
     * Progressive calibration writes ONE ROW PER WAVE (FR-144 - FR-146):
     * the wave history reads as a sequence (`expand`, `expand`,
     * `stop_pass`, ...) and only the row that actually settled the
     * decision carries `is_selected` (FR-065). Both precision and recall
     * 95% Wilson intervals are stored, not only point estimates, so "why
     * did we stop at wave N?" is answered by the row rather than by
     * memory.
     */
    public function up(): void
    {
        Schema::create('duplicate_eval_runs', function (Blueprint $table) {
            $table->id();
            $table->text('run_kind'); // calibration | embedder_benchmark
            $table->text('embedder_model');
            $table->integer('embedder_dimension');
            $table->text('embedding_config_version')->nullable();
            $table->integer('eval_pair_count');
            $table->smallInteger('sample_wave')->nullable();
            $table->integer('positive_class_count')->nullable();

            $table->double('recall_at_20')->nullable();
            $table->double('precision_at_threshold')->nullable();
            $table->double('precision_ci_low')->nullable();
            $table->double('precision_ci_high')->nullable();
            $table->double('recall_at_threshold')->nullable();
            $table->double('recall_ci_low')->nullable();
            $table->double('recall_ci_high')->nullable();
            $table->text('expansion_decision')->nullable(); // expand | stop_pass | stop_fail

            $table->double('threshold_low')->nullable();
            $table->double('threshold_high')->nullable();
            $table->integer('projected_uncertain_band_count')->nullable();
            $table->double('storage_mb')->nullable();
            $table->double('time_per_1k_ms')->nullable();
            $table->boolean('gate_passed')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->double('inter_rater_agreement')->nullable();

            $table->timestampTz('computed_at');
            $table->text('notes')->nullable();

            $table->index('run_kind');
            $table->index('sample_wave');
            $table->index('is_selected');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_eval_runs');
    }
};
