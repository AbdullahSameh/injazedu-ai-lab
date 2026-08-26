<?php

namespace App\Jobs\Import\Behaviour;

use App\Models\ImportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Second pass over `source_results` — not a copy from MySQL, a Postgres-side
 * derivation that must run after `ImportResults` (FR-038, data-model.md
 * §2). `ROW_NUMBER() OVER (PARTITION BY student_ref, quiz_source_id ORDER
 * BY source_created_at)`, recomputed over every row on every run: an order
 * of magnitude cheaper as one SQL statement than looping 1.1M rows in PHP,
 * and naturally idempotent — re-running assigns the same numbers unless the
 * underlying rows changed (FR-024). Soft-deleted results are included in
 * the partition, matching this project's rule of never excluding
 * soft-deleted rows at import time (FR-032) — exclusion is a consumer's
 * decision, not this pass's.
 *
 * Rows with a NULL `student_ref` (71% of `results` has a NULL `user_id` —
 * see `ImportResults`) are excluded from the ranked subquery entirely, so
 * they keep a NULL `attempt_index` forever: "this student's Nth attempt"
 * is undefined without a student, and PARTITION BY would otherwise treat
 * every NULL as one shared group, numbering unrelated anonymous attempts
 * on the same quiz as if they were one person's sequence.
 *
 * Takes `$importRunId` only to fail loudly on an unknown run, for the same
 * uniform `new $jobClass($run->id)` dispatch `LabImport` uses for every
 * other job — the derivation itself is global, not scoped to one run.
 */
final class DeriveAttemptIndex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $importRunId) {}

    public function handle(): void
    {
        ImportRun::findOrFail($this->importRunId);

        DB::connection('pgsql')->statement(<<<'SQL'
            UPDATE source_results AS r
            SET attempt_index = ranked.attempt_index
            FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY student_ref, quiz_source_id
                    ORDER BY source_created_at
                ) AS attempt_index
                FROM source_results
                WHERE student_ref IS NOT NULL
            ) AS ranked
            WHERE r.id = ranked.id
        SQL);
    }
}
