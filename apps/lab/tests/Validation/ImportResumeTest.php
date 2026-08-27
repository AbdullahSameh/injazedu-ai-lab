<?php

namespace Tests\Validation;

use App\Jobs\Import\Bank\ImportSections;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Import\ResumeCursor;
use App\Support\SourceReader;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * FR-025 / SC-008: a run that dies part-way must be completable, and
 * `--resume` must neither duplicate a row nor drop one.
 *
 * The interruption here is real, not simulated. A query listener on the
 * `pgsql` connection watches for `BatchUpsert`'s statements against
 * `source_sections` and throws the moment the **second** batch has been
 * written — after the INSERT commits its rows, before `BankImportJob` gets
 * to confirm the cursor for them.
 *
 * That timing is the point. Crashing between a committed batch and its
 * confirmed cursor is the state a real crash leaves behind, and it is the
 * only one that is dangerous: on resume those rows are read a second time.
 * If `--resume` merely skipped past the cursor it would be trivially
 * correct and useless; what makes it safe is that re-reading a committed
 * batch flows through the same `ON CONFLICT` upsert and lands as
 * `unchanged`. An interruption placed at a tidy batch boundary would never
 * exercise that.
 *
 * `source_sections` is the table under test: 3,316 rows is four batches at
 * `BatchUpsert::BATCH_SIZE`, enough for a crash to have a middle to happen
 * in, small enough that the whole test is seconds.
 *
 * Everything runs inside a transaction that is always rolled back. The test
 * deletes real mirror rows to create the gap, and a failure part-way must
 * not leave the mirror short.
 */
class ImportResumeTest extends TestCase
{
    /** Leaves 3,000 sections missing — three batches, so the crash has a middle. */
    private const CUT = 316;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_an_interrupted_pass_resumes_without_duplicating_or_dropping_a_row(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $before = $this->sectionFingerprint();
            $this->assertGreaterThan(3000, $before['count'], 'Too few sections mirrored to interrupt meaningfully.');

            $connection->table('source_sections')->where('source_id', '>', self::CUT)->delete();

            $interrupted = $this->newRun();
            (new ResumeCursor($interrupted))->confirm('sections', self::CUT);

            $this->interruptAfterSecondBatch();

            try {
                Bus::dispatchSync(new ImportSections($interrupted->id));
                $this->fail('The import was not interrupted — the listener never fired.');
            } catch (RuntimeException $e) {
                $this->assertSame('crash', $e->getMessage());
            }

            $interrupted->refresh();
            $partial = (int) $connection->table('source_sections')->count();
            $confirmed = (int) $interrupted->resume_cursor['sections'];

            // Two batches were written; only the first was confirmed. The
            // gap between them is what resume has to handle.
            $this->assertGreaterThan(self::CUT, $partial, 'Nothing was written before the crash.');
            $this->assertLessThan($before['count'], $partial, 'The crash happened after the pass had already finished.');
            $this->assertGreaterThan(self::CUT, $confirmed, 'The cursor never advanced.');

            // The rows the crash left behind unconfirmed. Resume will read
            // them again, and they must land as `unchanged`, not as
            // duplicates — that is the property this whole test exists for.
            $unconfirmed = (int) $connection->table('source_sections')
                ->where('source_id', '>', $confirmed)->count();

            $this->assertGreaterThan(
                0,
                $unconfirmed,
                'The crash landed on a batch boundary, not inside one — resume never re-reads a written row.'
            );

            // Resume through the command: `--resume` seeds a new run from the
            // last run of this kind, which is the interrupted one.
            $this->artisan('lab:import', ['--kind' => 'bank', '--resume' => true])->assertExitCode(0);

            $resumed = ImportRun::where('kind', 'bank')->latest('id')->firstOrFail();
            $this->assertSame('completed', $resumed->status);
            // Not zero: a bank pass runs the thirteen checks and the bank
            // really does hold ~29K anomalies. What matters here is that
            // resuming did not fail, which `status` already says.
            $this->assertGreaterThan(0, (int) $resumed->error_count);

            // The sharpest evidence that the cursor was actually honoured:
            // the resumed run inserts exactly the rows the crash never got
            // to, and not one more. `status` cannot carry this — a run that
            // started as `resumed` finishes as `completed` like any other,
            // so the counters are the record of what resuming did.
            $this->assertSame(
                $before['count'] - $partial,
                (int) $resumed->rows_inserted,
                'The resumed run inserted a different number of rows than the crash left missing.'
            );

            $after = $this->sectionFingerprint();

            // Count alone would pass while holding the wrong rows, so the id
            // sum goes with it — together they pin size and membership.
            $this->assertSame($before['count'], $after['count'], 'Rows are missing or duplicated after resume.');
            $this->assertSame($before['sum'], $after['sum'], 'Same count, different rows.');
            $this->assertSame($before['distinct'], $after['distinct'], 'A source_id appears twice.');
            $this->assertSame($after['count'], $after['distinct'], 'A source_id appears twice.');

            // And the resumed mirror still matches the source it came from.
            $source = app(SourceReader::class)->table('sections')
                ->selectRaw('count(*) AS c, COALESCE(sum(id), 0) AS s')->first();

            $this->assertSame((int) $source->c, $after['count']);
            $this->assertSame((string) $source->s, $after['sum']);
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * Throw once, immediately after the second `source_sections` batch has
     * been written and before its cursor is confirmed. The listener disarms
     * itself so the resume pass runs clean.
     */
    private function interruptAfterSecondBatch(): void
    {
        $armed = true;
        $batches = 0;

        DB::connection('pgsql')->listen(function ($query) use (&$armed, &$batches): void {
            if (! $armed || ! str_contains($query->sql, 'INSERT INTO source_sections')) {
                return;
            }

            if (++$batches < 2) {
                return;
            }

            $armed = false;

            throw new RuntimeException('crash');
        });
    }

    private function newRun(): ImportRun
    {
        return ImportRun::create([
            'snapshot_id' => SourceSnapshot::latestRun()->firstOrFail()->id,
            'kind' => 'bank',
            'started_at' => now(),
            'status' => 'running',
            'ran_via' => 'inline',
            'resume_cursor' => [],
            'rows_read' => 0, 'rows_inserted' => 0, 'rows_updated' => 0,
            'rows_unchanged' => 0, 'error_count' => 0,
        ]);
    }

    /**
     * @return array{count: int, sum: string, distinct: int}
     */
    private function sectionFingerprint(): array
    {
        $row = DB::connection('pgsql')->selectOne(
            'SELECT count(*) AS c, COALESCE(sum(source_id), 0) AS s, count(DISTINCT source_id) AS d
             FROM source_sections'
        );

        return ['count' => (int) $row->c, 'sum' => (string) $row->s, 'distinct' => (int) $row->d];
    }
}
