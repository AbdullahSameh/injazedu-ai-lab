<?php

namespace App\Jobs\Import\Bank;

use App\Models\ImportRun;
use App\Support\Derive\AnswerKeyDeriver;
use App\Support\Derive\PayloadHasher;
use App\Support\Import\Bank\QuestionOptionsFetcher;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportErrorRecorder;
use App\Support\Import\ImportRunRecorder;
use App\Support\Import\ResumeCursor;
use App\Support\Import\Validators\QuestionUnderImport;
use App\Support\Import\Validators\ValidationSuite;
use App\Support\SourceReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * `questions` → `source_questions` — the central table (FR-034, FR-061,
 * data-model.md §2). Runs *before* `ImportQuestionOptions` in the mandatory
 * bank order, so its option-derived stats read straight from MySQL's
 * `options` table via `QuestionOptionsFetcher` — not from the mirror,
 * which does not have this data yet.
 *
 * `raw_text` (`questions.name`) is copied unmodified. `answer_key_state`
 * always comes out `pending` (`AnswerKeyDeriver`, T031) — the multi-key
 * policy is the backfill pass's job (T062), not this one's.
 *
 * **Ten of the thirteen checks run here** (FR-042), because this is the one
 * pass that holds a question together with its full option set. That
 * includes `DUPLICATE_OPTION_TEXT` and `OPTION_ORDER_TIE`, which are defects
 * in the options but properties of the set, and are filed against the
 * question — the row a reviewer would actually open.
 *
 * Every check runs *after* the row is in the batch. A finding never keeps a
 * row out of the mirror, never repairs it and never normalises it (FR-046):
 * the anomaly is recorded beside a faithful copy.
 */
final class ImportQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId) {}

    public function handle(SourceReader $source, BatchUpsert $upsert, QuestionOptionsFetcher $optionsFetcher): void
    {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);
        $cursor = new ResumeCursor($run);

        $errors = new ImportErrorRecorder($run);
        $lastId = $cursor->lastConfirmed('questions') ?? 0;
        $optionsByQuestion = $optionsFetcher->grouped();

        // Read once, from the source: `ORPHAN_SECTION` needs to know which
        // sections exist, and asking per question would be 29,142 queries to
        // settle what 3,316 ids answer.
        $checks = ValidationSuite::forQuestions(
            array_fill_keys($source->table('sections')->pluck('id')->all(), true)
        );

        $rows = $source->table('questions')
            ->select(['id', 'section_id', 'order', 'name', 'description', 'hint', 'created_at', 'updated_at', 'deleted_at'])
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->cursor();

        $answerKeyDeriver = new AnswerKeyDeriver;
        $hasher = new PayloadHasher;
        $maxId = $lastId;
        $batch = [];

        foreach ($rows as $row) {
            $options = $optionsByQuestion[$row->id] ?? [];
            $liveOptionCount = count(array_filter(
                $options,
                static fn (array $option): bool => empty($option['deleted_at'])
            ));

            $answerKey = $answerKeyDeriver->derive($options);

            $rawText = $row->name;
            $strippedText = trim(strip_tags($rawText));

            $batch[] = [
                'section_source_id' => $row->section_id,
                'order' => $row->order,
                'raw_text' => $rawText,
                'explanation_raw' => $row->description,
                'hint_raw' => $row->hint,
                'correct_option_count' => $answerKey['correct_option_count'],
                'answer_key_state' => $answerKey['answer_key_state'],
                'options_count' => $liveOptionCount,
                'stem_char_length' => mb_strlen($rawText),
                'has_html' => str_contains($rawText, '<'),
                'has_img' => str_contains($rawText, '<img'),
                'is_stem_image_only' => $strippedText === '',
                'source_system' => config('lab.import.source_system'),
                'source_id' => $row->id,
                'source_created_at' => $row->created_at,
                'source_updated_at' => $row->updated_at,
                'source_deleted_at' => $row->deleted_at,
                'imported_at' => now(),
                'import_run_id' => $run->id,
                'payload_hash' => $hasher->hashQuestion($rawText, $row->description, $row->hint, $options),
            ];

            $maxId = max($maxId, (int) $row->id);

            $question = new QuestionUnderImport(
                sourceId: (int) $row->id,
                sectionSourceId: $row->section_id === null ? null : (int) $row->section_id,
                rawText: $rawText,
                options: $options,
                isSoftDeleted: $row->deleted_at !== null,
            );

            foreach ($checks as $check) {
                if ($finding = $check->check($question)) {
                    $errors->recordFinding($finding);
                }
            }

            if (count($batch) >= BatchUpsert::BATCH_SIZE) {
                $this->flush($upsert, $recorder, $batch);
                // Findings commit before the cursor moves past the rows they
                // describe — a resumed run never reads those rows again.
                $errors->flush();
                $cursor->confirm('questions', $maxId);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->flush($upsert, $recorder, $batch);
            $errors->flush();
            $cursor->confirm('questions', $maxId);
        }

        $errors->flush();
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function flush(BatchUpsert $upsert, ImportRunRecorder $recorder, array $batch): void
    {
        $recorder->recordRead(count($batch));

        $outcome = $upsert->run('questions', 'source_questions', $batch);
        $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);
    }
}
