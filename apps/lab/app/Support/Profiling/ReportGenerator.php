<?php

namespace App\Support\Profiling;

/**
 * Renders `docs/reports/p1-profiling.md` from `source_snapshots.profiling_results`
 * alone (FR-005, contracts/profiling-results.md). Regenerating without a new
 * run must produce a byte-identical file, so nothing here may read the clock,
 * a database connection, or anything else outside the JSON it is given.
 */
final class ReportGenerator
{
    public function generate(array $profilingResults): string
    {
        $lines = [
            '# P1 Profiling Report',
            '',
            "**snapshot_taken_at**: {$profilingResults['snapshot_taken_at']}",
            "**run_at**: {$profilingResults['run_at']}",
            "**mysql_version**: {$profilingResults['mysql_version']}",
            "**source_database_size_mb**: {$profilingResults['source_database_size_mb']}",
            '',
        ];

        $queries = $profilingResults['queries'] ?? [];
        ksort($queries, SORT_NUMERIC);

        foreach ($queries as $number => $query) {
            array_push($lines, ...$this->renderQuery((int) $number, $query));
        }

        return implode("\n", $lines)."\n";
    }

    public function write(string $reportPath, array $profilingResults): string
    {
        file_put_contents($reportPath, $this->generate($profilingResults));

        return $reportPath;
    }

    /**
     * @return list<string>
     */
    private function renderQuery(int $number, array $query): array
    {
        $lines = [
            "## {$number}. {$query['title']}",
            '',
            "- file: `{$query['file']}`",
            '- tables_read: '.implode(', ', $query['tables_read']),
            "- allowlist: {$query['allowlist']}",
        ];

        if (array_key_exists('error', $query)) {
            $lines[] = "- **error**: {$query['error']}";
            $lines[] = '';

            return $lines;
        }

        $lines[] = "- row_count: {$query['row_count']}";
        $lines[] = "- duration_ms: {$query['duration_ms']}";
        $lines[] = '';
        array_push($lines, ...$this->renderRowsTable($query['columns'], $query['rows']));
        $lines[] = '';

        return $lines;
    }

    /**
     * jsonb does not preserve object key order on round-trip, so both the
     * header and each row's cells are read by explicit column name — never
     * by PHP array iteration order, which reflects Postgres's internal
     * reordering rather than the query's own column order.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function renderRowsTable(array $columns, array $rows): array
    {
        if ($rows === []) {
            return ['_(no rows)_'];
        }

        $lines = [
            '| '.implode(' | ', $columns).' |',
            '| '.implode(' | ', array_fill(0, count($columns), '---')).' |',
        ];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(
                fn (string $column) => $row[$column] === null ? 'NULL' : (string) $row[$column],
                $columns
            )).' |';
        }

        return $lines;
    }
}
