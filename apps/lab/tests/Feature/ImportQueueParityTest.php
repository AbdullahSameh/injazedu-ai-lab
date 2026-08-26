<?php

namespace Tests\Feature;

use App\Jobs\Import\Bank\ImportSections;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Import\ResumeCursor;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * FR-029 / SC-019: `--queue` changes the dispatcher, never the work. The
 * same input through the inline path and through the queue must produce the
 * same mirror, and both must record how they ran.
 *
 * This is not a formality. The two paths are one line apart in `LabImport`,
 * and that line was wrong: `Bus::dispatch($job)->onConnection('database')`
 * chains off a facade that returns the pushed job's **id**, so every
 * `--queue` run died with "Call to a member function onConnection() on int"
 * before a single row moved. Nine months of `ran_via = 'queue'` rows would
 * have recorded runs that never happened. A test that only asserted the
 * column was populated would have passed throughout.
 *
 * So the queue path here is the real one: the job is serialised into the
 * `jobs` table, popped off it, unserialised and fired. Only the worker
 * *process* is missing — the test fires the job in-process instead, which
 * keeps it inside the test's own transaction and deterministic. That
 * matters twice over: a live `queue:work` daemon polls this same table on
 * this machine, and an uncommitted job row is invisible to it, so the test
 * cannot race the daemon and the daemon cannot swallow the test's job.
 *
 * Both passes import the same fixed tail of `source_sections` into a mirror
 * the pass itself emptied, inside a transaction that is always rolled back.
 * Comparing `source_id => payload_hash` compares content, not row counts:
 * two paths can agree on how many rows they wrote and disagree on what is
 * in them.
 */
class ImportQueueParityTest extends TestCase
{
    /** Leaves 3,000 sections to import — three batches through either path. */
    private const CUT = 316;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.pgsql.database' => 'injazedu_lab',
            // The database queue driver reads `jobs` from the default
            // connection. phpunit.xml makes that sqlite :memory:, which has
            // no such table — point both at the Lab.
            'database.default' => 'pgsql',
            'queue.connections.database.connection' => 'pgsql',
        ]);

        DB::purge('pgsql');
    }

    public function test_the_inline_and_queued_paths_produce_the_same_mirror(): void
    {
        $inline = $this->importTailVia('inline');
        $queued = $this->importTailVia('queue');

        $this->assertNotEmpty($inline['rows'], 'The inline pass imported nothing.');
        $this->assertSame(
            $inline['rows'],
            $queued['rows'],
            'The queued path produced a different mirror from the inline path.'
        );

        // Every row in the mirror was put there by the pass under test, so
        // neither path can pass by leaving rows it never wrote.
        $this->assertSame(count($inline['rows']), $inline['inserted']);
        $this->assertSame(count($queued['rows']), $queued['inserted']);
        $this->assertSame($inline['inserted'], $queued['inserted']);
        $this->assertSame(0, $inline['errors']);
        $this->assertSame(0, $queued['errors']);

        // FR-029: the run row must say which path it took, either way.
        $this->assertSame('inline', $inline['ran_via']);
        $this->assertSame('queue', $queued['ran_via']);
    }

    /**
     * The parity test above dispatches the job itself, so it proves the two
     * paths do the same work — but it would still pass if `lab:import
     * --queue` could not dispatch at all, which is exactly the bug that was
     * there. This runs the command, so the command's own dispatch line is
     * under test.
     *
     * `backfill` is the kind used because it is three sub-second statements
     * with no MySQL read, and `questions_count` can be knocked out first so
     * the queued jobs have real work to do — a pass that never ran would
     * leave the column at zero and be indistinguishable from one that ran
     * and found nothing to change.
     */
    public function test_the_command_dispatches_to_the_queue_and_the_work_actually_happens(): void
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            // Only the sections that actually hold live questions can come
            // back non-zero. The 228 that genuinely count zero are re-derived
            // as zero and correctly not written — `IS DISTINCT FROM` is doing
            // its job, so they must not be expected here.
            $expected = (int) $connection->table('source_sections')->where('questions_count', '>', 0)->count();
            $connection->table('source_sections')->update(['questions_count' => 0]);

            $this->artisan('lab:import', ['--kind' => 'backfill', '--queue' => true])->assertExitCode(0);

            $run = ImportRun::where('kind', 'backfill')->latest('id')->firstOrFail();
            $this->assertSame('queue', $run->ran_via);
            $this->assertSame('completed', $run->status, 'The command could not dispatch to the queue.');

            $this->drainDatabaseQueue();

            $run->refresh();
            $this->assertSame($expected, (int) $run->rows_updated, 'The queued backfill did not rewrite the column.');
            $this->assertSame(0, (int) $run->error_count);

            $this->assertSame(
                0,
                (int) $connection->table('source_sections')->where('questions_count', 0)
                    ->whereExists(fn ($q) => $q->from('source_questions')
                        ->whereColumn('section_source_id', 'source_sections.source_id')
                        ->whereNull('source_deleted_at'))
                    ->count(),
                'A section with live questions was left at zero.'
            );
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * Empty the tail, import it back through `$ranVia`, and return what
     * landed. Always rolls back — the mirror is left exactly as found.
     *
     * @return array{rows: array<int, string>, inserted: int, errors: int, ran_via: string}
     */
    private function importTailVia(string $ranVia): array
    {
        $connection = DB::connection('pgsql');
        $connection->beginTransaction();

        try {
            $connection->table('source_sections')->where('source_id', '>', self::CUT)->delete();

            $run = ImportRun::create([
                'snapshot_id' => SourceSnapshot::latestRun()->firstOrFail()->id,
                'kind' => 'bank',
                'started_at' => now(),
                'status' => 'running',
                'ran_via' => $ranVia,
                'resume_cursor' => [],
                'rows_read' => 0, 'rows_inserted' => 0, 'rows_updated' => 0,
                'rows_unchanged' => 0, 'error_count' => 0,
            ]);

            (new ResumeCursor($run))->confirm('sections', self::CUT);

            $job = new ImportSections($run->id);

            // The one line that differs between the paths — exactly as
            // `LabImport` dispatches it.
            if ($ranVia === 'queue') {
                Bus::dispatch($job->onConnection('database'));
                $this->drainDatabaseQueue();
            } else {
                Bus::dispatchSync($job);
            }

            $run->refresh();

            $rows = $connection->table('source_sections')
                ->where('source_id', '>', self::CUT)
                ->orderBy('source_id')
                ->pluck('payload_hash', 'source_id')
                ->all();

            return [
                'rows' => $rows,
                'inserted' => (int) $run->rows_inserted,
                'errors' => (int) $run->error_count,
                'ran_via' => $run->ran_via,
            ];
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * Pop and fire every job on the database queue, in this process. This is
     * what a worker does — minus the process, which is what keeps the test
     * inside its transaction and out of the live daemon's way.
     */
    private function drainDatabaseQueue(): void
    {
        $queue = Queue::connection('database');
        $fired = 0;

        while ($job = $queue->pop()) {
            $job->fire();
            $fired++;

            $this->assertLessThan(50, $fired, 'The queue is not draining — a job is being retried forever.');
        }

        $this->assertGreaterThan(0, $fired, 'Nothing was queued — the job never reached the jobs table.');
    }
}
