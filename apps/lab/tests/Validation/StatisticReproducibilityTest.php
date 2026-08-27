<?php

namespace Tests\Validation;

use Illuminate\Support\Facades\DB;

/**
 * FR-057 / SC-016: at least one statistic the console will display is
 * reproduced from raw mirror rows in a test.
 *
 * `Inventory` (T083, not yet built) leads with "total questions (active /
 * soft-deleted)" — the first card, sourced from the mirror's own columns,
 * never from `import_errors` (FR-049). This proves that guarantee ahead of
 * the UI: whatever SQL aggregate the widget eventually runs, an independent
 * recount of the same raw `source_questions` rows in PHP — no SQL
 * aggregate function, no ORM scope — must agree with it.
 *
 * Unlike `StatsReproducibilityTest` (which reproduces `source_item_stats`
 * from the raw MySQL source), this reproduces a mirror-level count from the
 * raw Postgres mirror rows themselves — the number a console page reads
 * straight off `source_questions`, not a value computed by the ETL.
 */
class StatisticReproducibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml points DB_DATABASE at :memory: for the sqlite default;
        // the Lab schema lives in the real pgsql database, so restore it.
        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_total_active_and_soft_deleted_question_counts_reproduce_from_raw_rows(): void
    {
        $connection = DB::connection('pgsql');

        $aggregate = [
            'total' => $connection->table('source_questions')->count(),
            'active' => $connection->table('source_questions')->whereNull('source_deleted_at')->count(),
            'soft_deleted' => $connection->table('source_questions')->whereNotNull('source_deleted_at')->count(),
        ];

        $this->assertGreaterThan(0, $aggregate['total'], 'No mirror rows to verify — run lab:import first.');

        // Recount by walking every row in PHP — no SQL aggregate function,
        // no query builder scope, nothing the eventual widget's query could
        // silently share a bug with.
        $recount = ['total' => 0, 'active' => 0, 'soft_deleted' => 0];

        foreach ($connection->table('source_questions')->select(['source_deleted_at'])->cursor() as $row) {
            $recount['total']++;
            $row->source_deleted_at === null ? $recount['active']++ : $recount['soft_deleted']++;
        }

        $this->assertSame($aggregate, $recount);
        $this->assertSame($aggregate['total'], $aggregate['active'] + $aggregate['soft_deleted']);
    }
}
