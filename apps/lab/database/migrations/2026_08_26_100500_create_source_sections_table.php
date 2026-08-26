<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `sections` — where the shared stimulus lives (§8,
     * data-model.md §2). `description` is absent from `Section::$fillable`
     * in the source app but the column exists and may be populated: read
     * it, never assume it is empty (§9 item 7). `stimulus_length`,
     * `has_stimulus` and `is_long_stimulus` (> 200 chars, query 12's
     * threshold) are derived from it at copy time; `questions_count` is a
     * second pass, after `source_questions` exists (FR-013).
     */
    public function up(): void
    {
        Schema::create('source_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_source_id')->nullable();
            $table->string('name')->nullable();
            $table->integer('order')->default(1);
            $table->text('stimulus_raw')->nullable(); // faithful copy of sections.description
            $table->integer('stimulus_length')->default(0);
            $table->boolean('has_stimulus')->default(false);
            $table->boolean('is_long_stimulus')->default(false);
            $table->integer('questions_count')->default(0); // backfilled, T060

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_sections');
    }
};
