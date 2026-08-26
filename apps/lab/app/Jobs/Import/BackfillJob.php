<?php

namespace App\Jobs\Import;

use App\Models\ImportRun;
use App\Support\Import\ImportRunRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * The one idempotent second-pass pattern, three uses (plan.md "Decisions
 * Taken Under Principle I"): `questions_count`, `requires_media_review` and
 * `answer_key_state` all describe a mirror row using a table that does not
 * exist yet when that row is written, because the bank import order is a
 * key-dependency order and cannot be rearranged (FR-013, FR-034, FR-061).
 *
 * Each pass is **one Postgres statement over the whole mirror**, not a copy
 * and not a read of MySQL — the same shape as `DeriveAttemptIndex`, for the
 * same reason: an order of magnitude cheaper than looping rows in PHP, and
 * atomic. Nothing here calls `assertCopyable()` because nothing here copies;
 * the source database is not touched at all.
 *
 * Idempotency is structural rather than promised. Every statement ends in
 * `AND <column> IS DISTINCT FROM <recomputed>`, so a second run matches no
 * rows and writes nothing — and `rows_updated = 0` on that run is the
 * evidence, not an assertion (FR-024, SC-020). `IS DISTINCT FROM` rather
 * than `<>` because a NULL on either side must count as a difference, never
 * as an unknown that silently skips the row.
 *
 * Each pass touches exactly one column. That is the property the operator
 * is owed: re-running a backfill can never disturb a copied value.
 *
 * Takes `$importRunId` for the uniform `new $jobClass($run->id)` dispatch
 * `LabImport` uses for every other job, and to fail loudly on an unknown
 * run — the derivation itself is global, not scoped to one run.
 */
abstract class BackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected readonly int $importRunId) {}

    public function handle(): void
    {
        $run = ImportRun::findOrFail($this->importRunId);
        $recorder = ImportRunRecorder::for($run);

        $this->guard();

        $connection = DB::connection('pgsql');

        $examined = $connection->table($this->mirrorTable())->count();
        $updated = $connection->update($this->statement());

        $recorder->recordRead($examined);
        // Nothing is ever inserted by a backfill: the rows already exist,
        // and a pass that had to create one would mean the copy pass missed
        // it — a defect, not something to paper over here.
        $recorder->recordOutcomes(0, $updated, $examined - $updated);
    }

    /**
     * Refuse to run rather than derive from a decision that was never
     * recorded. Only `BackfillAnswerKeyState` has one (FR-061); the default
     * is deliberately permissive because a count is not an interpretation.
     */
    protected function guard(): void {}

    /** The mirror table whose rows this pass rewrites — one column of them. */
    abstract protected function mirrorTable(): string;

    /**
     * One `UPDATE … FROM (…) WHERE … IS DISTINCT FROM …` over the whole
     * table. No bindings: every value is recomputed in SQL from mirror rows.
     */
    abstract protected function statement(): string;
}
