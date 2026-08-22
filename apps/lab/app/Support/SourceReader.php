<?php

namespace App\Support;

use App\Exceptions\SourceTableNotAllowed;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Guard 3 of the source read-only enforcement (FR-003, FR-006, data-model.md §2).
 *
 * The only sanctioned read path to the InjazEdu source. Refuses — naming the
 * table — anything outside config('lab.source_tables'). The other thirty-nine
 * tables in the database are unreachable from here.
 */
class SourceReader
{
    /**
     * A query builder for an allowlisted source table.
     *
     * @throws SourceTableNotAllowed
     */
    public function table(string $table): Builder
    {
        $this->assertAllowed($table);

        return DB::connection('injazedu')->table($table);
    }

    /**
     * Row count of an allowlisted source table.
     *
     * @throws SourceTableNotAllowed
     */
    public function count(string $table): int
    {
        return $this->table($table)->count();
    }

    /**
     * @throws SourceTableNotAllowed
     */
    public function assertAllowed(string $table): void
    {
        if (! in_array($table, config('lab.source_tables', []), true)) {
            throw SourceTableNotAllowed::forTable($table);
        }
    }
}
