<?php

namespace Tests\Validation;

use App\Support\SourceReader;
use Illuminate\Support\Facades\DB;

/**
 * FR-037 / SC-006: `source_results` is the one behavioural table still
 * mirrored row-for-row, and it must stay complete.
 *
 * Attempt-level rows are bounded enough to keep (1.1M) and are what
 * `attempt_index`, cohort analysis and the corrected totals behind the
 * point-biserial are built on. Since the answer events are no longer
 * mirrored, this table's completeness carries more weight than it used to:
 * it is the only row-level behavioural record the Lab holds.
 *
 * `count` alone would pass while silently holding the wrong rows, so the id
 * sum goes with it — together they pin both size and membership.
 */
class SourceResultsCompletenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_mirror_matches_the_source_in_count_and_membership(): void
    {
        $mirror = DB::connection('pgsql')
            ->selectOne('SELECT count(*) AS c, COALESCE(sum(source_id), 0) AS s FROM source_results');

        $source = app(SourceReader::class)->table('results')
            ->selectRaw('count(*) AS c, COALESCE(sum(id), 0) AS s')->first();

        $this->assertSame((int) $source->c, (int) $mirror->c, 'Row count drifted from the source.');
        $this->assertSame((string) $source->s, (string) $mirror->s, 'Same count, different rows.');
    }

    public function test_no_source_id_appears_twice(): void
    {
        $rows = DB::connection('pgsql')->selectOne(
            'SELECT count(*) AS total, count(DISTINCT source_id) AS distinct_ids FROM source_results'
        );

        $this->assertSame((int) $rows->total, (int) $rows->distinct_ids);
    }

    public function test_attempt_index_is_contiguous_within_each_student_and_quiz(): void
    {
        // ROW_NUMBER() must yield 1..n with no gap and no repeat per group.
        $malformed = DB::connection('pgsql')->selectOne(
            "SELECT count(*) AS c FROM (
                SELECT student_ref, quiz_source_id,
                       count(*) AS n, min(attempt_index) AS mn,
                       max(attempt_index) AS mx, count(DISTINCT attempt_index) AS d
                FROM source_results
                WHERE student_ref IS NOT NULL
                GROUP BY student_ref, quiz_source_id
            ) t WHERE mn <> 1 OR mx <> n OR d <> n"
        );

        $this->assertSame(0, (int) $malformed->c);
    }

    public function test_anonymous_attempts_carry_neither_a_ref_nor_an_attempt_index(): void
    {
        // 71% of source rows have a NULL user_id. There is no id to hash, so
        // student_ref stays NULL — and "this student's Nth attempt" is
        // undefined without a student, so attempt_index must stay NULL too.
        $leaked = DB::connection('pgsql')->selectOne(
            'SELECT count(*) AS c FROM source_results
             WHERE student_ref IS NULL AND attempt_index IS NOT NULL'
        );

        $this->assertSame(0, (int) $leaked->c, 'An anonymous attempt was given an attempt_index.');
    }
}
