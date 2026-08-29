<?php

namespace App\Jobs\Dedup;

use App\Models\ImportRun;
use App\Support\Dedup\ArabicNormalizer;
use App\Support\Dedup\DuplicateHasher;
use App\Support\Dedup\OptionsNormalizer;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportErrorCode;
use App\Support\Import\ImportErrorRecorder;
use App\Support\Import\ImportRunRecorder;
use App\Support\Import\ResumeCursor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes P2's strict and recall-only text layers beside every mirrored
 * question. The mirror remains immutable; even soft-deleted questions are
 * derived so the frozen snapshot stays complete.
 */
final class DeriveQuestionTextLayers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CURSOR_KEY = 'dedup_questions';

    public function __construct(
        private readonly int $importRunId,
        private readonly ?int $chunkSize = null,
    ) {}

    public function handle(
        BatchUpsert $upsert,
        ArabicNormalizer $normalizer,
        OptionsNormalizer $optionsNormalizer,
        DuplicateHasher $hasher,
    ): void {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);
        $cursor = new ResumeCursor($run);
        $errors = new ImportErrorRecorder($run);
        $lastConfirmed = $cursor->lastConfirmed(self::CURSOR_KEY) ?? 0;
        $chunkSize = $this->chunkSize ?? (int) config('lab.dedup.chunk_size');

        DB::connection('pgsql')->table('source_questions')
            ->select(['source_id', 'raw_text'])
            ->where('source_id', '>', $lastConfirmed)
            ->orderBy('source_id')
            ->chunkById($chunkSize, function (Collection $questions) use ($upsert, $normalizer, $optionsNormalizer, $hasher, $recorder, $cursor, $errors): void {
                $questionIds = $questions->pluck('source_id')->map(static fn (mixed $id): int => (int) $id)->all();
                $optionsByQuestion = DB::connection('pgsql')->table('source_question_options')
                    ->select(['question_source_id', 'option_index', 'raw_text'])
                    ->whereIn('question_source_id', $questionIds)
                    ->orderBy('question_source_id')
                    ->orderBy('option_index')
                    ->get()
                    ->groupBy('question_source_id')
                    ->map(static fn (Collection $options): array => $options
                        ->map(static fn (object $option): array => [
                            'option_index' => (int) $option->option_index,
                            'raw_text' => (string) $option->raw_text,
                        ])
                        ->all())
                    ->all();
                $imagePathsByQuestion = DB::connection('pgsql')->table('source_media')
                    ->select(['question_source_id', 'path'])
                    ->whereIn('question_source_id', $questionIds)
                    ->where('type', 'image')
                    ->where('attach_level', 'question')
                    ->orderBy('question_source_id')
                    ->orderBy('source_id')
                    ->get()
                    ->groupBy('question_source_id')
                    ->map(static fn (Collection $media): array => $media
                        ->map(static fn (object $image): ?string => $image->path)
                        ->all())
                    ->all();

                $computedAt = now();
                $batch = [];
                foreach ($questions as $question) {
                    $cleanText = $normalizer->clean((string) $question->raw_text);
                    $searchText = $normalizer->search($cleanText);
                    $normalizedOptions = $optionsNormalizer->build($optionsByQuestion[(int) $question->source_id] ?? []);

                    $batch[] = [
                        'question_source_id' => (int) $question->source_id,
                        'clean_text' => $cleanText,
                        'search_text' => $searchText,
                        'question_text_hash' => $hasher->questionTextHash($searchText),
                        'question_with_options_hash' => $hasher->questionWithOptionsHash($searchText, $normalizedOptions),
                        'fuzzy_text_hash' => $hasher->fuzzyTextHash($searchText),
                        'fuzzy_rules_version' => ArabicNormalizer::FUZZY_VERSION,
                        'media_fingerprint' => $hasher->mediaFingerprint($imagePathsByQuestion[(int) $question->source_id] ?? []),
                        'normalizer_version' => ArabicNormalizer::VERSION,
                        'text_computed_at' => $computedAt,
                    ];

                    if ($searchText === '') {
                        $code = ImportErrorCode::EMPTY_SEARCH_TEXT;
                        $errors->record(
                            $code->value,
                            $code->severity(),
                            'source_questions',
                            (int) $question->source_id,
                            $code->description(),
                        );
                    }
                }

                $lastSourceId = (int) $questions->last()->source_id;
                DB::connection('pgsql')->transaction(function () use ($upsert, $recorder, $errors, $batch): void {
                    $recorder->recordRead(count($batch));
                    $outcome = $upsert->runDerived(
                        'source_question_derived',
                        $batch,
                        ['question_source_id'],
                        [
                            'clean_text', 'search_text', 'question_text_hash',
                            'question_with_options_hash', 'fuzzy_text_hash',
                            'fuzzy_rules_version', 'media_fingerprint',
                            'normalizer_version',
                        ],
                    );
                    $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);
                    $errors->flush();
                });

                // This is intentionally outside the transaction: a cursor is
                // confirmation of a committed batch, never a prediction.
                $cursor->confirm(self::CURSOR_KEY, $lastSourceId);
            }, 'source_id');

        $errors->flush();
    }
}
