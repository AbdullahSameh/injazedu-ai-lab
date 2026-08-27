<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `quiz_files` (data-model.md §2, notes N3, FR-035).
     * `path_unverified` is always true — Production storage is
     * unreachable locally. `quiz_files` has no soft delete, so
     * `source_deleted_at` here is permanently NULL; the column stays for
     * uniformity across the fourteen tables. Images inside
     * `questions.name` are a second, independent media path, detected via
     * `source_questions.has_img` — not represented here.
     */
    public function up(): void
    {
        Schema::create('source_media', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['video', 'image', 'audio']);
            $table->string('path')->nullable(); // nullable in the source too
            $table->unsignedBigInteger('section_source_id')->nullable();
            $table->unsignedBigInteger('question_source_id')->nullable();
            $table->enum('attach_level', ['section', 'question']);
            $table->boolean('path_unverified')->default(true);

            // Common mirror columns (data-model.md §1).
            $table->text('source_system');
            $table->unsignedBigInteger('source_id');
            $table->timestampTz('source_created_at')->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('source_deleted_at')->nullable(); // structurally always NULL — quiz_files has no soft delete (notes N3)
            $table->timestampTz('imported_at');
            $table->unsignedBigInteger('import_run_id');
            $table->char('payload_hash', 64);

            $table->unique(['source_system', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_media');
    }
};
