<?php

namespace Tests\Feature\Dedup;

use App\Jobs\Dedup\DeriveQuestionTextLayers;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediaFingerprintOrderTest extends TestCase
{
    public function test_two_image_fingerprint_uses_source_id_order_and_changes_with_the_image_set(): void
    {
        $run = $this->createRun();

        $this->question(10);
        $this->question(20);
        $this->question(30);

        $this->image(10, 101, 'diagram-a.png');
        $this->image(10, 102, 'diagram-b.png');

        // Inserted in the opposite database order, but source_id still
        // defines the same ordered path list.
        $this->image(20, 202, 'diagram-b.png');
        $this->image(20, 201, 'diagram-a.png');

        $this->image(30, 301, 'diagram-a.png');
        $this->image(30, 302, 'diagram-c.png');

        app()->call([new DeriveQuestionTextLayers($run->id), 'handle']);

        $fingerprints = DB::connection('pgsql')->table('source_question_derived')
            ->orderBy('question_source_id')
            ->pluck('media_fingerprint', 'question_source_id');
        $expected = 'd417d0a8784bf189870e996e027d597025aa6d271632ece1f8cdcd52b5158003';

        $this->assertSame($expected, $fingerprints[10]);
        $this->assertSame($fingerprints[10], $fingerprints[20]);
        $this->assertNotSame($fingerprints[10], $fingerprints[30]);
    }

    private function createRun(): ImportRun
    {
        $snapshot = SourceSnapshot::create([
            'snapshot_taken_at' => '2026-08-07',
            'loaded_at' => now(),
            'mysql_version' => '9.1.0',
            'source_database_size_mb' => 1,
            'source_row_counts' => [],
            'profiling_results' => [],
        ]);

        return ImportRun::create([
            'snapshot_id' => $snapshot->id,
            'kind' => 'p2_derive_text',
            'started_at' => now(),
            'status' => 'running',
            'ran_via' => 'inline',
        ]);
    }

    private function question(int $sourceId): void
    {
        DB::connection('pgsql')->table('source_questions')->insert([
            'section_source_id' => null,
            'order' => 1,
            'raw_text' => "Question {$sourceId}",
            'correct_option_count' => 1,
            'answer_key_state' => 'single_correct',
            'options_count' => 0,
            'stem_char_length' => 11,
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
            'payload_hash' => hash('sha256', "{$sourceId}:{$path}"),
        ]);
    }
}
