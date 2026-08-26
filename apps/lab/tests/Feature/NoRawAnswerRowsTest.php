<?php

namespace Tests\Feature;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\SourceReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ADR-022: the Lab stores no individual answer events, and cannot start
 * storing them by accident.
 *
 * `question_result` is unbounded behavioural data — it grows with students
 * x time forever. Everything the program needs from it is an aggregate
 * whose size is bounded by the question count. The architectural rule is
 * therefore "aggregate, never mirror", and these assertions are what make
 * that a property of the system rather than a note in a plan.
 *
 * Three independent locks, each of which fails on its own:
 *  - the raw table does not exist;
 *  - no Lab table carries an answer-row shaped column;
 *  - the copy guard refuses the source table by name.
 */
class NoRawAnswerRowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_the_raw_answers_table_does_not_exist(): void
    {
        $exists = DB::connection('pgsql')
            ->table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_name', 'source_answers')
            ->exists();

        $this->assertFalse($exists, 'source_answers is back — the raw answer mirror was reintroduced.');
    }

    public function test_no_lab_table_carries_an_answer_row_shaped_column(): void
    {
        // `result_source_id` only ever means "this row is one student's one
        // answer". Its presence anywhere is the mirror returning by another
        // name.
        $columns = DB::connection('pgsql')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'result_source_id')
            ->pluck('table_name');

        $this->assertEmpty(
            $columns->all(),
            'Answer-level columns found on: '.json_encode($columns->all())
        );
    }

    public function test_question_result_is_readable_but_never_copyable(): void
    {
        $reader = app(SourceReader::class);

        // Readable: the aggregates genuinely do read it.
        $reader->assertReadable('question_result');
        $this->addToAssertionCount(1);

        try {
            $reader->assertCopyable('question_result');
            $this->fail('SourceReader offered to copy question_result — the raw mirror is possible again.');
        } catch (SourceTableNotAllowed $e) {
            $this->assertStringContainsString('question_result', $e->getMessage());
        }
    }

    public function test_the_derived_statistics_tables_are_present_instead(): void
    {
        foreach (['source_item_stats', 'source_option_stats'] as $table) {
            $this->assertTrue(
                DB::connection('pgsql')->table('information_schema.tables')
                    ->where('table_schema', 'public')->where('table_name', $table)->exists(),
                "{$table} is missing — the replacement for the raw mirror is not in place."
            );
        }
    }
}
