<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror. `question_source_id` joins to
     * `source_questions.source_id`, never the surrogate `id`
     * (data-model.md §1, §6).
     *
     * UNIQUE (duplicate_cluster_id, question_source_id) permits a question
     * in several clusters — correct, because clusters of different
     * `source_layer`s may overlap (FR-121). It does not permit two
     * clusters of the *same* layer holding one question; that property is
     * asserted by a test (FR-120), because a partial unique index cannot
     * see the parent's layer without a join.
     */
    public function up(): void
    {
        Schema::create('duplicate_cluster_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('duplicate_cluster_id');
            $table->unsignedBigInteger('question_source_id');
            $table->boolean('is_canonical')->default(false);
            $table->timestampTz('added_at');

            $table->unique(['duplicate_cluster_id', 'question_source_id']);
            $table->index('question_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_cluster_members');
    }
};
