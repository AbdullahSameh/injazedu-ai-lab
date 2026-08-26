<?php

namespace App\Support\Import;

use App\Models\ImportRun;

/**
 * Creates and tracks one `import_runs` row per `lab:import` invocation
 * (FR-022, FR-028, FR-041). Every mirror row a job writes must carry this
 * run's id in its own `import_run_id` column — that linking is the caller's
 * job when it builds the attributes passed to `BatchUpsert::run()`; this class
 * only owns the run row itself and its counters.
 */
final class ImportRunRecorder
{
    private ImportRun $run;

    private float $startedAtMonotonic;

    /**
     * @param  array<string, int>  $resumeCursor
     */
    public function start(
        int $snapshotId,
        string $kind,
        string $ranVia,
        string $status = 'running',
        array $resumeCursor = [],
    ): ImportRun {
        $this->startedAtMonotonic = microtime(true);

        $this->run = ImportRun::create([
            'snapshot_id' => $snapshotId,
            'kind' => $kind,
            'started_at' => now(),
            'status' => $status,
            'ran_via' => $ranVia,
            'resume_cursor' => $resumeCursor,
            // Set explicitly, not left to the column default: create() takes
            // this array as the in-memory model's attributes verbatim, so an
            // omitted key reads as null here even though Postgres would fill
            // 0 — and a run that writes nothing must still display as 0.
            'rows_read' => 0,
            'rows_inserted' => 0,
            'rows_updated' => 0,
            'rows_unchanged' => 0,
            'error_count' => 0,
        ]);

        return $this->run;
    }

    public function run(): ImportRun
    {
        return $this->run;
    }

    /**
     * Attach to an already-started run — for a job resolved fresh out of the
     * container (inline or via the queue) that needs to record against the
     * run `LabImport` already created, rather than creating a new one.
     */
    public static function for(ImportRun $run): self
    {
        $recorder = new self;
        $recorder->run = $run;
        $recorder->startedAtMonotonic = microtime(true);

        return $recorder;
    }

    public function recordRead(int $count = 1): void
    {
        $this->run->increment('rows_read', $count);
    }

    /**
     * Records one `BatchUpsert::run()` return value — one UPDATE per counter
     * per batch, never per row.
     */
    public function recordOutcomes(int $inserted, int $updated, int $unchanged): void
    {
        if ($inserted > 0) {
            $this->run->increment('rows_inserted', $inserted);
        }

        if ($updated > 0) {
            $this->run->increment('rows_updated', $updated);
        }

        if ($unchanged > 0) {
            $this->run->increment('rows_unchanged', $unchanged);
        }
    }

    public function recordError(int $count = 1): void
    {
        $this->run->increment('error_count', $count);
    }

    public function finish(string $status = 'completed'): void
    {
        $this->run->forceFill([
            'status' => $status,
            'finished_at' => now(),
            'elapsed_seconds' => round(microtime(true) - $this->startedAtMonotonic, 3),
        ])->save();
    }
}
