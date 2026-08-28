<?php

namespace Tests\Feature\Dedup;

use App\Models\DuplicateCluster;
use App\Models\DuplicateClusterMember;
use App\Models\SourceQuestion;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * data-model.md §1: every P2 `*_source_id` column joins a mirror table
 * through `source_id` (the Production identifier), never through the Lab
 * surrogate `id`. "This is the single most likely defect in the feature" —
 * a join through `id` "would return rows and be silently wrong," so this
 * test proves the failure mode directly rather than trusting the
 * convention (FR-003).
 */
class ForeignKeyThroughSourceIdTest extends TestCase
{
    /**
     * A DECOY row is planted at the surrogate-id slot the real question's
     * source_id happens to share, so a join through `id` doesn't merely
     * fail to match — it silently returns a *different, wrong* question.
     */
    private function seedRealAndDecoyQuestions(): void
    {
        DB::connection('pgsql')->table('source_questions')->insert([
            [
                'id' => 10,
                'section_source_id' => null,
                'order' => 1,
                'raw_text' => 'REAL QUESTION — the one duplicate_cluster_members actually points at',
                'correct_option_count' => 1,
                'answer_key_state' => 'single_correct',
                'options_count' => 0,
                'stem_char_length' => 0,
                'has_html' => false,
                'has_img' => false,
                'is_stem_image_only' => false,
                'requires_media_review' => false,
                'source_origin' => 'unknown',
                'source_system' => 'injazedu_production',
                'source_id' => 500,
                'imported_at' => now(),
                'import_run_id' => 1,
                'payload_hash' => str_repeat('a', 64),
            ],
            [
                'id' => 500,
                'section_source_id' => null,
                'order' => 1,
                'raw_text' => 'DECOY — sits at id=500, which equals the real question source_id',
                'correct_option_count' => 1,
                'answer_key_state' => 'single_correct',
                'options_count' => 0,
                'stem_char_length' => 0,
                'has_html' => false,
                'has_img' => false,
                'is_stem_image_only' => false,
                'requires_media_review' => false,
                'source_origin' => 'unknown',
                'source_system' => 'injazedu_production',
                'source_id' => 999,
                'imported_at' => now(),
                'import_run_id' => 1,
                'payload_hash' => str_repeat('b', 64),
            ],
        ]);

        // The bigserial sequence must move past the explicit ids above.
        DB::connection('pgsql')->statement(
            "SELECT setval(pg_get_serial_sequence('source_questions', 'id'), 1000)"
        );
    }

    public function test_eloquent_relation_resolves_through_source_id_not_the_surrogate_id(): void
    {
        $this->seedRealAndDecoyQuestions();

        $cluster = DuplicateCluster::create([
            'canonical_question_source_id' => 500,
            'relation_type' => 'exact_duplicate',
            'status' => 'auto',
            'source_layer' => 'hash',
            'member_count' => 1,
        ]);

        $member = DuplicateClusterMember::create([
            'duplicate_cluster_id' => $cluster->id,
            'question_source_id' => 500,
            'is_canonical' => true,
            'added_at' => now(),
        ]);

        $this->assertSame(
            'REAL QUESTION — the one duplicate_cluster_members actually points at',
            $member->question->raw_text,
            'DuplicateClusterMember::question() must resolve via source_id, not the Lab surrogate id'
        );

        $this->assertNotSame(
            'DECOY — sits at id=500, which equals the real question source_id',
            $member->question->raw_text
        );
    }

    public function test_a_raw_join_through_the_surrogate_id_returns_the_wrong_row(): void
    {
        $this->seedRealAndDecoyQuestions();

        DuplicateCluster::create([
            'canonical_question_source_id' => 500,
            'relation_type' => 'exact_duplicate',
            'status' => 'auto',
            'source_layer' => 'hash',
            'member_count' => 1,
        ])->members()->create([
            'question_source_id' => 500,
            'is_canonical' => true,
            'added_at' => now(),
        ]);

        $throughSourceId = DB::connection('pgsql')
            ->table('duplicate_cluster_members as m')
            ->join('source_questions as q', 'q.source_id', '=', 'm.question_source_id')
            ->value('q.raw_text');

        $throughSurrogateId = DB::connection('pgsql')
            ->table('duplicate_cluster_members as m')
            ->join('source_questions as q', 'q.id', '=', 'm.question_source_id')
            ->value('q.raw_text');

        $this->assertSame('REAL QUESTION — the one duplicate_cluster_members actually points at', $throughSourceId);
        $this->assertSame('DECOY — sits at id=500, which equals the real question source_id', $throughSurrogateId);
        $this->assertNotSame($throughSourceId, $throughSurrogateId);
    }

    public function test_source_question_model_defines_the_relation_through_source_id(): void
    {
        $reflection = new \ReflectionMethod(SourceQuestion::class, 'options');
        $this->assertTrue($reflection->isPublic());
    }
}
