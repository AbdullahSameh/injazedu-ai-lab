<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror. The labelled set, three
     * purposes: calibration, spot-check, uncertain-band review
     * (data-model.md §8).
     *
     * `label_round` is in the UNIQUE key so the doubled subsample (FR-056)
     * is a second row on the same pair with `label_round = 2` and a
     * different `labelled_by` — inter-rater agreement is then one
     * self-join. `sample_wave` is a SEPARATE, indexed column and
     * deliberately NOT in the key: a pair is drawn in exactly one wave,
     * and `label_round` says *who labelled it* while `sample_wave` says
     * *which expansion drew it*. Conflating the two axes would make
     * waves 2 and 3 silently read as second and third labellers and
     * corrupt inter-rater agreement (data-model.md §8, FR-050, FR-056).
     *
     * The six `ai_*`/revision columns keep the ground truth separable by
     * construction (FR-147, FR-148): the AI suggestion is stored beside
     * the human label, never in it, and `ai_suggestion_shown` is set only
     * AFTER `human_relation` is recorded.
     */
    public function up(): void
    {
        Schema::create('duplicate_eval_pairs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_a_source_id');
            $table->unsignedBigInteger('question_b_source_id');
            $table->text('purpose'); // calibration | spot_check | uncertain_review
            $table->smallInteger('label_round')->default(1);
            $table->smallInteger('sample_wave')->default(1);
            $table->text('sampled_band');
            $table->double('sim_score_at_sampling')->nullable();
            $table->text('embedding_config_version_at_sampling')->nullable();
            $table->text('media_relation')->nullable();

            $table->text('human_relation')->nullable();
            $table->boolean('human_same_learning_objective')->nullable();
            $table->boolean('human_same_correct_answer')->nullable();
            $table->double('human_confidence')->nullable();
            $table->unsignedBigInteger('labelled_by')->nullable();
            $table->timestampTz('labelled_at')->nullable();

            $table->text('ai_suggested_relation')->nullable();
            $table->double('ai_suggested_confidence')->nullable();
            $table->text('ai_prompt_version')->nullable();
            $table->timestampTz('ai_suggested_at')->nullable();
            $table->boolean('ai_suggestion_shown')->default(false);
            $table->boolean('human_relation_revised')->default(false);

            $table->text('notes')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['question_a_source_id', 'question_b_source_id', 'purpose', 'label_round']);
            $table->index('purpose');
            $table->index('sample_wave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_eval_pairs');
    }
};
