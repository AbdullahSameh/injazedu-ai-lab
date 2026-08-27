<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per `lab:import` invocation (data-model.md §2, FR-028,
     * FR-041). Not a mirror table — it carries none of the common mirror
     * columns. `resume_cursor` records (table, last confirmed source_id)
     * per data-model.md, updated after each confirmed batch and never
     * before it commits (FR-025). `ran_via` and `elapsed_seconds` are
     * recorded because P3 sizes its own batches from them, and the
     * inline/queue equivalence claim (FR-029) needs evidence, not
     * assertion.
     */
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snapshot_id');
            $table->string('kind', 20); // profile | bank | behaviour | backfill
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->string('status', 20); // running | completed | failed | resumed
            $table->unsignedBigInteger('rows_read')->default(0);
            $table->unsignedBigInteger('rows_inserted')->default(0);
            $table->unsignedBigInteger('rows_updated')->default(0);
            $table->unsignedBigInteger('rows_unchanged')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->decimal('elapsed_seconds', 10, 3)->nullable();
            $table->jsonb('resume_cursor')->nullable();
            $table->string('ran_via', 10); // inline | queue
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
