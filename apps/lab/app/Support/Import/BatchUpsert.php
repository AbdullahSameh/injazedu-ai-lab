<?php

namespace App\Support\Import;

use App\Support\SourceReader;
use Illuminate\Support\Facades\DB;

/**
 * `Upsert` for the behavioural tables — identical semantics, one statement
 * per batch instead of two round-trips per row (FR-023, FR-026).
 *
 * `Upsert::run()` does a SELECT then an INSERT/UPDATE per row. At 13.8M
 * rows (`question_result`) that is ~7 hours of pure round-trip latency,
 * measured. This class issues one
 * `INSERT … ON CONFLICT … DO UPDATE … WHERE payload_hash IS DISTINCT FROM
 * EXCLUDED.payload_hash` per batch — 59× faster, measured, with the same
 * three outcomes:
 *
 *   - unseen (source_system, source_id)  → inserted
 *   - key exists, hash differs           → updated
 *   - key exists, hash matches           → unchanged, and **no write at
 *     all**, because the WHERE suppresses the UPDATE (FR-024)
 *
 * The counters stay exact via `RETURNING (xmax = 0)`: a freshly inserted
 * tuple has `xmax = 0`, one updated through ON CONFLICT does not, and a
 * row whose UPDATE the WHERE suppressed is not returned at all — so
 * `unchanged = count(batch) − count(returned)`. This is a single-writer
 * ETL, which is what makes the `xmax` read dependable here.
 *
 * `assertCopyable()` is called before every batch, exactly as `Upsert`
 * does it — the guarantee `CopyGuardTest` (T072) exists to protect.
 */
final class BatchUpsert
{
    /**
     * Rows per statement. Postgres caps a single statement at 65,535 bound
     * parameters; at ~13 columns a 1,000-row batch binds ~13,000, which
     * leaves generous headroom for a wider table.
     */
    public const BATCH_SIZE = 1000;

    public function __construct(private readonly SourceReader $sourceReader) {}

    /**
     * A mirror write: guarded by the copy allowlist, keyed on
     * (`source_system`, `source_id`), gated on `payload_hash`.
     *
     * @param  list<array<string, mixed>>  $rows  all sharing one column set
     * @return array{inserted: int, updated: int, unchanged: int}
     */
    public function run(string $sourceTable, string $mirrorTable, array $rows): array
    {
        $this->sourceReader->assertCopyable($sourceTable);

        return $this->write($mirrorTable, $rows, ['source_system', 'source_id'], 'payload_hash');
    }

    /**
     * A derived-table write: the same statement and the same counters, with
     * no copy check, because nothing is being copied. The stats tables hold
     * aggregates computed from the source, not rows taken from it — and
     * their source table is deliberately not copyable at all (ADR-022).
     * Callers are responsible for having asserted READability themselves.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $conflictKey
     * @return array{inserted: int, updated: int, unchanged: int}
     */
    public function runDerived(string $table, array $rows, array $conflictKey, string $hashColumn): array
    {
        return $this->write($table, $rows, $conflictKey, $hashColumn);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $conflictKey
     * @return array{inserted: int, updated: int, unchanged: int}
     */
    private function write(string $table, array $rows, array $conflictKey, string $hashColumn): array
    {
        $mirrorTable = $table;

        $totals = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0];

        if ($rows === []) {
            return $totals;
        }

        $columns = array_keys($rows[0]);
        $updatable = array_values(array_diff($columns, $conflictKey));

        $placeholders = '('.implode(', ', array_fill(0, count($columns), '?')).')';
        $assignments = implode(', ', array_map(
            static fn (string $c): string => "\"{$c}\" = EXCLUDED.\"{$c}\"",
            $updatable
        ));
        $columnList = implode(', ', array_map(static fn (string $c): string => "\"{$c}\"", $columns));

        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            $bindings = [];

            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $bindings[] = $row[$column];
                }
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s
                 ON CONFLICT (%s) DO UPDATE SET %s
                 WHERE %s.%s IS DISTINCT FROM EXCLUDED.%s
                 RETURNING (xmax = 0) AS was_inserted',
                $mirrorTable,
                $columnList,
                implode(', ', array_fill(0, count($chunk), $placeholders)),
                implode(', ', array_map(static fn (string $c): string => "\"{$c}\"", $conflictKey)),
                $assignments,
                $mirrorTable,
                $hashColumn,
                $hashColumn,
            );

            $returned = DB::connection('pgsql')->select($sql, $bindings);

            $inserted = count(array_filter($returned, static fn (object $r): bool => (bool) $r->was_inserted));

            $totals['inserted'] += $inserted;
            $totals['updated'] += count($returned) - $inserted;
            $totals['unchanged'] += count($chunk) - count($returned);
        }

        return $totals;
    }
}
