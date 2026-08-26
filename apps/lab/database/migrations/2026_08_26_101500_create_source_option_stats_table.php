<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-option selection counts — the distractor distribution (ADR-022).
     * Derived by pushdown like `source_item_stats`, same `scope` semantics,
     * same absence of common mirror columns.
     *
     * Built from `source_question_options LEFT JOIN` the aggregate, never
     * from the aggregate alone: 45,840 of 124,549 options (37%) were never
     * chosen by anyone. A plain GROUP BY over the answer table returns only
     * the 78,709 that were, silently dropping exactly the rows the "a
     * distractor chosen by under 2% is a dead distractor" rule exists to
     * find. A never-chosen option must appear with `chosen_n = 0`.
     *
     * `is_key` is copied from `source_question_options.is_correct_derived`
     * so a reviewer screen can rank "distractor chosen more often than the
     * key" without a join back to the bank.
     */
    public function up(): void
    {
        Schema::create('source_option_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_source_id');
            $table->unsignedBigInteger('option_source_id');
            $table->text('scope'); // 'active' | 'all'

            $table->integer('chosen_n');
            $table->double('chosen_share')->nullable(); // NULL when the question has no answers
            $table->boolean('is_key');

            $table->timestampTz('computed_at');
            $table->unsignedBigInteger('import_run_id');
            $table->unsignedBigInteger('snapshot_id');
            $table->char('stats_hash', 64);

            $table->unique(['option_source_id', 'scope']);
            // Earned: "every option of this question" is the distractor screen's
            // only access pattern (constitution VII).
            $table->index('question_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_option_stats');
    }
};
