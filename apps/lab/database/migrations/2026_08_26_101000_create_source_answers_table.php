<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `question_result` (data-model.md §2, notes N3). Structural
     * limit, recorded not worked around: `question_result.option_id` is
     * NOT NULL in the source, so a skipped question produces no row at
     * all — "no answer" cannot be distinguished from "not shown".
     * `question_result` has no soft delete while `results` does, so
     * `source_deleted_at` here is permanently NULL and analysis-time
     * exclusion of deleted attempts must go through `source_results`.
     */
    public function up(): void
    {
        Schema::create('source_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('result_source_id');
            $table->unsignedBigInteger('question_source_id');
            $table->unsignedBigInteger('option_source_id');
            $table->integer('points')->default(0);
            $table->boolean('is_correct_derived'); // points > 0

            // Common mirror columns (data-model.md §1).
            $table->text('source_system');
            $table->unsignedBigInteger('source_id');
            $table->timestampTz('source_created_at')->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('source_deleted_at')->nullable(); // structurally always NULL — question_result has no soft delete (notes N3)
            $table->timestampTz('imported_at');
            $table->unsignedBigInteger('import_run_id');
            $table->char('payload_hash', 64);

            $table->unique(['source_system', 'source_id']);
            $table->index('question_source_id');
            $table->index('result_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_answers');
    }
};
