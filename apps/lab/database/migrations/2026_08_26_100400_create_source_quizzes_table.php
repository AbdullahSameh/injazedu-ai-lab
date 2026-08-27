<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `quizzes` (data-model.md §2). `sort_order` is spelled
     * correctly in this one source table — notes N4 — so it is copied
     * as `sort_order` here, never through the `sorte_order` mapping the
     * other four tables use.
     *
     * `user_id` (the quiz author) is not copied: attribution is lost at
     * the quiz level and that is accepted (§5 — there is no author at the
     * question level). Not copied: `image`, `meta_title`, `meta_description`.
     */
    public function up(): void
    {
        Schema::create('source_quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->nullable()->default(1);
            $table->integer('duration')->default(10);
            $table->string('hint')->nullable();
            $table->unsignedBigInteger('course_source_id')->nullable(); // NULL => general/open quiz
            $table->unsignedBigInteger('category_source_id')->nullable();
            $table->unsignedBigInteger('lecture_source_id')->nullable();

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
        Schema::dropIfExists('source_quizzes');
    }
};
