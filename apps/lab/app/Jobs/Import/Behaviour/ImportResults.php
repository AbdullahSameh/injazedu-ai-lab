<?php

namespace App\Jobs\Import\Behaviour;

use App\Models\ImportRun;
use App\Support\Derive\PayloadHasher;
use App\Support\Derive\StudentRefHasher;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportRunRecorder;
use App\Support\Import\ResumeCursor;
use App\Support\SourceReader;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * `results` → `source_results` — ~1.1M rows (FR-020, FR-037, FR-039,
 * data-model.md §2). `user_id` is read and hashed into `student_ref` inside
 * the same expression that builds `$content` — no named variable ever holds
 * the raw id, no log line can print it, and no column but the hash receives
 * it. `attempt_index` is left NULL here; `DeriveAttemptIndex` fills it in a
 * second Postgres-side pass once every row exists. Unlike the bank tables,
 * `source_deleted_at` populates for real — `results` carries a genuine
 * `deleted_at` in the source.
 *
 * `payload_hash` hashes `student_ref`, never the raw `user_id` — the pepper
 * makes the hash deterministic across re-imports, so an unchanged row still
 * reports `unchanged` (FR-024).
 *
 * `user_id` is NULL on 71% of source rows (undocumented in data-model.md
 * §2, found running the real import) — mostly rows soft-deleted shortly
 * after creation, plus ~41.6K live rows with no linked user. There is no id
 * to hash, so `student_ref` stays NULL: a fabricated or sentinel value
 * would falsely correlate unrelated anonymous attempts as one identity.
 * `source_results.student_ref` is nullable for exactly this reason.
 *
 * Writes through `BatchUpsert`, not `Upsert` — at this row count the
 * per-row round-trips are the whole cost (see `BatchUpsert`).
 */
final class ImportResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId) {}

    public function handle(SourceReader $source, BatchUpsert $upsert, StudentRefHasher $studentRefHasher): void
    {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);
        $cursor = new ResumeCursor($run);

        $lastId = $cursor->lastConfirmed('results') ?? 0;
        $hasher = new PayloadHasher;

        while (true) {
            $rows = $source->table('results')
                ->select(['id', 'total_points', 'user_id', 'quiz_id', 'created_at', 'updated_at', 'deleted_at'])
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(BatchUpsert::BATCH_SIZE)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $batch = [];

            foreach ($rows as $row) {
                $content = [
                    'quiz_source_id' => $row->quiz_id,
                    'total_points' => $row->total_points,
                    'student_ref' => $row->user_id !== null ? $studentRefHasher->hash($row->user_id) : null,
                    'duration_estimate_seconds' => ($row->created_at !== null && $row->updated_at !== null)
                        ? (int) Carbon::parse($row->created_at)->diffInSeconds(Carbon::parse($row->updated_at))
                        : null,
                ];

                $batch[] = $content + [
                    'source_system' => config('lab.import.source_system'),
                    'source_id' => $row->id,
                    'source_created_at' => $row->created_at,
                    'source_updated_at' => $row->updated_at,
                    'source_deleted_at' => $row->deleted_at,
                    'imported_at' => now(),
                    'import_run_id' => $run->id,
                    'payload_hash' => $hasher->hash($content),
                ];

                $lastId = (int) $row->id;
            }

            $recorder->recordRead(count($batch));

            $outcome = $upsert->run('results', 'source_results', $batch);
            $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);

            $cursor->confirm('results', $lastId);
        }
    }
}
