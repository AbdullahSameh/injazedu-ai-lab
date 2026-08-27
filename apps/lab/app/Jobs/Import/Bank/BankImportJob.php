<?php

namespace App\Jobs\Import\Bank;

use App\Models\ImportRun;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportErrorRecorder;
use App\Support\Import\ImportRunRecorder;
use App\Support\Import\ResumeCursor;
use App\Support\SourceReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One MySQL table, one pass, in ascending `id` order (FR-031–FR-036). Bank
 * tables top out at ~125K rows (`options`) — small enough that a single
 * query per table is simpler and just as correct as chunking, which this
 * project reserves for the ~1.1M/~13.8M-row behavioural tables (T055–T058).
 * `--resume` still works: rows at or below the last confirmed `source_id`
 * are skipped by the `WHERE id > ?` below, and the cursor is confirmed once
 * at the end of the pass.
 *
 * Soft-deleted rows are copied with `source_deleted_at`, never excluded
 * (FR-032). `assertCopyable()` happens inside `BatchUpsert`, the single
 * write site every mirror job funnels through (FR-026).
 *
 * **Validation rides along, it does not gate** (FR-046). `validate()` is
 * called for every row *after* that row has been added to the batch, so a
 * finding can never keep a row out of the mirror: anomalies are recorded
 * beside faithful copies, never instead of them. Subclasses with no checks
 * override nothing and pay nothing.
 */
abstract class BankImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected readonly int $importRunId) {}

    public function handle(SourceReader $source, BatchUpsert $upsert): void
    {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);
        $errors = new ImportErrorRecorder($run);
        $cursor = new ResumeCursor($run);

        $this->prepareChecks($source);

        $lastId = $cursor->lastConfirmed($this->sourceTable()) ?? 0;

        // Stream from MySQL and flush every BATCH_SIZE rows rather than
        // building the whole table in memory first: `options` is ~125K rows
        // and holding all of their attribute arrays at once exhausted a
        // 512M limit in practice.
        $rows = $source->table($this->sourceTable())
            ->select($this->selectColumns())
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->cursor();

        $batch = [];
        $maxId = $lastId;

        foreach ($rows as $row) {
            $batch[] = $this->commonAttributes($row, $run) + $this->mapAttributes($row);
            $maxId = max($maxId, (int) $row->id);

            // After the row is in the batch, never before — a finding
            // records what is wrong with a row that is being copied anyway.
            $this->validate($row, $errors);

            if (count($batch) >= BatchUpsert::BATCH_SIZE) {
                $this->flush($upsert, $recorder, $batch);
                // Findings commit before the cursor moves past the rows they
                // describe. A resumed run never re-reads those rows, so a
                // finding still sitting in the buffer when the cursor
                // advanced would be lost for good rather than merely delayed.
                $errors->flush();
                // Rows arrive in ascending id order, so everything at or
                // below $maxId really is committed — the cursor can move.
                $cursor->confirm($this->sourceTable(), $maxId);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->flush($upsert, $recorder, $batch);
            $errors->flush();
            $cursor->confirm($this->sourceTable(), $maxId);
        }

        $errors->flush();
    }

    /**
     * Read whatever the pass's checks need, once, before the first row.
     * Reads the **source**, not the mirror: the mirror does not yet hold the
     * answer for the jobs that run early, and under `--resume` it holds only
     * part of it.
     */
    protected function prepareChecks(SourceReader $source): void {}

    /**
     * Run this table's checks over one source row. Default: none — a table
     * with nothing to check overrides nothing.
     */
    protected function validate(object $row, ImportErrorRecorder $errors): void {}

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    protected function flush(BatchUpsert $upsert, ImportRunRecorder $recorder, array $batch): void
    {
        $recorder->recordRead(count($batch));

        $outcome = $upsert->run($this->sourceTable(), $this->mirrorTable(), $batch);
        $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function commonAttributes(object $row, ImportRun $run): array
    {
        return [
            'source_system' => config('lab.import.source_system'),
            'source_id' => $row->id,
            'source_created_at' => $row->created_at,
            'source_updated_at' => $row->updated_at,
            'source_deleted_at' => $row->deleted_at ?? null,
            'imported_at' => now(),
            'import_run_id' => $run->id,
        ];
    }

    abstract protected function sourceTable(): string;

    abstract protected function mirrorTable(): string;

    /**
     * @return list<string>
     */
    abstract protected function selectColumns(): array;

    /**
     * The table's own content columns, plus `payload_hash` computed over
     * them — never the common columns above (data-model.md §1: every table
     * hashes its own copied columns, and the common columns are shared
     * bookkeeping, not content).
     *
     * @return array<string, mixed>
     */
    abstract protected function mapAttributes(object $row): array;
}
