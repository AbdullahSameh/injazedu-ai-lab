<?php

namespace App\Console\Commands;

use App\Exceptions\SourceTableNotAllowed;
use App\Models\SourceSnapshot;
use App\Support\Profiling\QueryFile;
use App\Support\Profiling\QueryPack;
use App\Support\Profiling\ReportGenerator;
use App\Support\SourceReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LabProfile extends Command
{
    protected $signature = 'lab:profile {--dry-run : List the files and their declared tables; execute nothing} {--query= : Run exactly one file, by its §6 number}';

    protected $description = 'Run the §6 profiling queries over the guarded injazedu connection';

    public function handle(QueryPack $pack, SourceReader $source, ReportGenerator $report): int
    {
        $files = $pack->files();

        if ($this->option('query') !== null) {
            $number = (int) $this->option('query');
            $files = array_values(array_filter(
                $files,
                fn (QueryFile $file) => $file->number === $number
            ));

            if ($files === []) {
                $this->error("No query file numbered [{$number}] in the pack.");

                return self::FAILURE;
            }
        }

        // FR-002: every declared table across this run's file scope must pass
        // assertReadable() before the first file executes — checked up front,
        // not lazily per file, so a widened-reach mistake fails before any SQL
        // runs at all (spec §"lab:profile widening its own reach").
        try {
            foreach ($files as $file) {
                foreach ($file->tablesRead as $table) {
                    $source->assertReadable($table);
                }
            }
        } catch (SourceTableNotAllowed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['#', 'File', 'Tables read', 'Allowlist'],
                array_map(fn (QueryFile $file) => [
                    $file->number, $file->filename, implode(', ', $file->tablesRead), $file->allowlist,
                ], $files)
            );

            return self::SUCCESS;
        }

        $runAt = now();
        $queries = [];

        foreach ($files as $file) {
            $queries[(string) $file->number] = $this->runFile($file);

            $entry = $queries[(string) $file->number];
            $this->line(sprintf(
                '  [%d] %s — %s',
                $file->number,
                $file->filename,
                array_key_exists('error', $entry) ? 'ERROR: '.$entry['error'] : "{$entry['row_count']} rows",
            ));
        }

        $mysqlVersion = DB::connection('injazedu')->selectOne('SELECT VERSION() AS version')->version;
        $sizeMb = (float) DB::connection('injazedu')->selectOne(
            'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb '.
            'FROM information_schema.tables WHERE table_schema = DATABASE()'
        )->size_mb;

        $envelope = [
            'schema_version' => 1,
            'snapshot_taken_at' => config('lab.snapshot_taken_at'),
            'run_at' => $runAt->toIso8601ZuluString(),
            'mysql_version' => $mysqlVersion,
            'source_database_size_mb' => $sizeMb,
            'queries' => $queries,
        ];

        $sourceRowCounts = collect(config('lab.source_tables'))
            ->mapWithKeys(fn (string $table) => [$table => $source->count($table)])
            ->all();

        $snapshot = SourceSnapshot::create([
            'snapshot_taken_at' => config('lab.snapshot_taken_at'),
            'loaded_at' => $runAt,
            'mysql_version' => $mysqlVersion,
            'source_database_size_mb' => $sizeMb,
            'source_row_counts' => $sourceRowCounts,
            'profiling_results' => $envelope,
            'profiling_report_path' => 'docs/reports/p1-profiling.md',
        ]);

        $report->write(config('lab.profiling.report_path'), $snapshot->profiling_results);

        $this->table(
            ['#', 'File', 'Rows', 'Duration (ms)', 'Status'],
            array_map(function (QueryFile $file) use ($queries) {
                $entry = $queries[(string) $file->number];

                return [
                    $file->number,
                    $file->filename,
                    $entry['row_count'] ?? '—',
                    $entry['duration_ms'],
                    array_key_exists('error', $entry) ? 'ERROR' : 'OK',
                ];
            }, $files)
        );

        $this->info("Snapshot recorded: source_snapshots.id={$snapshot->id}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function runFile(QueryFile $file): array
    {
        $entry = [
            'file' => $file->filename,
            'title' => $file->title,
            'tables_read' => $file->tablesRead,
            'allowlist' => $file->allowlist,
            'executed_at' => now()->toIso8601ZuluString(),
        ];

        $startedAt = microtime(true);

        try {
            $rows = array_map(
                fn (object $row) => (array) $row,
                DB::connection('injazedu')->select($file->statement)
            );

            $entry['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $entry['row_count'] = count($rows);
            // jsonb does not preserve object key order on round-trip (it
            // reorders by length, then lexicographically) — the column order
            // is recorded explicitly so the report can render it verbatim
            // (FR-005, contracts/profiling-results.md §2).
            $entry['columns'] = $rows === [] ? [] : array_keys($rows[0]);
            $entry['rows'] = $rows;
        } catch (Throwable $e) {
            $entry['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
            $entry['error'] = $e->getMessage();
        }

        return $entry;
    }
}
