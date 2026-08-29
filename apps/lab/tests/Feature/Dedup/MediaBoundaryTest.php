<?php

namespace Tests\Feature\Dedup;

use App\Jobs\Dedup\ClusterExactHashMatches;
use App\Jobs\Dedup\DeriveQuestionTextLayers;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediaBoundaryTest extends TestCase
{
    public function test_identical_full_hash_with_different_images_is_a_candidate_and_not_a_hash_cluster(): void
    {
        $snapshot = $this->snapshot();
        $this->question(10, 'نفس السؤال');
        $this->question(20, 'نفس السؤال');
        $this->image(10, 101, 'diagram-a.png');
        $this->image(20, 201, 'diagram-b.png');

        app()->call([new DeriveQuestionTextLayers($this->createRun($snapshot, 'p2_derive_text')->id), 'handle']);
        app()->call([new ClusterExactHashMatches($this->createRun($snapshot, 'p2_hash_cluster')->id), 'handle']);

        $candidate = DB::connection('pgsql')->table('duplicate_candidates')->sole();

        $this->assertSame(10, $candidate->question_a_source_id);
        $this->assertSame(20, $candidate->question_b_source_id);
        $this->assertSame('exact', $candidate->hash_match_level);
        $this->assertSame('different_media', $candidate->media_relation);
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->count());
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_cluster_members')->count());
    }

    public function test_questions_without_images_fingerprint_null_and_a_hash_candidate_relates_as_no_media(): void
    {
        $snapshot = $this->snapshot();
        $this->question(10, 'نفس السؤال');
        $this->question(20, 'نفس السؤال');
        $this->option(10, 101, 'الخيار الأول');
        $this->option(20, 201, 'خيار مختلف');
        $this->media(10, 301, 'video', 'lesson-a.mp4');
        $this->media(20, 302, 'video', 'lesson-b.mp4');

        app()->call([new DeriveQuestionTextLayers($this->createRun($snapshot, 'p2_derive_text')->id), 'handle']);
        app()->call([new ClusterExactHashMatches($this->createRun($snapshot, 'p2_hash_cluster')->id), 'handle']);

        $this->assertSame(2, DB::connection('pgsql')->table('source_question_derived')->whereNull('media_fingerprint')->count());
        $candidate = DB::connection('pgsql')->table('duplicate_candidates')->sole();
        $this->assertSame('formatting', $candidate->hash_match_level);
        $this->assertSame('no_media', $candidate->media_relation);
        $this->assertSame(0, DB::connection('pgsql')->table('duplicate_clusters')->count());
    }

    public function test_mixed_media_hash_group_clusters_each_same_fingerprint_subgroup_only(): void
    {
        $snapshot = $this->snapshot();
        $this->question(10, 'نفس السؤال');
        $this->question(20, 'نفس السؤال');
        $this->question(30, 'نفس السؤال');
        $this->image(10, 101, 'diagram-a.png');
        $this->image(20, 201, 'diagram-a.png');
        $this->image(30, 301, 'diagram-b.png');

        app()->call([new DeriveQuestionTextLayers($this->createRun($snapshot, 'p2_derive_text')->id), 'handle']);
        app()->call([new ClusterExactHashMatches($this->createRun($snapshot, 'p2_hash_cluster')->id), 'handle']);

        $cluster = DB::connection('pgsql')->table('duplicate_clusters')->sole();
        $memberIds = DB::connection('pgsql')->table('duplicate_cluster_members')
            ->where('duplicate_cluster_id', $cluster->id)
            ->orderBy('question_source_id')
            ->pluck('question_source_id')
            ->all();
        $candidatePairs = DB::connection('pgsql')->table('duplicate_candidates')
            ->orderBy('question_a_source_id')
            ->orderBy('question_b_source_id')
            ->get(['question_a_source_id', 'question_b_source_id', 'media_relation'])
            ->map(static fn (object $candidate): array => [
                (int) $candidate->question_a_source_id,
                (int) $candidate->question_b_source_id,
                $candidate->media_relation,
            ])
            ->all();

        $this->assertSame(10, $cluster->canonical_question_source_id);
        $this->assertSame(2, $cluster->member_count);
        $this->assertSame([10, 20], $memberIds);
        $this->assertSame([
            [10, 30, 'different_media'],
            [20, 30, 'different_media'],
        ], $candidatePairs);
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

    private function createRun(SourceSnapshot $snapshot, string $kind): ImportRun
    {
        return ImportRun::create([
            'snapshot_id' => $snapshot->id,
            'kind' => $kind,
            'started_at' => now(),
            'status' => 'running',
            'ran_via' => 'inline',
        ]);
    }

    private function question(int $sourceId, string $rawText): void
    {
        DB::connection('pgsql')->table('source_questions')->insert([
            'section_source_id' => null,
            'order' => 1,
            'raw_text' => $rawText,
            'correct_option_count' => 1,
            'answer_key_state' => 'single_correct',
            'options_count' => 0,
            'stem_char_length' => mb_strlen($rawText),
            'has_html' => false,
            'has_img' => false,
            'is_stem_image_only' => false,
            'requires_media_review' => false,
            'source_origin' => 'unknown',
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', "question-{$sourceId}"),
        ]);
    }

    private function option(int $questionSourceId, int $sourceId, string $rawText): void
    {
        DB::connection('pgsql')->table('source_question_options')->insert([
            'question_source_id' => $questionSourceId,
            'raw_text' => $rawText,
            'points' => 0,
            'source_order' => 1,
            'option_index' => 1,
            'is_correct_derived' => false,
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', "{$sourceId}:{$rawText}"),
        ]);
    }

    private function image(int $questionSourceId, int $sourceId, string $path): void
    {
        $this->media($questionSourceId, $sourceId, 'image', $path);
    }

    private function media(int $questionSourceId, int $sourceId, string $type, string $path): void
    {
        DB::connection('pgsql')->table('source_media')->insert([
            'type' => $type,
            'path' => $path,
            'question_source_id' => $questionSourceId,
            'attach_level' => 'question',
            'path_unverified' => true,
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', "{$sourceId}:{$path}"),
        ]);
    }
}
