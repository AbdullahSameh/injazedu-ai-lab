<?php

namespace Tests\Feature\Dedup;

use App\Console\Commands\LabDedup;
use App\Jobs\Dedup\ClusterExactHashMatches;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Dedup\DuplicateHasher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HashClusterTest extends TestCase
{
    public function test_hash_cluster_command_is_wired_and_reports_orthographic_candidate_count(): void
    {
        $this->snapshot();

        $exit = Artisan::call(LabDedup::class, ['--step' => 'hash-cluster']);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Orthographic candidates: 0.', Artisan::output());
        $this->assertDatabaseHas('import_runs', [
            'kind' => 'p2_hash_cluster',
            'status' => 'completed',
        ], 'pgsql');
    }

    public function test_literal_full_hash_group_creates_one_exact_cluster_with_its_lowest_source_id_as_canonical(): void
    {
        $run = $this->createRun();
        $this->questionWithDerived(30, 'full-a', 'stem-a');
        $this->questionWithDerived(10, 'full-a', 'stem-a');
        $this->questionWithDerived(20, 'full-a', 'stem-a');

        $this->callJob(new ClusterExactHashMatches($run->id));

        $cluster = DB::connection('pgsql')->table('duplicate_clusters')->sole();
        $members = DB::connection('pgsql')->table('duplicate_cluster_members')
            ->orderBy('question_source_id')
            ->get(['question_source_id', 'is_canonical']);

        $this->assertSame('exact_duplicate', $cluster->relation_type);
        $this->assertSame('auto', $cluster->status);
        $this->assertSame('hash', $cluster->source_layer);
        $this->assertSame(10, $cluster->canonical_question_source_id);
        $this->assertSame(3, $cluster->member_count);
        $this->assertSame([10, 20, 30], $members->pluck('question_source_id')->all());
        $this->assertSame([true, false, false], $members->map(static fn (object $member): bool => (bool) $member->is_canonical)->all());
    }

    public function test_exact_group_canonical_minimum_is_derived_by_sql(): void
    {
        $queries = [];
        DB::connection('pgsql')->listen(static function (object $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $run = $this->createRun();
        $this->questionWithDerived(20, 'full-a', 'stem-a');
        $this->questionWithDerived(10, 'full-a', 'stem-a');

        $this->callJob(new ClusterExactHashMatches($run->id));

        $this->assertTrue(collect($queries)->contains(static fn (string $sql): bool => str_contains(
            $sql,
            'MIN(derived.question_source_id) OVER (PARTITION BY derived.question_with_options_hash) AS group_canonical_id',
        )));
        $this->assertSame(10, DB::connection('pgsql')->table('duplicate_clusters')->value('canonical_question_source_id'));
    }

    public function test_rerunning_the_hash_job_keeps_cluster_membership_and_canonical_selection_stable(): void
    {
        $firstRun = $this->createRun();
        $this->questionWithDerived(30, 'full-a', 'stem-a');
        $this->questionWithDerived(10, 'full-a', 'stem-a');
        $this->questionWithDerived(20, 'full-a', 'stem-a');

        $this->callJob(new ClusterExactHashMatches($firstRun->id));
        $before = DB::connection('pgsql')->table('duplicate_clusters')->sole();
        $beforeMembers = DB::connection('pgsql')->table('duplicate_cluster_members')
            ->orderBy('question_source_id')
            ->get(['question_source_id', 'is_canonical'])
            ->map(static fn (object $member): array => [(int) $member->question_source_id, (bool) $member->is_canonical])
            ->all();

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $after = DB::connection('pgsql')->table('duplicate_clusters')->sole();
        $afterMembers = DB::connection('pgsql')->table('duplicate_cluster_members')
            ->orderBy('question_source_id')
            ->get(['question_source_id', 'is_canonical'])
            ->map(static fn (object $member): array => [(int) $member->question_source_id, (bool) $member->is_canonical])
            ->all();

        $this->assertSame($before->id, $after->id);
        $this->assertSame(10, $after->canonical_question_source_id);
        $this->assertSame($beforeMembers, $afterMembers);
        $this->assertSame(1, DB::connection('pgsql')->table('duplicate_clusters')->count());
        $this->assertSame(3, DB::connection('pgsql')->table('duplicate_cluster_members')->count());
    }

    public function test_reconciliation_removes_a_member_that_moves_to_another_exact_hash_group(): void
    {
        $firstRun = $this->createRun();
        $this->questionWithDerived(10, 'full-a', 'stem-a');
        $this->questionWithDerived(20, 'full-a', 'stem-a');
        $this->questionWithDerived(30, 'full-b', 'stem-b');
        $this->questionWithDerived(40, 'full-b', 'stem-b');

        $this->callJob(new ClusterExactHashMatches($firstRun->id));
        DB::connection('pgsql')->table('source_question_derived')
            ->where('question_source_id', 20)
            ->update(['question_with_options_hash' => 'full-b']);

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $clusters = DB::connection('pgsql')->table('duplicate_clusters')
            ->where('source_layer', 'hash')
            ->get(['id', 'canonical_question_source_id', 'member_count']);
        $members = DB::connection('pgsql')->table('duplicate_cluster_members')
            ->orderBy('question_source_id')
            ->get(['question_source_id', 'duplicate_cluster_id', 'is_canonical']);

        $this->assertCount(1, $clusters);
        $this->assertSame(20, $clusters->sole()->canonical_question_source_id);
        $this->assertSame(3, $clusters->sole()->member_count);
        $this->assertSame([20, 30, 40], $members->pluck('question_source_id')->all());
        $this->assertSame([true, false, false], $members->map(static fn (object $member): bool => (bool) $member->is_canonical)->all());
        $this->assertSame(3, $members->pluck('question_source_id')->unique()->count());
    }

    public function test_unchanged_reviewed_and_triaged_hash_cluster_keeps_human_state_and_deterministic_projection(): void
    {
        $firstRun = $this->createRun();
        $this->questionWithDerived(10, 'full-a', 'stem-a');
        $this->questionWithDerived(20, 'full-a', 'stem-a');
        $this->callJob(new ClusterExactHashMatches($firstRun->id));

        $clusterId = DB::connection('pgsql')->table('duplicate_clusters')->value('id');
        DB::connection('pgsql')->table('duplicate_clusters')->where('id', $clusterId)->update([
            'status' => 'confirmed',
            'ai_triage_recommendation' => 'Keep trainer decision',
            'ai_triage_rationale' => 'Already triaged',
            'ai_triage_confidence' => 0.91,
            'ai_triage_prompt_version' => 'triage-v1',
            'ai_triage_at' => now(),
        ]);

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $cluster = DB::connection('pgsql')->table('duplicate_clusters')->find($clusterId);

        $this->assertSame('confirmed', $cluster->status);
        $this->assertSame('Keep trainer decision', $cluster->ai_triage_recommendation);
        $this->assertSame('Already triaged', $cluster->ai_triage_rationale);
        $this->assertSame(0.91, $cluster->ai_triage_confidence);
        $this->assertSame('triage-v1', $cluster->ai_triage_prompt_version);
        $this->assertNotNull($cluster->ai_triage_at);
        $this->assertSame(10, $cluster->canonical_question_source_id);
        $this->assertSame(2, $cluster->member_count);
    }

    public function test_reconciliation_refuses_to_reinterpret_a_reviewed_hash_cluster_after_its_membership_changes(): void
    {
        $firstRun = $this->createRun();
        $this->questionWithDerived(10, 'full-a', 'stem-a');
        $this->questionWithDerived(20, 'full-a', 'stem-a');
        $this->callJob(new ClusterExactHashMatches($firstRun->id));

        DB::connection('pgsql')->table('duplicate_clusters')->update(['status' => 'confirmed']);
        DB::connection('pgsql')->table('source_question_derived')
            ->where('question_source_id', 20)
            ->update(['question_with_options_hash' => 'full-b']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot reconcile protected hash cluster');

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));
    }

    public function test_human_modified_relation_and_review_remain_unchanged_on_rerun_and_protected_drift_refusal(): void
    {
        $firstRun = $this->createRun();
        $this->questionWithDerived(10, 'full-a', 'stem-a');
        $this->questionWithDerived(20, 'full-a', 'stem-a');
        $this->callJob(new ClusterExactHashMatches($firstRun->id));

        $clusterId = DB::connection('pgsql')->table('duplicate_clusters')->value('id');
        $reviewerId = DB::connection('pgsql')->table('users')->insertGetId([
            'name' => 'Lab reviewer',
            'email' => 'reviewer@example.test',
            'password' => 'not-used-by-this-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('pgsql')->table('duplicate_clusters')->where('id', $clusterId)->update([
            'status' => 'confirmed',
            'relation_type' => 'semantic_duplicate',
        ]);
        $reviewId = DB::connection('pgsql')->table('duplicate_reviews')->insertGetId([
            'duplicate_cluster_id' => $clusterId,
            'decision' => 'same',
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
            'previous_status' => 'auto',
            'new_status' => 'confirmed',
            'previous_relation_type' => 'exact_duplicate',
            'new_relation_type' => 'semantic_duplicate',
            'notes' => 'Human relation is authoritative.',
        ]);
        $reviewBefore = DB::connection('pgsql')->table('duplicate_reviews')->find($reviewId);

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $unchangedCluster = DB::connection('pgsql')->table('duplicate_clusters')->find($clusterId);
        $this->assertSame(1, DB::connection('pgsql')->table('duplicate_clusters')->where('source_layer', 'hash')->count());
        $this->assertSame('semantic_duplicate', $unchangedCluster->relation_type);
        $this->assertSame('confirmed', $unchangedCluster->status);
        $this->assertEquals($reviewBefore, DB::connection('pgsql')->table('duplicate_reviews')->find($reviewId));

        DB::connection('pgsql')->table('source_question_derived')
            ->where('question_source_id', 20)
            ->update(['question_with_options_hash' => 'full-b']);

        try {
            $this->callJob(new ClusterExactHashMatches($this->createRun()->id));
            $this->fail('Expected protected membership drift to fail before it mutates the human decision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot reconcile protected hash cluster', $exception->getMessage());
        }

        $refusedCluster = DB::connection('pgsql')->table('duplicate_clusters')->find($clusterId);
        $this->assertSame('semantic_duplicate', $refusedCluster->relation_type);
        $this->assertSame('confirmed', $refusedCluster->status);
        $this->assertEquals($reviewBefore, DB::connection('pgsql')->table('duplicate_reviews')->find($reviewId));
    }

    public function test_stem_only_hash_match_creates_a_high_formatting_candidate_without_a_cluster(): void
    {
        $run = $this->createRun();
        $hasher = app(DuplicateHasher::class);
        $this->questionWithDerived(
            10,
            'full-a',
            'shared-stem',
            sectionSourceId: 7,
            mediaFingerprint: $hasher->mediaFingerprint(['diagram-a.png']),
        );
        $this->questionWithDerived(
            20,
            'full-b',
            'shared-stem',
            sectionSourceId: 7,
            mediaFingerprint: $hasher->mediaFingerprint(['diagram-b.png']),
        );
        $this->image(10, 101, 'diagram-a.png');
        $this->image(20, 102, 'diagram-b.png');

        $this->callJob(new ClusterExactHashMatches($run->id));

        $candidate = DB::connection('pgsql')->table('duplicate_candidates')->sole();

        $this->assertSame(10, $candidate->question_a_source_id);
        $this->assertSame(20, $candidate->question_b_source_id);
        $this->assertSame('formatting', $candidate->hash_match_level);
        $this->assertSame('high', $candidate->band);
        $this->assertTrue((bool) $candidate->same_section);
        $this->assertSame('different_media', $candidate->media_relation);
        $this->assertNotNull($candidate->generated_at);
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->count());
    }

    public function test_fuzzy_only_match_creates_an_orthographic_candidate_without_an_automatic_cluster_or_service_call(): void
    {
        config(['lab.dedup.fuzzy_fold_enabled' => true]);
        Http::preventStrayRequests();
        $run = $this->createRun();
        $this->questionWithDerived(10, 'full-a', 'strict-a', fuzzyHash: 'shared-fuzzy');
        $this->questionWithDerived(20, 'full-b', 'strict-b', fuzzyHash: 'shared-fuzzy');

        $this->callJob(new ClusterExactHashMatches($run->id));

        $candidate = DB::connection('pgsql')->table('duplicate_candidates')->sole();

        $this->assertSame('orthographic', $candidate->hash_match_level);
        $this->assertSame('high', $candidate->band);
        $this->assertFalse((bool) $candidate->same_section);
        $this->assertSame('no_media', $candidate->media_relation);
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->count());
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->where('relation_type', 'exact_duplicate')->count());
        $this->assertSame(1, $run->refresh()->rows_inserted);
    }

    public function test_fuzzy_only_branch_is_skipped_when_folding_is_disabled(): void
    {
        config(['lab.dedup.fuzzy_fold_enabled' => false]);
        $run = $this->createRun();
        $this->questionWithDerived(10, 'full-a', 'strict-a', fuzzyHash: 'shared-fuzzy');
        $this->questionWithDerived(20, 'full-b', 'strict-b', fuzzyHash: 'shared-fuzzy');

        $this->callJob(new ClusterExactHashMatches($run->id));

        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_candidates')->count());
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->count());
    }

    public function test_deleted_questions_and_empty_search_text_are_excluded_from_hash_groups_and_candidates(): void
    {
        $run = $this->createRun();
        $this->questionWithDerived(10, 'full-a', 'shared-stem');
        $this->questionWithDerived(20, 'full-a', 'shared-stem', deletedAt: now());
        $this->questionWithDerived(30, 'full-b', 'shared-stem', searchText: '');

        $this->callJob(new ClusterExactHashMatches($run->id));

        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->count());
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_candidates')->count());
    }

    public function test_questions_requiring_media_review_are_excluded_from_every_hash_text_path(): void
    {
        config(['lab.dedup.fuzzy_fold_enabled' => true]);
        $run = $this->createRun();

        $this->questionWithDerived(10, 'exact-full', 'exact-stem', 'exact-fuzzy', requiresMediaReview: true);
        $this->questionWithDerived(20, 'exact-full', 'exact-stem', 'exact-fuzzy');
        $this->questionWithDerived(30, 'format-full-a', 'format-stem', 'format-fuzzy-a', requiresMediaReview: true);
        $this->questionWithDerived(40, 'format-full-b', 'format-stem', 'format-fuzzy-b');
        $this->questionWithDerived(50, 'orth-full-a', 'orth-stem-a', 'orth-fuzzy', requiresMediaReview: true);
        $this->questionWithDerived(60, 'orth-full-b', 'orth-stem-b', 'orth-fuzzy');

        $this->callJob(new ClusterExactHashMatches($run->id));

        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->count());
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_candidates')->count());
    }

    public function test_reconciliation_removes_a_hash_candidate_that_becomes_ineligible_for_text_matching(): void
    {
        config(['lab.dedup.fuzzy_fold_enabled' => true]);
        $this->questionWithDerived(10, 'full-a', 'shared-stem', 'fuzzy-a');
        $this->questionWithDerived(20, 'full-b', 'shared-stem', 'fuzzy-b');
        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));
        $this->assertSame(1, DB::connection('pgsql')->table('duplicate_candidates')->count());

        DB::connection('pgsql')->table('source_questions')
            ->where('source_id', 10)
            ->update(['requires_media_review' => true]);

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_candidates')->count());
    }

    public function test_reconciliation_removes_a_formatting_candidate_when_its_hash_relationship_disappears(): void
    {
        config(['lab.dedup.fuzzy_fold_enabled' => true]);
        $this->questionWithDerived(10, 'full-a', 'shared-stem', 'fuzzy-a');
        $this->questionWithDerived(20, 'full-b', 'shared-stem', 'fuzzy-b');
        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));
        $this->assertSame(1, DB::connection('pgsql')->table('duplicate_candidates')->count());

        DB::connection('pgsql')->table('source_question_derived')
            ->where('question_source_id', 20)
            ->update(['question_text_hash' => 'different-stem']);

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_candidates')->count());
    }

    public function test_reconciliation_reclassifies_a_formatting_candidate_as_orthographic(): void
    {
        config(['lab.dedup.fuzzy_fold_enabled' => true]);
        $this->questionWithDerived(10, 'full-a', 'shared-stem', 'shared-fuzzy');
        $this->questionWithDerived(20, 'full-b', 'shared-stem', 'shared-fuzzy');
        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));
        $this->assertSame('formatting', DB::connection('pgsql')->table('duplicate_candidates')->value('hash_match_level'));

        DB::connection('pgsql')->table('source_question_derived')
            ->where('question_source_id', 20)
            ->update(['question_text_hash' => 'different-stem']);

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $this->assertSame(1, DB::connection('pgsql')->table('duplicate_candidates')->count());
        $this->assertSame('orthographic', DB::connection('pgsql')->table('duplicate_candidates')->value('hash_match_level'));
    }

    public function test_reconciliation_preserves_downstream_candidate_evidence_when_hash_support_disappears(): void
    {
        $this->questionWithDerived(10, 'full-a', 'shared-stem');
        $this->questionWithDerived(20, 'full-b', 'shared-stem');
        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));
        DB::connection('pgsql')->table('duplicate_candidates')->update([
            'trgm_score' => 0.91,
            'llm_verdict_relation' => 'formatting_duplicate',
        ]);
        DB::connection('pgsql')->table('source_question_derived')
            ->where('question_source_id', 20)
            ->update(['question_text_hash' => 'different-stem']);

        $this->callJob(new ClusterExactHashMatches($this->createRun()->id));

        $candidate = DB::connection('pgsql')->table('duplicate_candidates')->sole();
        $this->assertNull($candidate->hash_match_level);
        $this->assertSame(0.91, $candidate->trgm_score);
        $this->assertSame('formatting_duplicate', $candidate->llm_verdict_relation);
    }

    private function callJob(object $job): void
    {
        app()->call([$job, 'handle']);
    }

    private function createRun(): ImportRun
    {
        return ImportRun::create([
            'snapshot_id' => $this->snapshot()->id,
            'kind' => 'p2_hash_cluster',
            'started_at' => now(),
            'status' => 'running',
            'ran_via' => 'inline',
        ]);
    }

    private function snapshot(): SourceSnapshot
    {
        return SourceSnapshot::firstOrCreate([
            'snapshot_taken_at' => '2026-08-07',
        ], [
            'loaded_at' => now(),
            'mysql_version' => '9.1.0',
            'source_database_size_mb' => 1,
            'source_row_counts' => [],
            'profiling_results' => [],
        ]);
    }

    private function questionWithDerived(
        int $sourceId,
        string $fullHash,
        string $textHash,
        ?string $fuzzyHash = null,
        ?int $sectionSourceId = null,
        mixed $deletedAt = null,
        string $searchText = 'question text',
        bool $requiresMediaReview = false,
        ?string $mediaFingerprint = null,
    ): void {
        DB::connection('pgsql')->table('source_questions')->insert([
            'section_source_id' => $sectionSourceId,
            'order' => 1,
            'raw_text' => "Question {$sourceId}",
            'correct_option_count' => 1,
            'answer_key_state' => 'single_correct',
            'options_count' => 0,
            'stem_char_length' => 10,
            'has_html' => false,
            'has_img' => false,
            'is_stem_image_only' => false,
            'requires_media_review' => $requiresMediaReview,
            'source_origin' => 'unknown',
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'source_deleted_at' => $deletedAt,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', "question-{$sourceId}"),
        ]);

        DB::connection('pgsql')->table('source_question_derived')->insert([
            'question_source_id' => $sourceId,
            'clean_text' => $searchText,
            'search_text' => $searchText,
            'question_text_hash' => $textHash,
            'question_with_options_hash' => $fullHash,
            'fuzzy_text_hash' => $fuzzyHash,
            'media_fingerprint' => $mediaFingerprint,
            'normalizer_version' => 'test-v1',
        ]);
    }

    private function image(int $questionSourceId, int $sourceId, string $path): void
    {
        DB::connection('pgsql')->table('source_media')->insert([
            'type' => 'image',
            'path' => $path,
            'question_source_id' => $questionSourceId,
            'attach_level' => 'question',
            'path_unverified' => true,
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', $path),
        ]);
    }
}
