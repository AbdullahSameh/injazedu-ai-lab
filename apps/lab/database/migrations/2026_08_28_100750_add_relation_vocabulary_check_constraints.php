<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * data-model.md §9: the two relation vocabularies are stored as `text`
     * with a CHECK constraint, not a Postgres `enum` type — "a new relation
     * value must cost an edit, not a type migration with data in it," the
     * same precedent `source_item_stats.scope` sets. This is the DB-level
     * half of FR-134's separation; App\Support\Dedup\RelationVocabulary is
     * the single source of truth these literal lists mirror by hand.
     *
     * `hash_match_level` and `media_relation` also get their small fixed
     * lists constrained here, for the same reason: nothing in P2 accepts an
     * arbitrary string in a column with a closed set of legal values.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE duplicate_candidates
                ADD CONSTRAINT duplicate_candidates_llm_verdict_relation_check
                CHECK (llm_verdict_relation IS NULL OR llm_verdict_relation IN (
                    'exact_duplicate', 'formatting_duplicate', 'semantic_duplicate',
                    'same_objective_variant', 'related_not_duplicate', 'conflicting_duplicate',
                    'not_related'
                ))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE duplicate_candidates
                ADD CONSTRAINT duplicate_candidates_hash_match_level_check
                CHECK (hash_match_level IS NULL OR hash_match_level IN ('exact', 'formatting', 'orthographic'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE duplicate_candidates
                ADD CONSTRAINT duplicate_candidates_media_relation_check
                CHECK (media_relation IN ('same_media', 'different_media', 'no_media'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE duplicate_clusters
                ADD CONSTRAINT duplicate_clusters_relation_type_check
                CHECK (relation_type IN (
                    'exact_duplicate', 'formatting_duplicate', 'semantic_duplicate',
                    'same_objective_variant', 'related_not_duplicate', 'conflicting_duplicate',
                    'probable_duplicate'
                ))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE duplicate_eval_pairs
                ADD CONSTRAINT duplicate_eval_pairs_human_relation_check
                CHECK (human_relation IS NULL OR human_relation IN (
                    'exact_duplicate', 'formatting_duplicate', 'semantic_duplicate',
                    'same_objective_variant', 'related_not_duplicate', 'conflicting_duplicate',
                    'not_related'
                ))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE duplicate_eval_pairs
                ADD CONSTRAINT duplicate_eval_pairs_ai_suggested_relation_check
                CHECK (ai_suggested_relation IS NULL OR ai_suggested_relation IN (
                    'exact_duplicate', 'formatting_duplicate', 'semantic_duplicate',
                    'same_objective_variant', 'related_not_duplicate', 'conflicting_duplicate',
                    'not_related'
                ))
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE duplicate_candidates DROP CONSTRAINT IF EXISTS duplicate_candidates_llm_verdict_relation_check');
        DB::statement('ALTER TABLE duplicate_candidates DROP CONSTRAINT IF EXISTS duplicate_candidates_hash_match_level_check');
        DB::statement('ALTER TABLE duplicate_candidates DROP CONSTRAINT IF EXISTS duplicate_candidates_media_relation_check');
        DB::statement('ALTER TABLE duplicate_clusters DROP CONSTRAINT IF EXISTS duplicate_clusters_relation_type_check');
        DB::statement('ALTER TABLE duplicate_eval_pairs DROP CONSTRAINT IF EXISTS duplicate_eval_pairs_human_relation_check');
        DB::statement('ALTER TABLE duplicate_eval_pairs DROP CONSTRAINT IF EXISTS duplicate_eval_pairs_ai_suggested_relation_check');
    }
};
