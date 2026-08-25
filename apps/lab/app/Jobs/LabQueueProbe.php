<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Proves a worker actually ran (notes.md N4). "The queue connection is
 * reachable" is not evidence — this job upserts the fixed id 1 and records
 * `ran_at` plus the executing process's pid. scripts/verify-lab-app.sh asserts
 * the row exists with worker_pid ≠ the dispatcher's pid, after the worker
 * process has exited. The fixed id keeps re-running idempotent.
 */
class LabQueueProbe implements ShouldQueue
{
    use Queueable;

    public const PROBE_ID = 1;

    /** Set by the dispatcher, serialised with the job. */
    public string $dispatchedAt;

    public function __construct()
    {
        $this->dispatchedAt = now()->toDateTimeString();
    }

    public function handle(): void
    {
        DB::table('lab_job_probes')->updateOrInsert(
            ['id' => self::PROBE_ID],
            [
                'dispatched_at' => $this->dispatchedAt,
                'ran_at' => now(),
                'worker_pid' => getmypid(),
            ],
        );
    }
}
