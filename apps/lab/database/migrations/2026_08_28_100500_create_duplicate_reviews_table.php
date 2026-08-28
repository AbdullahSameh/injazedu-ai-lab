<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2-owned. NOT part of the P1 mirror. The one irreproducible artefact
     * in the Lab (data-model.md §7) — append-only, a human decision never
     * overwrites the AI verdict, which is what makes "how often was the
     * model wrong?" answerable.
     *
     * `reviewer_id` -> `users.id`, the Lab's own operator accounts, never a
     * Production/InjazEdu identity. It is deliberately not named
     * `user_id`, which is why `NoPiiInLabSchemaTest` passes with no edit
     * and no exemption (notes.md N7) — its forbidden list names the
     * literal `user_id`, not every `*_id` column.
     */
    public function up(): void
    {
        Schema::create('duplicate_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('duplicate_cluster_id');
            $table->text('decision'); // same | valid_variant | not_duplicate | conflict | skip
            $table->unsignedBigInteger('reviewer_id');
            $table->timestampTz('reviewed_at');

            $table->text('previous_status')->nullable();
            $table->text('new_status')->nullable();
            $table->text('previous_relation_type')->nullable();
            $table->text('new_relation_type')->nullable();
            $table->text('notes')->nullable();

            $table->index('duplicate_cluster_id');
            $table->index('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_reviews');
    }
};
