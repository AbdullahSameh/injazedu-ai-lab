<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-question item statistics, derived by pushing a GROUP BY down into
     * the read-only MySQL source — not mirrored (ADR-022).
     *
     * This table replaces the 13.8M-row `source_answers` raw mirror. Its size
     * is bounded by the *question* count, not the answer count, which is the
     * property that lets the Lab point at a platform many times this one's
     * size. Everything P3 needs from the (attempt x question) grain that
     * cannot be recovered later is stored here: `n`, `n_correct`, `p_value`,
     * and the corrected-total components of the point-biserial. `r_pbis`
     * itself is P3's to compute — one arithmetic line over these columns,
     * needing no raw rows.
     *
     * NOT a mirror, so deliberately no common mirror columns: no
     * `source_system`, `source_id`, `source_created_at` or `payload_hash`.
     * `source_snapshots` sets the same precedent — a register, not a mirror.
     * `snapshot_id` carries provenance; `stats_hash` detects change.
     *
     * `scope` is a row discriminator rather than parallel columns:
     *   'active' — attempts whose `results.deleted_at` is NULL
     *   'all'    — every attempt
     * 71% of `results` rows are soft-deleted, so the two scopes differ a
     * great deal and which one is right is P3's call, not P1's. A third
     * scope costs a row, never a migration.
     *
     * Questions with no answer data at all (1,328 of 29,142) get rows with
     * `n = 0` rather than no row, so a consumer can tell "no data" from
     * "not computed".
     */
    public function up(): void
    {
        Schema::create('source_item_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_source_id');
            $table->text('scope'); // 'active' | 'all'

            $table->integer('n');
            $table->integer('n_correct');
            $table->double('p_value')->nullable(); // NULL when n = 0

            // Point-biserial inputs, on the CORRECTED total (the attempt's
            // total_points minus this item's own points). Uncorrected, the
            // coefficient inflates itself — core plan §P3.
            $table->double('m1_corrected')->nullable(); // mean corrected total, answered correctly
            $table->double('m0_corrected')->nullable(); // mean corrected total, answered incorrectly
            $table->double('sd_corrected')->nullable(); // STDDEV_SAMP of the corrected total

            $table->timestampTz('computed_at');
            $table->unsignedBigInteger('import_run_id');
            $table->unsignedBigInteger('snapshot_id');
            $table->char('stats_hash', 64);

            $table->unique(['question_source_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_item_stats');
    }
};
