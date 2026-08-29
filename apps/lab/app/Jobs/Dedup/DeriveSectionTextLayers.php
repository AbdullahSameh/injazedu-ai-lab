<?php

namespace App\Jobs\Dedup;

use App\Models\ImportRun;
use App\Support\Dedup\ArabicNormalizer;
use App\Support\Dedup\DuplicateHasher;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportRunRecorder;
use App\Support\Import\ResumeCursor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Derives the section passage layer only where the mirrored section says it has stimulus text. */
final class DeriveSectionTextLayers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CURSOR_KEY = 'dedup_sections';

    public function __construct(
        private readonly int $importRunId,
        private readonly ?int $chunkSize = null,
    ) {}

    public function handle(BatchUpsert $upsert, ArabicNormalizer $normalizer, DuplicateHasher $hasher): void
    {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);
        $cursor = new ResumeCursor($run);
        $lastConfirmed = $cursor->lastConfirmed(self::CURSOR_KEY) ?? 0;
        $chunkSize = $this->chunkSize ?? (int) config('lab.dedup.chunk_size');

        DB::connection('pgsql')->table('source_sections')
            ->select(['source_id', 'stimulus_raw'])
            ->where('has_stimulus', true)
            ->where('source_id', '>', $lastConfirmed)
            ->orderBy('source_id')
            ->chunkById($chunkSize, function (Collection $sections) use ($upsert, $normalizer, $hasher, $recorder, $cursor): void {
                $computedAt = now();
                $batch = [];
                foreach ($sections as $section) {
                    $cleanText = $normalizer->clean((string) $section->stimulus_raw);
                    $searchText = $normalizer->search($cleanText);
                    $batch[] = [
                        'section_source_id' => (int) $section->source_id,
                        'clean_text' => $cleanText,
                        'search_text' => $searchText,
                        'stimulus_text_hash' => $hasher->questionTextHash($searchText),
                        'normalizer_version' => ArabicNormalizer::VERSION,
                        'text_computed_at' => $computedAt,
                    ];
                }

                $lastSourceId = (int) $sections->last()->source_id;
                DB::connection('pgsql')->transaction(function () use ($upsert, $recorder, $batch): void {
                    $recorder->recordRead(count($batch));
                    $outcome = $upsert->runDerived(
                        'source_section_derived',
                        $batch,
                        ['section_source_id'],
                        ['clean_text', 'search_text', 'stimulus_text_hash', 'normalizer_version'],
                    );
                    $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);
                });

                $cursor->confirm(self::CURSOR_KEY, $lastSourceId);
            }, 'source_id');
    }
}
