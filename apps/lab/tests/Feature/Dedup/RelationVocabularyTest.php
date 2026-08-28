<?php

namespace Tests\Feature\Dedup;

use App\Support\Dedup\RelationVocabulary;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * FR-134: the two relation vocabularies are deliberately not one enum.
 * `probable_duplicate` means "the threshold said so and nobody looked" — a
 * statement no verdict or human label may make. `not_related` never gets a
 * cluster, because no cluster is created for unrelated questions. Both the
 * PHP-level constants and the database CHECK constraints
 * (2026_08_28_100750_add_relation_vocabulary_check_constraints) are
 * asserted here, so a mismatch between them fails loudly.
 */
class RelationVocabularyTest extends TestCase
{
    public function test_probable_duplicate_is_absent_from_the_verdict_vocabulary(): void
    {
        $this->assertNotContains('probable_duplicate', RelationVocabulary::VERDICT_VALUES);
    }

    public function test_not_related_is_absent_from_the_cluster_vocabulary(): void
    {
        $this->assertNotContains('not_related', RelationVocabulary::CLUSTER_VALUES);
    }

    public function test_the_two_vocabularies_are_not_the_same_list(): void
    {
        $this->assertNotEquals(RelationVocabulary::VERDICT_VALUES, RelationVocabulary::CLUSTER_VALUES);
        $this->assertContains('not_related', RelationVocabulary::VERDICT_VALUES);
        $this->assertContains('probable_duplicate', RelationVocabulary::CLUSTER_VALUES);
    }

    public function test_database_rejects_probable_duplicate_as_a_verdict_relation(): void
    {
        $this->expectException(QueryException::class);

        DB::connection('pgsql')->table('duplicate_candidates')->insert([
            'question_a_source_id' => 1,
            'question_b_source_id' => 2,
            'same_section' => false,
            'media_relation' => 'no_media',
            'llm_verdict_relation' => 'probable_duplicate',
            'generated_at' => now(),
        ]);
    }

    public function test_database_rejects_probable_duplicate_as_a_human_label(): void
    {
        $this->expectException(QueryException::class);

        DB::connection('pgsql')->table('duplicate_eval_pairs')->insert([
            'question_a_source_id' => 1,
            'question_b_source_id' => 2,
            'purpose' => 'calibration',
            'sampled_band' => 'high',
            'human_relation' => 'probable_duplicate',
            'created_at' => now(),
        ]);
    }

    public function test_database_rejects_not_related_as_a_cluster_relation_type(): void
    {
        $this->expectException(QueryException::class);

        DB::connection('pgsql')->table('duplicate_clusters')->insert([
            'canonical_question_source_id' => 1,
            'relation_type' => 'not_related',
            'status' => 'auto',
            'source_layer' => 'hash',
            'member_count' => 1,
        ]);
    }

    public function test_database_accepts_every_legal_verdict_value(): void
    {
        foreach (RelationVocabulary::VERDICT_VALUES as $index => $value) {
            DB::connection('pgsql')->table('duplicate_candidates')->insert([
                'question_a_source_id' => 1000 + $index,
                'question_b_source_id' => 2000 + $index,
                'same_section' => false,
                'media_relation' => 'no_media',
                'llm_verdict_relation' => $value,
                'generated_at' => now(),
            ]);
        }

        $this->assertSame(
            count(RelationVocabulary::VERDICT_VALUES),
            DB::connection('pgsql')->table('duplicate_candidates')->count()
        );
    }

    public function test_database_accepts_every_legal_cluster_relation_type(): void
    {
        foreach (RelationVocabulary::CLUSTER_VALUES as $index => $value) {
            DB::connection('pgsql')->table('duplicate_clusters')->insert([
                'canonical_question_source_id' => 1000 + $index,
                'relation_type' => $value,
                'status' => 'auto',
                'source_layer' => 'hash',
                'member_count' => 1,
            ]);
        }

        $this->assertSame(
            count(RelationVocabulary::CLUSTER_VALUES),
            DB::connection('pgsql')->table('duplicate_clusters')->count()
        );
    }
}
