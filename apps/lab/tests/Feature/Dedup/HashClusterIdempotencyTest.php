<?php

namespace Tests\Feature\Dedup;

use App\Console\Commands\LabDedup;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** T044: the public hash-cluster step is a stable projection on rerun. */
class HashClusterIdempotencyTest extends TestCase
{
    public function test_hash_cluster_command_rerun_creates_no_extra_candidates_or_members(): void
    {
        config(['lab.dedup.fuzzy_fold_enabled' => true]);
        $this->snapshot();

        // One strict full-hash group becomes a cluster.
        $this->questionWithDerived(10, 'exact-full', 'exact-stem', 'exact-fuzzy');
        $this->questionWithDerived(20, 'exact-full', 'exact-stem', 'exact-fuzzy');
        // Same strict stem but different full hash proposes a formatting pair.
        $this->questionWithDerived(30, 'format-full-a', 'format-stem', 'format-fuzzy-a');
        $this->questionWithDerived(40, 'format-full-b', 'format-stem', 'format-fuzzy-b');
        // Same recall-only fold but different strict stem proposes an orthographic pair.
        $this->questionWithDerived(50, 'orthographic-full-a', 'orthographic-stem-a', 'orthographic-fuzzy');
        $this->questionWithDerived(60, 'orthographic-full-b', 'orthographic-stem-b', 'orthographic-fuzzy');

        $this->assertSame(0, Artisan::call(LabDedup::class, ['--step' => 'hash-cluster']));
        $this->assertStringContainsString(
            'Hash clustering: 1 exact cluster(s), 1 formatting candidate(s). Orthographic candidates: 1.',
            Artisan::output(),
        );

        $firstClusters = $this->clusterProjection();
        $firstClusterCount = DB::connection('pgsql')->table('duplicate_clusters')
            ->where('source_layer', 'hash')
            ->count();
        $firstMemberCount = DB::connection('pgsql')->table('duplicate_cluster_members')->count();
        $firstCandidateCount = DB::connection('pgsql')->table('duplicate_candidates')->count();

        $this->assertSame([[
            'canonical_question_source_id' => 10,
            'member_count' => 2,
            'members' => [
                ['question_source_id' => 10, 'is_canonical' => true],
                ['question_source_id' => 20, 'is_canonical' => false],
            ],
        ]], $firstClusters);
        $this->assertSame(1, $firstClusterCount);
        $this->assertSame(2, $firstMemberCount);
        $this->assertSame(2, $firstCandidateCount);
        $this->assertSame([
            [30, 40, 'formatting'],
            [50, 60, 'orthographic'],
        ], $this->candidateProjection());

        $this->assertSame(0, Artisan::call(LabDedup::class, ['--step' => 'hash-cluster']));
        $secondRun = ImportRun::where('kind', 'p2_hash_cluster')->latest('id')->firstOrFail();
        $secondClusterCount = DB::connection('pgsql')->table('duplicate_clusters')
            ->where('source_layer', 'hash')
            ->count();

        $this->assertSame($firstClusters, $this->clusterProjection());
        $this->assertSame($firstClusterCount, $secondClusterCount);
        $this->assertSame($firstMemberCount, DB::connection('pgsql')->table('duplicate_cluster_members')->count());
        $this->assertSame($firstCandidateCount, DB::connection('pgsql')->table('duplicate_candidates')->count());
        $this->assertSame(0, $secondRun->rows_inserted);
    }

    /** @return list<array{canonical_question_source_id: int, member_count: int, members: list<array{question_source_id: int, is_canonical: bool}>}> */
    private function clusterProjection(): array
    {
        return DB::connection('pgsql')->table('duplicate_clusters as cluster')
            ->join('duplicate_cluster_members as member', 'member.duplicate_cluster_id', '=', 'cluster.id')
            ->where('cluster.source_layer', 'hash')
            ->orderBy('cluster.canonical_question_source_id')
            ->orderBy('member.question_source_id')
            ->get([
                'cluster.canonical_question_source_id',
                'cluster.member_count',
                'member.question_source_id',
                'member.is_canonical',
            ])
            ->groupBy('canonical_question_source_id')
            ->map(static fn ($members): array => [
                'canonical_question_source_id' => (int) $members->first()->canonical_question_source_id,
                'member_count' => (int) $members->first()->member_count,
                'members' => $members->map(static fn (object $member): array => [
                    'question_source_id' => (int) $member->question_source_id,
                    'is_canonical' => (bool) $member->is_canonical,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{0: int, 1: int, 2: string}> */
    private function candidateProjection(): array
    {
        return DB::connection('pgsql')->table('duplicate_candidates')
            ->orderBy('question_a_source_id')
            ->get(['question_a_source_id', 'question_b_source_id', 'hash_match_level'])
            ->map(static fn (object $candidate): array => [
                (int) $candidate->question_a_source_id,
                (int) $candidate->question_b_source_id,
                $candidate->hash_match_level,
            ])
            ->all();
    }

    private function snapshot(): SourceSnapshot
    {
        return SourceSnapshot::create([
            'snapshot_taken_at' => '2026-08-07',
            'loaded_at' => now(),
            'mysql_version' => '9.1.0',
            'source_database_size_mb' => 1,
            'source_row_counts' => [],
            'profiling_results' => [],
        ]);
    }

    private function questionWithDerived(int $sourceId, string $fullHash, string $textHash, string $fuzzyHash): void
    {
        DB::connection('pgsql')->table('source_questions')->insert([
            'section_source_id' => null,
            'order' => 1,
            'raw_text' => "Question {$sourceId}",
            'correct_option_count' => 1,
            'answer_key_state' => 'single_correct',
            'options_count' => 0,
            'stem_char_length' => 10,
            'has_html' => false,
            'has_img' => false,
            'is_stem_image_only' => false,
            'requires_media_review' => false,
            'source_origin' => 'unknown',
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'source_deleted_at' => null,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', "question-{$sourceId}"),
        ]);

        DB::connection('pgsql')->table('source_question_derived')->insert([
            'question_source_id' => $sourceId,
            'clean_text' => "question {$sourceId}",
            'search_text' => "question {$sourceId}",
            'question_text_hash' => $textHash,
            'question_with_options_hash' => $fullHash,
            'fuzzy_text_hash' => $fuzzyHash,
            'normalizer_version' => 'test-v1',
        ]);
    }
}
