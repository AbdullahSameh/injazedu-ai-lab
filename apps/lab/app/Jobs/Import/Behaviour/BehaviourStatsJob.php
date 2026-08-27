<?php

namespace App\Jobs\Import\Behaviour;

use App\Models\ImportRun;
use App\Support\SourceReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Shared base for the two aggregate-pushdown jobs (ADR-022).
 *
 * These jobs read `question_result` and `results` and store nothing from
 * them row-for-row — they store GROUP BY results. That is why they call
 * `assertReadable()` and never `assertCopyable()`: since 2026-08-26
 * `question_result` sits on `profile_tables`, so `assertCopyable()` would
 * correctly throw, and the reading check is the honest description of what
 * this actually does. Both tables are asserted up front, before any SQL
 * runs, matching `LabProfile`'s check-everything-first pattern — a widened
 * reach fails before it touches the source.
 *
 * The derived tables are not mirrors, so writes here do NOT go through
 * `BatchUpsert`: its `assertCopyable()` guard is exactly right for a mirror
 * and exactly wrong for an aggregate.
 */
abstract class BehaviourStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 'active' counts only attempts the source has not soft-deleted;
     * 'all' counts every attempt. 71% of `results` rows carry a
     * `deleted_at`, so the two differ a great deal — which one is the right
     * basis for a published statistic is P3's decision, and storing both
     * means it does not have to be guessed here (operator decision,
     * 2026-08-26).
     */
    protected const SCOPES = ['active', 'all'];

    public function __construct(protected readonly int $importRunId) {}

    /** Rows per upsert statement, matching `BatchUpsert`. */
    protected const FLUSH_SIZE = 1000;

    /**
     * Decimal places every stored statistic is rounded to.
     *
     * PostgreSQL renders `double precision` at `extra_float_digits`, and PHP
     * parses it back at `precision = 14` — so a raw double written here comes
     * back differing in the 15th digit and no longer matches its own
     * `stats_hash`. Rounding first makes the stored value, the hash, and
     * every later read exactly consistent.
     *
     * Ten is safe and generous: these are point-score means and ratios whose
     * largest magnitude is ~132 (three integer digits), so a rounded value
     * needs at most 13 significant digits and round-trips exactly. It is also
     * far finer than any resolution these numbers actually carry.
     */
    protected const STAT_PRECISION = 10;

    /**
     * Round a computed statistic to the stored precision, preserving NULL.
     */
    protected function stat(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, self::STAT_PRECISION);
    }

    protected function guardedRun(SourceReader $source): ImportRun
    {
        $source->assertReadable('question_result');
        $source->assertReadable('results');

        // The aggregate maps are held for the whole pass — ~28K questions or
        // ~125K options — which is past the 128M CLI/worker default. Set here
        // rather than in `LabImport`, because a `--queue` dispatch runs this
        // in a worker process that never executes the command's code (the
        // same reasoning as `QuestionOptionsFetcher`). -1 means "already
        // unlimited" — leave it alone.
        if (ini_get('memory_limit') !== '-1') {
            ini_set('memory_limit', '512M');
        }

        return ImportRun::findOrFail($this->importRunId);
    }

    /**
     * The soft-delete predicate for a scope, as a SQL fragment.
     */
    protected function scopePredicate(string $scope): string
    {
        return $scope === 'active' ? 'WHERE r.deleted_at IS NULL' : '';
    }
}
