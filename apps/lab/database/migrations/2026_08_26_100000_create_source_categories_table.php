<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `categories` (data-model.md §2). `parent_id` is INT in the
     * source against a BIGINT UNSIGNED `id`, with no FK there either —
     * copied as-is into `parent_source_id`; orphans and cycles are logged
     * by the ETL's validators, never repaired (FR-009, FR-015, notes N2).
     *
     * Not copied: `meta_title`, `meta_description`, `courses_card`,
     * `quizzes_card`, `events_card`, `mobile_image` — none is about a
     * question.
     *
     * No FK is declared on any `*_source_id` column anywhere in this
     * schema (data-model.md §3): orphans are a validator's job, not a
     * constraint's.
     */
    public function up(): void
    {
        Schema::create('source_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('sort_order')->nullable(); // categories.sorte_order (sic)
            $table->integer('parent_source_id')->nullable();
            $table->string('image')->nullable();

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
        Schema::dropIfExists('source_categories');
    }
};
