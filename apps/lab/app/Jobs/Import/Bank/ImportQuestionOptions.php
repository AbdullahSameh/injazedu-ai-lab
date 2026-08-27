<?php

namespace App\Jobs\Import\Bank;

use App\Models\ImportRun;
use App\Support\Derive\PayloadHasher;
use App\Support\Import\Bank\QuestionOptionsFetcher;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportRunRecorder;
use App\Support\Import\ResumeCursor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * `options` → `source_question_options` (FR-017, data-model.md §2). Reads
 * the same `QuestionOptionsFetcher` grouping `ImportQuestions` used, so the
 * `option_index` stored here is guaranteed identical to the one already
 * folded into `source_questions.payload_hash` — one derivation, read twice,
 * never two independent ones that could drift.
 *
 * A/B/C/D letters do not exist in the source and are never stored here;
 * they are synthesized from `option_index` at render time only.
 */
final class ImportQuestionOptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId) {}

    public function handle(BatchUpsert $upsert, QuestionOptionsFetcher $optionsFetcher): void
    {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);
        $cursor = new ResumeCursor($run);

        $lastId = $cursor->lastConfirmed('options') ?? 0;
        $optionsByQuestion = $optionsFetcher->grouped();
        $hasher = new PayloadHasher;

        $maxId = $lastId;
        $batch = [];

        foreach ($optionsByQuestion as $questionId => $options) {
            foreach ($options as $option) {
                if ($option['id'] <= $lastId) {
                    continue;
                }

                $content = [
                    'raw_text' => $option['name'],
                    'points' => $option['points'],
                    'source_order' => $option['order'],
                ];

                $batch[] = $content + [
                    'question_source_id' => $questionId,
                    'option_index' => $option['option_index'],
                    'is_correct_derived' => $option['points'] > 0,
                    'source_system' => config('lab.import.source_system'),
                    'source_id' => $option['id'],
                    'source_created_at' => $option['created_at'],
                    'source_updated_at' => $option['updated_at'],
                    'source_deleted_at' => $option['deleted_at'],
                    'imported_at' => now(),
                    'import_run_id' => $run->id,
                    'payload_hash' => $hasher->hash($content),
                ];

                $maxId = max($maxId, (int) $option['id']);

                if (count($batch) >= BatchUpsert::BATCH_SIZE) {
                    $this->flush($upsert, $recorder, $batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            $this->flush($upsert, $recorder, $batch);
        }

        if ($maxId > $lastId) {
            // Confirmed once, at the end — never per flush. These rows are
            // grouped by question, so option ids are NOT globally ascending
            // across the pass and a mid-pass cursor would skip options
            // belonging to a later question. A crash therefore replays the
            // whole table, which the upsert makes free.
            $cursor->confirm('options', $maxId);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function flush(BatchUpsert $upsert, ImportRunRecorder $recorder, array $batch): void
    {
        $recorder->recordRead(count($batch));

        $outcome = $upsert->run('options', 'source_question_options', $batch);
        $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);
    }
}
