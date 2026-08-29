<?php

namespace Tests\Feature\Dedup;

use App\Console\Commands\LabDedup;
use App\Jobs\Dedup\DeriveQuestionTextLayers;
use App\Jobs\Dedup\DeriveSectionTextLayers;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Dedup\ArabicNormalizer;
use App\Support\Dedup\DuplicateHasher;
use App\Support\Dedup\OptionsNormalizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeriveTextLayersTest extends TestCase
{
    public function test_question_derivation_includes_soft_deleted_rows_and_uses_existing_option_indexes(): void
    {
        $run = $this->createRun();

        $this->question(10, 'السؤال الأول');
        $this->question(20, 'السؤال المحذوف', now());
        $this->question(30, 'السؤال الثالث');
        $this->option(10, 101, 2, 'B. الخيار الثاني');
        $this->option(10, 102, 1, 'A. الخيار الأول');

        $this->callJob(new DeriveQuestionTextLayers($run->id, 2));

        $derived = DB::connection('pgsql')->table('source_question_derived')
            ->where('question_source_id', 10)
            ->first();

        $this->assertSame(3, DB::connection('pgsql')->table('source_question_derived')->count());
        $this->assertSame('السؤال المحذوف', DB::connection('pgsql')->table('source_questions')->where('source_id', 20)->value('raw_text'));
        $this->assertSame(ArabicNormalizer::VERSION, $derived->normalizer_version);
        $this->assertSame(ArabicNormalizer::FUZZY_VERSION, $derived->fuzzy_rules_version);
        $this->assertNotSame('', $derived->fuzzy_text_hash);

        $normalizer = app(ArabicNormalizer::class);
        $hasher = app(DuplicateHasher::class);
        $options = app(OptionsNormalizer::class)->build([
            ['option_index' => 2, 'raw_text' => 'B. الخيار الثاني'],
            ['option_index' => 1, 'raw_text' => 'A. الخيار الأول'],
        ]);
        $search = $normalizer->search($normalizer->clean('السؤال الأول'));

        $this->assertSame($hasher->questionTextHash($search), $derived->question_text_hash);
        $this->assertSame($hasher->questionWithOptionsHash($search, $options), $derived->question_with_options_hash);
        $this->assertSame($hasher->fuzzyTextHash($search), $derived->fuzzy_text_hash);
        $this->assertSame(3, $run->refresh()->rows_read);
        $this->assertSame(30, $run->resume_cursor['dedup_questions']);
    }

    public function test_question_derivation_resumes_after_the_last_confirmed_source_id(): void
    {
        $run = $this->createRun(['dedup_questions' => 20]);

        $this->question(10, 'لا يعاد');
        $this->question(20, 'لا يعاد أيضا');
        $this->question(30, 'يشتق بعد الاستئناف');

        $this->callJob(new DeriveQuestionTextLayers($run->id, 1));

        $this->assertSame([30], DB::connection('pgsql')->table('source_question_derived')->pluck('question_source_id')->all());
        $this->assertSame(1, $run->refresh()->rows_read);
        $this->assertSame(30, $run->resume_cursor['dedup_questions']);
    }

    public function test_empty_search_text_is_stored_and_recorded_as_an_anomaly(): void
    {
        $run = $this->createRun();
        $this->question(10, '<img src="only-an-image.png">');

        $this->callJob(new DeriveQuestionTextLayers($run->id));

        $this->assertSame('', DB::connection('pgsql')->table('source_question_derived')->where('question_source_id', 10)->value('search_text'));
        $this->assertDatabaseHas('import_errors', [
            'import_run_id' => $run->id,
            'source_table' => 'source_questions',
            'source_id' => 10,
            'code' => 'EMPTY_SEARCH_TEXT',
        ], 'pgsql');
    }

    public function test_section_derivation_measures_zero_and_nonzero_stimulus_rows(): void
    {
        $run = $this->createRun();
        $this->section(10, null, false);
        $this->section(20, 'نص مشترك', true);

        $this->callJob(new DeriveSectionTextLayers($run->id, 1));

        $this->assertSame([20], DB::connection('pgsql')->table('source_section_derived')->pluck('section_source_id')->all());
        $this->assertSame(1, $run->refresh()->rows_read);
        $this->assertSame(20, $run->resume_cursor['dedup_sections']);
    }

    public function test_derive_text_command_records_its_p2_run_and_reports_processed_counts(): void
    {
        $this->snapshot();
        $this->question(10, 'سؤال الأمر');

        $exit = Artisan::call(LabDedup::class, ['--step' => 'derive-text', '--chunk' => 1]);
        $run = ImportRun::where('kind', 'p2_derive_text')->latest('id')->firstOrFail();

        $this->assertSame(0, $exit);
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->rows_read);
        $this->assertStringContainsString('Derived text: 1 question(s), 0 section(s).', Artisan::output());
        $this->assertDatabaseHas('source_question_derived', ['question_source_id' => 10], 'pgsql');
    }

    private function callJob(object $job): void
    {
        app()->call([$job, 'handle']);
    }

    /** @param array<string, int> $cursor */
    private function createRun(array $cursor = []): ImportRun
    {
        $snapshot = $this->snapshot();

        return ImportRun::create([
            'snapshot_id' => $snapshot->id,
            'kind' => 'p2_derive_text',
            'started_at' => now(),
            'status' => 'running',
            'ran_via' => 'inline',
            'resume_cursor' => $cursor,
        ]);
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

    private function question(int $sourceId, string $rawText, mixed $deletedAt = null): void
    {
        DB::connection('pgsql')->table('source_questions')->insert([
            'section_source_id' => null,
            'order' => 1,
            'raw_text' => $rawText,
            'correct_option_count' => 1,
            'answer_key_state' => 'single_correct',
            'options_count' => 0,
            'stem_char_length' => mb_strlen($rawText),
            'has_html' => str_contains($rawText, '<'),
            'has_img' => str_contains($rawText, '<img'),
            'is_stem_image_only' => false,
            'requires_media_review' => false,
            'source_origin' => 'unknown',
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'source_deleted_at' => $deletedAt,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', $rawText),
        ]);
    }

    private function option(int $questionSourceId, int $sourceId, int $optionIndex, string $rawText): void
    {
        DB::connection('pgsql')->table('source_question_options')->insert([
            'question_source_id' => $questionSourceId,
            'raw_text' => $rawText,
            'points' => 0,
            'source_order' => $optionIndex,
            'option_index' => $optionIndex,
            'is_correct_derived' => false,
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', $rawText),
        ]);
    }

    private function section(int $sourceId, ?string $stimulus, bool $hasStimulus): void
    {
        DB::connection('pgsql')->table('source_sections')->insert([
            'quiz_source_id' => null,
            'order' => 1,
            'stimulus_raw' => $stimulus,
            'stimulus_length' => mb_strlen((string) $stimulus),
            'has_stimulus' => $hasStimulus,
            'is_long_stimulus' => false,
            'questions_count' => 0,
            'source_system' => 'injazedu_production',
            'source_id' => $sourceId,
            'imported_at' => now(),
            'import_run_id' => 1,
            'payload_hash' => hash('sha256', (string) $stimulus),
        ]);
    }
}
