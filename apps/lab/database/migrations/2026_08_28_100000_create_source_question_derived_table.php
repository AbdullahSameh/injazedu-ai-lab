<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror — one row per `source_questions`
     * row, including soft-deleted ones (FR-020, data-model.md §2). Joins to
     * the mirror through `question_source_id` -> `source_questions.source_id`,
     * never through the Lab surrogate `id` (data-model.md §1).
     *
     * `fuzzy_text_hash` is a recall-only candidate-grouping key (FR-141,
     * FR-142) — never a cluster key, never an identity decision — so it
     * gets a plain btree, not the trigram GIN index Phase 5 earns for
     * `search_text`. `fuzzy_rules_version` is deliberately separate from
     * `normalizer_version`: the strict hashes do not depend on the fold, so
     * disabling it must not make a single strict hash look stale.
     *
     * No trigram index here — Phase 5 creates it once its query needs it
     * (FR-008, FR-043, constitution VII).
     */
    public function up(): void
    {
        Schema::create('source_question_derived', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_source_id');

            $table->text('clean_text');
            $table->text('search_text');
            $table->char('question_text_hash', 64);
            $table->char('question_with_options_hash', 64);
            $table->char('fuzzy_text_hash', 64)->nullable();
            $table->text('fuzzy_rules_version')->nullable();
            $table->char('media_fingerprint', 64)->nullable();
            $table->text('normalizer_version');

            $table->vector('stem_embedding', 768)->nullable();
            $table->vector('full_embedding', 768)->nullable();
            $table->text('embedding_config_version')->nullable();
            $table->boolean('stem_truncated')->default(false);
            $table->boolean('full_truncated')->default(false);

            $table->timestampTz('text_computed_at')->nullable();
            $table->timestampTz('embedded_at')->nullable();

            $table->unique('question_source_id');
            $table->index('question_text_hash');
            $table->index('question_with_options_hash');
            $table->index('fuzzy_text_hash');
            $table->index('media_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_question_derived');
    }
};
