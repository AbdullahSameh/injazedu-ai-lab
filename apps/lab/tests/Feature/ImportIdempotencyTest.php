<?php

namespace Tests\Feature;

use App\Jobs\Import\BackfillAnswerKeyState;
use App\Jobs\Import\Behaviour\ImportResults;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Import\ResumeCursor;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * FR-024 / SC-007: running `lab:import` twice must leave the mirror exactly
 * as the first run left it.
 *
 * `BatchUpsertIdempotencyTest` (T059d) already pins the write path itself on
 * a scratch table. This is the end-to-end version: the real command, the
 * real jobs, the real mirror, the real MySQL snapshot. The two tests fail
 * for different reasons and both are needed — the write path can be
 * perfectly idempotent while a job feeds it a value that changes between
 * runs (a timestamp inside the hash, an unstable ordering, a re-derived
 * `student_ref`), and only a run-to-run comparison catches that.
 *
 * The snapshot is frozen at 2026-08-07 and is never refreshed, so "the same
 * input" is not an assumption here — it is a property of the source.
 *
 * Two shapes, deliberately:
 *
 *   - **the bank, through the command**, because that is what an operator
 *     actually types, and it exercises all nine jobs, the run recorder and
 *     the counters together. It is a no-op re-run over 174K rows and costs
 *     ~8s per pass.
 *   - **`source_results`, at the job level over a fixed tail slice**, because
 *     the full 1.1M-row pass costs ~65s and running it twice for the same
 *     evidence is not worth two minutes of suite time. The slice is deleted
 *     and re-imported inside a rolled-back transaction, so this covers the
 *     stronger case the bank test cannot reach: **insert, then unchanged**
 *     — not merely unchanged twice.
 */
