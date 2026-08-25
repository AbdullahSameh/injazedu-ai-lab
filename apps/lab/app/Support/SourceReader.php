<?php

namespace App\Support;

use App\Exceptions\SourceTableNotAllowed;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Guard 3 of the source read-only enforcement (FR-003, FR-006, data-model.md §2).
 *
 * The only sanctioned read path to the InjazEdu source. Since P0 §3.2
 * (2026-08-23) reading and copying are different acts with different checks:
 *
 *  - READING is governed by source_tables ∪ profile_tables (assertReadable).
 *  - COPYING INTO the Lab is governed by source_tables alone (assertCopyable).
 *
 * The union is never reachable as a copy check — that separation is the entire
 * safety property of the split. Refusals name the table.
 */
class SourceReader
{
    /**
     * A query builder for a readable source table (either allowlist).
     *
     * @throws SourceTableNotAllowed
     */
    public function table(string $table): Builder
    {
        $this->assertReadable($table);

        return DB::connection('injazedu')->table($table);
    }

    /**
     * Row count of a readable source table (either allowlist).
     *
     * @throws SourceTableNotAllowed
     */
    public function count(string $table): int
    {
        return $this->table($table)->count();
    }

    /**
     * Reading check: passes for source_tables ∪ profile_tables.
     *
     * @throws SourceTableNotAllowed
     */
    public function assertReadable(string $table): void
    {
        if ($this->isCopyable($table) || $this->isProfileOnly($table)) {
            return;
        }

        throw SourceTableNotAllowed::forReading($table);
    }

    /**
     * Copy check: passes for source_tables ONLY — never the union.
     *
     * Callers that intend to store rows (P1's ETL) must ask this question,
     * not assertReadable: a profile table reads as counts and is never stored.
     *
     * @throws SourceTableNotAllowed
     */
    public function assertCopyable(string $table): void
    {
        if (! $this->isCopyable($table)) {
            throw SourceTableNotAllowed::forCopying($table);
        }
    }

    private function isCopyable(string $table): bool
    {
        return in_array($table, config('lab.source_tables', []), true);
    }

    private function isProfileOnly(string $table): bool
    {
        return in_array($table, config('lab.profile_tables', []), true);
    }
}
