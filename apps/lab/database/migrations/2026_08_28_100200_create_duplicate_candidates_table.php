<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror. Every pair the trigram, vector
     * and hash layers proposed — canonicalised so `question_a_source_id`
     * is always the smaller (data-model.md §4). The seven `llm_*` verdict
     * fields are seven columns, not one JSON blob, so a verdict is
     * queryable; `llm_issues` is the one genuinely list-shaped field.
     *
     * `hash_match_level = 'orthographic'` (FR-142) marks a pair equal
     * under the recall-only `fuzzy_text_hash` but not under
     * `question_text_hash`. Like `formatting`, it routes to the high band
     * for a verdict or a human; unlike `exact`, no automatic path may ever
     * promote it to a cluster or an `exact_duplicate` relation.
     *
     * Three columns for verdict failure, not one (FR-122 - FR-124):
     * `verdict_failed` alone loses *why*; `verdict_attempts` alone cannot
     * express "stop trying".
     */
    public function up(): void
    {
        Schema::create('duplicate_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_a_source_id');
            $table->unsignedBigInteger('question_b_source_id');

            $table->double('trgm_score')->nullable();
            $table->double('stem_cosine_sim')->nullable();
            $table->double('full_cosine_sim')->nullable();
            $table->text('hash_match_level')->nullable(); // exact | formatting | orthographic | NULL
            $table->boolean('same_section');
            $table->text('media_relation'); // same_media | different_media | no_media
            $table->text('band')->nullable(); // exact | high | uncertain | low

            $table->text('llm_verdict_relation')->nullable();
            $table->boolean('llm_same_learning_objective')->nullable();
            $table->boolean('llm_same_correct_answer')->nullable();
            $table->double('llm_confidence')->nullable();
            $table->jsonb('llm_issues')->nullable();
            $table->text('llm_recommended_action')->nullable();
            $table->boolean('llm_review_required')->nullable();
            $table->text('llm_prompt_version')->nullable();
            $table->timestampTz('llm_verdict_at')->nullable();

            $table->integer('verdict_attempts')->default(0);
            $table->text('verdict_last_error')->nullable();
            $table->boolean('verdict_failed')->default(false);

            $table->timestampTz('generated_at');
            $table->text('embedding_config_version_at_generation')->nullable();

            $table->unique(['question_a_source_id', 'question_b_source_id']);
            $table->index('band');
            $table->index(['band', 'verdict_failed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_candidates');
    }
};
