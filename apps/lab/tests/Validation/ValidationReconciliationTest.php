<?php

namespace Tests\Validation;

use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Import\ImportErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * FR-045 / SC-013 / SC-014: the anomalies the mirror reports must be the
 * same anomalies the profiling run measured.
 *
 * The two arrive by completely different routes — the profiling pack is a
 * MySQL `GROUP BY` over the source, the validators are PHP running row by
 * row during the copy — so agreement between them is real evidence and
 * disagreement is a defect in one of the two. FR-045 is explicit that it is
 * not a number to reconcile by adjusting a check.
 *
 * The expected counts are **read from `source_snapshots.profiling_results`**,
 * never written here as literals. A literal would turn this into a test of
 * what someone typed; reading the stored run makes it a test of the two
 * pipelines against each other.
 *
 * Runs against the live mirror and the latest completed bank run, following
 * this suite's convention (see `StatsReproducibilityTest`): `lab:import`
 * must have run.
 */
class ValidationReconciliationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_the_zero_correct_rate_among_active_questions_equals_query_3(): void
    {
        $expected = $this->questionsAtCorrectCount(0);

        $this->assertSame(
            $expected,
            $this->activeFindings(ImportErrorCode::ZERO_CORRECT),
            'The mirror and the profiling run disagree about how many questions have no correct answer. '
            .'One of the two is wrong (FR-045) — this is not a threshold to relax.'
        );
    }

    public function test_the_multi_correct_count_equals_query_3(): void
    {
        // Query 3 buckets by correct_count; everything above 1 is multi-key.
        $expected = array_sum(array_map(
            fn (array $row): int => (int) $row['questions'],
            array_filter($this->profilingQuery(3), fn (array $row): bool => (int) $row['correct_count'] > 1)
        ));

        $this->assertSame($expected, $this->activeFindings(ImportErrorCode::MULTI_CORRECT));
    }

    public function test_the_missing_options_count_equals_query_2(): void
    {
        $expected = array_sum(array_map(
            fn (array $row): int => (int) $row['questions'],
            array_filter($this->profilingQuery(2), fn (array $row): bool => (int) $row['opt_count'] === 0)
        ));

        $this->assertSame($expected, $this->activeFindings(ImportErrorCode::MISSING_OPTIONS));
    }

    public function test_the_option_order_tie_count_equals_query_5(): void
    {
        $expected = (int) $this->profilingQuery(5)[0]['questions_with_order_ties'];

        $this->assertSame(
            $expected,
            $this->findings(ImportErrorCode::OPTION_ORDER_TIE),
            'Query 5 counts ties over every question, deleted included.'
        );
    }

    public function test_no_row_was_dropped_or_repaired_because_it_had_a_finding(): void
    {
        // FR-046, the heart of this phase: an anomaly is recorded *beside* a
        // faithful copy. A question that earned a finding and then went
        // missing from the mirror would mean a check had gained the power to
        // drop rows.
        $orphanedFindings = DB::connection('pgsql')->selectOne(
            'SELECT count(*) AS c
             FROM import_errors e
             WHERE e.import_run_id = ?
               AND e.source_table = ?
               AND NOT EXISTS (
                   SELECT 1 FROM source_questions q WHERE q.source_id = e.source_id
               )',
            [$this->lastBankRunId(), 'questions']
        );

        $this->assertSame(0, (int) $orphanedFindings->c, 'A question with a finding is not in the mirror.');
    }

    public function test_every_finding_names_a_code_the_enum_knows(): void
    {
        $known = array_map(fn (ImportErrorCode $c): string => $c->value, ImportErrorCode::cases());

        $unknown = DB::connection('pgsql')->table('import_errors')
            ->whereNotIn('code', $known)
            ->distinct()
            ->pluck('code')
            ->all();

        $this->assertSame([], $unknown, 'A code was written that the one enumeration does not define (FR-044).');
    }

    public function test_severity_in_the_log_matches_the_enum(): void
    {
        $rows = DB::connection('pgsql')->table('import_errors')
            ->select('code', 'severity')
            ->distinct()
            ->get();

        $this->assertNotEmpty($rows, 'No anomalies logged — run `lab:import --kind=bank` first.');

        foreach ($rows as $row) {
            $this->assertSame(
                ImportErrorCode::from($row->code)->severity(),
                $row->severity,
                "Severity for {$row->code} disagrees with the enum."
            );
        }
    }

    private function lastBankRunId(): int
    {
        $run = ImportRun::where('kind', 'bank')->where('status', 'completed')->latest('id')->first();

        $this->assertNotNull($run, 'No completed bank run — run `lab:import --kind=bank` first.');

        return (int) $run->id;
    }

    /** Findings of one code in the latest bank run, counting every question. */
    private function findings(ImportErrorCode $code): int
    {
        return (int) DB::connection('pgsql')->table('import_errors')
            ->where('import_run_id', $this->lastBankRunId())
            ->where('code', $code->value)
            ->count();
    }

    /**
     * Findings of one code among **active** questions only. The profiling
     * pack excludes soft-deleted rows (`WHERE deleted_at IS NULL`), so the
     * comparison has to exclude them too — the validators log both and
     * record which is which in `context`.
     */
    private function activeFindings(ImportErrorCode $code): int
    {
        return (int) DB::connection('pgsql')->table('import_errors')
            ->where('import_run_id', $this->lastBankRunId())
            ->where('code', $code->value)
            ->whereRaw("(context->>'soft_deleted')::boolean IS FALSE")
            ->count();
    }

    /**
     * One query's rows, straight out of the stored profiling run.
     *
     * @return list<array<string, mixed>>
     */
    private function profilingQuery(int $number): array
    {
        $snapshot = SourceSnapshot::latestRun()->first();

        $this->assertNotNull($snapshot, 'No profiling run stored — run `lab:profile` first.');

        $rows = $snapshot->profiling_results['queries'][(string) $number]['rows'] ?? null;

        $this->assertIsArray($rows, "Query {$number} is missing from the stored profiling run.");

        return $rows;
    }

    /** Questions at an exact `correct_count`, from query 3. */
    private function questionsAtCorrectCount(int $correctCount): int
    {
        foreach ($this->profilingQuery(3) as $row) {
            if ((int) $row['correct_count'] === $correctCount) {
                return (int) $row['questions'];
            }
        }

        return 0;
    }
}
