<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the raw answer mirror in favour of derived statistics (ADR-022).
     *
     * `source_answers` held 13,776,378 rows and 3.8 GB — one row per student
     * per answered question. Nothing in the program ever annotated one of
     * those rows; everything read from them was a GROUP BY. And the table is
     * unbounded: it grows with students x time, so it is the piece that
     * would not travel to a larger platform.
     *
     * `source_item_stats` and `source_option_stats` replace it at 307,382
     * rows, computed by pushing the aggregation into the source. The
     * substitution was verified exact before this ran: item counts and
     * n_correct matched on all 27,946 questions with data, p_value to
     * 5e-11 (the stored rounding), and both tables' totals reconcile to
     * 13,776,378 answer events.
     *
     * Reversible by re-running the ETL against the fixed 2026-08-07
     * snapshot, which is what constitution §III means by reproducible. The
     * `down()` below restores the shape, not the rows.
     */
    public function up(): void
    {
        Schema::dropIfExists('source_answers');
    }

    public function down(): void
    {
        Schema::create('source_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('result_source_id');
            $table->unsignedBigInteger('question_source_id');
            $table->unsignedBigInteger('option_source_id');
            $table->integer('points')->default(0);
            $table->boolean('is_correct_derived');

            $table->text('source_system');
            $table->unsignedBigInteger('source_id');
            $table->timestampTz('source_created_at')->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('source_deleted_at')->nullable();
            $table->timestampTz('imported_at');
            $table->unsignedBigInteger('import_run_id');
            $table->char('payload_hash', 64);

            $table->unique(['source_system', 'source_id']);
            $table->index('question_source_id');
            $table->index('result_source_id');
        });
    }
};
