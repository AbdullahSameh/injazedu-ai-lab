<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `results` — behavioural, pseudonymized (data-model.md §2,
     * FR-011, FR-037). There is no `user_id` column: `student_ref` is
     * `HMAC-SHA256(pepper, user_id)`, and `user_id` is read, hashed and
     * discarded in the same statement. `attempt_index` is a second pass
     * SQL window function, computed in Postgres (FR-038).
     * `duration_estimate_seconds` is `updated_at - created_at`, labelled
     * an approximation in the column name itself so it is never read as a
     * real duration (FR-039). Unlike `source_media` and `source_answers`,
     * `source_deleted_at` here *does* populate — `results` carries a real
     * `deleted_at` in the source.
     *
     * `student_ref` is declared NOT NULL here as originally planned; a
     * later migration (`..._make_source_results_student_ref_nullable`)
     * relaxes it once the real source turned out to have NULL `user_id`
     * on 71% of rows — see that migration for why.
     */
    public function up(): void
    {
        Schema::create('source_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_source_id')->nullable();
            $table->integer('total_points')->nullable();
            $table->char('student_ref', 64);
            $table->integer('attempt_index')->nullable(); // second pass, Postgres window function
            $table->integer('duration_estimate_seconds')->nullable();

            // Common mirror columns (data-model.md §1).
            $table->text('source_system');
            $table->unsignedBigInteger('source_id');
            $table->timestampTz('source_created_at')->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('source_deleted_at')->nullable();
            $table->timestampTz('imported_at');
            $table->unsignedBigInteger('import_run_id');
            $table->char('payload_hash', 64);

            $table->unique(['source_system', 'source_id']);
            $table->index('quiz_source_id');
            $table->index('student_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_results');
    }
};