class ImportIdempotencyTest extends TestCase
{
    /**
     * Nine bank tables, in the mandatory import order. Their combined row
     * count is what a no-op bank run must report as `rows_unchanged`.
     */
    private const BANK_TABLES = [
        'source_categories', 'source_courses', 'source_chapters', 'source_lectures',
        'source_quizzes', 'source_sections', 'source_questions',
        'source_question_options', 'source_media',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml points DB_DATABASE at :memory: for the sqlite default;
        // the Lab schema lives in the real pgsql database, so restore it.
        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_two_consecutive_bank_runs_write_nothing_and_agree(): void
    {
        $mirrored = $this->bankRowCount();

        $this->assertGreaterThan(0, $mirrored, 'The mirror is empty — run `lab:import --kind=bank` first.');

        $first = $this->runBankImport();
        $second = $this->runBankImport();

        foreach (['first' => $first, 'second' => $second] as $label => $run) {
            $this->assertSame('completed', $run->status, "The {$label} run did not complete.");
            $this->assertSame(0, (int) $run->rows_inserted, "The {$label} run inserted a row into a mirrored bank.");
            $this->assertSame(0, (int) $run->rows_updated, "The {$label} run rewrote a row that had not changed.");
            $this->assertSame(0, (int) $run->error_count, "The {$label} run recorded an error.");
            $this->assertSame($mirrored, (int) $run->rows_unchanged, "The {$label} run did not see the whole mirror.");
        }

        // The mirror is the same size it was before either run: nothing was
        // added, and nothing was quietly dropped and re-added.
        $this->assertSame($mirrored, $this->bankRowCount());
    }

    public function test_source_results_inserts_once_then_reports_unchanged(): void
    {
        $connection = DB::connection('pgsql');

        // The whole test runs inside one transaction and is rolled back:
        // it deletes real mirror rows to prove they come back correctly, and
        // a failure part-way must not leave the mirror short.
        $connection->beginTransaction();

        try {
            $cut = (int) $connection->table('source_results')->max('source_id') - 2000;
            $slice = (int) $connection->table('source_results')->where('source_id', '>', $cut)->count();

            $this->assertGreaterThan(0, $slice, 'No tail slice to test — is source_results populated?');

            $connection->table('source_results')->where('source_id', '>', $cut)->delete();

            // First pass: the slice is missing, so every row is an insert.
            $first = $this->runResultsFrom($cut);
            $this->assertSame($slice, (int) $first->rows_inserted, 'The tail slice did not come back in full.');
            $this->assertSame(0, (int) $first->rows_updated);
            $this->assertSame(0, (int) $first->rows_unchanged);

            // Second pass over the identical input: every row must be
            // recognised by its payload_hash and skipped without a write.
            $second = $this->runResultsFrom($cut);
            $this->assertSame(0, (int) $second->rows_inserted, 'A row was inserted twice.');
            $this->assertSame(0, (int) $second->rows_updated, 'An unchanged attempt was rewritten — a derived value is not stable across runs.');
            $this->assertSame($slice, (int) $second->rows_unchanged);
            $this->assertSame(0, (int) $second->error_count);

            $this->assertSame(
                $slice,
                (int) $connection->table('source_results')->where('source_id', '>', $cut)->count(),
                'The slice holds a different number of rows than it started with.'
            );
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * SC-020's second half: "re-running the backfill pass changes nothing."
     * The three passes rewrite one mirror column each and are the only
     * writes in this feature that are not upserts, so their idempotency
     * comes from `IS DISTINCT FROM` rather than from `BatchUpsert` — a
     * different mechanism, and it needs its own evidence.
     */
    public function test_the_backfill_passes_change_nothing_on_a_second_run(): void
    {
        $this->artisan('lab:import', ['--kind' => 'backfill'])->assertExitCode(0);
        $this->artisan('lab:import', ['--kind' => 'backfill'])->assertExitCode(0);

        $second = ImportRun::where('kind', 'backfill')->latest('id')->firstOrFail();

        $this->assertSame('completed', $second->status);
        $this->assertSame(0, (int) $second->rows_inserted, 'A backfill inserted a row.');
        $this->assertSame(0, (int) $second->rows_updated, 'A backfill rewrote a column that had not changed.');
        $this->assertSame(0, (int) $second->error_count);
        $this->assertGreaterThan(0, (int) $second->rows_unchanged);

        // SC-020's first half, on the live mirror: nothing active is pending.
        $this->assertSame(
            0,
            (int) DB::connection('pgsql')->table('source_questions')
                ->where('answer_key_state', 'pending')
                ->whereNull('source_deleted_at')
                ->count(),
            'An active question is still waiting on the answer-key decision.'
        );
    }

    /**
     * FR-061: the multi-key meaning is a domain decision, not a developer's.
     * With none recorded, the pass must refuse outright — a question leaving
     * `pending` has to be impossible without that answer, not merely
     * discouraged. The refusal is the guarantee; without it the default
     * would quietly become the decision.
     */
    public function test_the_answer_key_pass_refuses_to_run_without_a_recorded_decision(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            config(['lab.import.multi_key_policy' => null]);

            $connection->table('source_questions')->update(['answer_key_state' => 'pending']);

            $run = ImportRun::create([
                'snapshot_id' => SourceSnapshot::latestRun()->firstOrFail()->id,
                'kind' => 'backfill',
                'started_at' => now(),
                'status' => 'running',
                'ran_via' => 'inline',
                'resume_cursor' => [],
                'rows_read' => 0, 'rows_inserted' => 0, 'rows_updated' => 0,
                'rows_unchanged' => 0, 'error_count' => 0,
            ]);

            try {
                Bus::dispatchSync(new BackfillAnswerKeyState($run->id));
                $this->fail('The pass derived an answer-key state with no policy recorded.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('multi-key policy', $e->getMessage());
            }

            $this->assertSame(
                0,
                (int) $connection->table('source_questions')->where('answer_key_state', '!=', 'pending')->count(),
                'The refused pass still wrote a state.'
            );
        } finally {
            $connection->rollBack();
        }
    }

    private function bankRowCount(): int
    {
        return array_sum(array_map(
            fn (string $table): int => DB::connection('pgsql')->table($table)->count(),
            self::BANK_TABLES
        ));
    }

    private function runBankImport(): ImportRun
    {
        $this->artisan('lab:import', ['--kind' => 'bank'])->assertExitCode(0);

        return ImportRun::where('kind', 'bank')->latest('id')->firstOrFail();
    }

    /**
     * One `ImportResults` pass whose resume cursor starts at `$cut`, so it
     * reads only the tail — the same code path a full pass takes, over a
     * bounded set.
     */
    private function runResultsFrom(int $cut): ImportRun
    {
        $run = ImportRun::create([
            'snapshot_id' => SourceSnapshot::latestRun()->firstOrFail()->id,
            'kind' => 'behaviour',
            'started_at' => now(),
            'status' => 'running',
            'ran_via' => 'inline',
            'resume_cursor' => [],
            'rows_read' => 0, 'rows_inserted' => 0, 'rows_updated' => 0,
            'rows_unchanged' => 0, 'error_count' => 0,
        ]);

        (new ResumeCursor($run))->confirm('results', $cut);

        Bus::dispatchSync(new ImportResults($run->id));

        return $run->refresh();
    }
}
