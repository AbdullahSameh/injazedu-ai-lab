<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A register of `lab:profile` runs, not a mirror table — it carries none
     * of data-model.md §1's common mirror columns (FR-006). One row per run
     * against the fixed 2026-08-07 snapshot, so a re-run compares rather than
     * overwrites. `profiling_results` is the authoritative record contracts/
     * profiling-results.md defines; `profiling_report_path` names the file
     * generated from it, never the other way around (FR-004, FR-005).
     */
    public function up(): void
    {
        Schema::create('source_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_taken_at');
            $table->timestampTz('loaded_at');
            $table->string('mysql_version');
            $table->decimal('source_database_size_mb', 10, 2);
            $table->jsonb('source_row_counts');
            $table->jsonb('profiling_results');
            $table->string('profiling_report_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_snapshots');
    }
};
