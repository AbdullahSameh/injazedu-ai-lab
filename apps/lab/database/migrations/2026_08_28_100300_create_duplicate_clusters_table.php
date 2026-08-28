<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror. A grouping, never a deletion —
     * no source question is ever removed (data-model.md §5).
     *
     * `priority_tier` (FR-150) is a stored, recomputable derivation from
     * the measured `affected_student_count` distribution, never a
     * judgement — no model may write it. The five `ai_triage_*` columns
     * are prefixed for exactly one reason: so a reviewer, a query and a
     * test can all tell at a glance that nothing in them is measured
     * (FR-153). Only a `duplicate_reviews` row may move a conflict out of
     * `urgent_review` (FR-129).
     *
     * INDEX (status, priority_tier, affected_student_count DESC) is the
     * backlog's ordering (FR-089) — it must never fall back to `id`.
     */
    public function up(): void
    {
        Schema::create('duplicate_clusters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('canonical_question_source_id');
            $table->text('relation_type');
            $table->text('status'); // auto | pending_review | confirmed | rejected | urgent_review | resolved | skipped
            $table->text('source_layer'); // hash | high_band_auto | llm_verdict | human_manual
            $table->integer('affected_student_count')->nullable();
            $table->text('priority_tier')->nullable(); // tier_1_critical .. tier_4_deferred

            $table->text('ai_triage_recommendation')->nullable();
            $table->text('ai_triage_rationale')->nullable();
            $table->double('ai_triage_confidence')->nullable();
            $table->text('ai_triage_prompt_version')->nullable();
            $table->timestampTz('ai_triage_at')->nullable();

            $table->integer('member_count');
            $table->timestampsTz();

            $table->index('canonical_question_source_id');
            $table->index('status');
            $table->index('source_layer');
            $table->index('priority_tier');
            $table->index(['status', 'priority_tier', 'affected_student_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_clusters');
    }
};
