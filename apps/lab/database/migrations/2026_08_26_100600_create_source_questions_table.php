<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors `questions` — the central table (data-model.md §2). There is
     * no status column in the source (§9 item 10); the Lab's status is the
     * only status. `answer_key_state` defaults to `pending` and is set
     * only by the backfill pass once FR-061's policy is configured;
     * `source_origin` defaults to `unknown` — nothing else is claimed
     * without evidence (§14.5). `requires_media_review` is a second pass,
     * after `source_media` exists (FR-034).
     */
    public function up(): void
    {
        Schema::create('source_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_source_id')->nullable();
            $table->integer('order')->default(1);
            $table->longText('raw_text'); // questions.name, unmodified
            $table->text('explanation_raw')->nullable(); // questions.description
            $table->text('hint_raw')->nullable(); // questions.hint

            $table->integer('correct_option_count')->default(0);
            $table->string('answer_key_state', 20)->default('pending');
            $table->integer('options_count')->default(0);
            $table->integer('stem_char_length')->default(0);
            $table->boolean('has_html')->default(false);
            $table->boolean('has_img')->default(false);
            $table->boolean('is_stem_image_only')->default(false);
            $table->boolean('requires_media_review')->default(false); // backfilled, T061
            $table->string('source_origin', 20)->default('unknown');

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
            $table->index('section_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_questions');
    }
};
