<?php

namespace Tests\Validation;

use App\Models\SourceQuestion;
use App\Support\Console\InventoryMetrics;
use Illuminate\Support\Facades\DB;

/**
 * FR-057 / SC-016: at least one console statistic must be reproduced from
 * the raw mirror rows in a test, independently of the code under test —
 * these read `source_questions` and `source_sections` directly rather
 * than through {@see InventoryMetrics} itself.
 */
class InventoryMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml points DB_DATABASE at :memory: for the sqlite default;
        // the Lab schema lives in the real pgsql database, so restore it.
        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_active_question_count_reproduces_from_raw_rows(): void
    {
        $expected = SourceQuestion::query()->whereRaw('source_deleted_at IS NULL')->count();

        $this->assertSame($expected, (new InventoryMetrics)->activeQuestionCount());
        $this->assertGreaterThan(0, $expected);
    }

    public function test_answer_key_integrity_reproduces_from_a_manual_group_by(): void
    {
        $expected = DB::connection('pgsql')
            ->table('source_questions')
            ->whereNull('source_deleted_at')
            ->selectRaw('answer_key_state, count(*) as c')
            ->groupBy('answer_key_state')
            ->pluck('c', 'answer_key_state')
            ->map(fn ($count) => (int) $count)
            ->all();

        $actual = (new InventoryMetrics)->answerKeyIntegrity()
            ->pluck('count', 'state')
            ->all();

        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_category_breakdown_reproduces_from_a_manual_join(): void
    {
        $expected = DB::connection('pgsql')
            ->table('source_questions as q')
            ->join('source_sections as s', 'q.section_source_id', '=', 's.source_id')
            ->join('source_quizzes as z', 's.quiz_source_id', '=', 'z.source_id')
            ->whereNull('q.source_deleted_at')
            ->selectRaw('z.category_source_id, count(*) as c')
            ->groupBy('z.category_source_id')
            ->orderByDesc('c')
            ->pluck('c', 'z.category_source_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        $actual = (new InventoryMetrics)->byCategory()
            ->pluck('count', 'id')
            ->all();

        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }
}
