<?php

namespace App\Console\Commands;

use App\Jobs\Import\BackfillAnswerKeyState;
use App\Jobs\Import\BackfillQuestionsCount;
use App\Jobs\Import\BackfillRequiresMediaReview;
use App\Jobs\Import\Bank\ImportCategories;
use App\Jobs\Import\Bank\ImportChapters;
use App\Jobs\Import\Bank\ImportCourses;
use App\Jobs\Import\Bank\ImportLectures;
use App\Jobs\Import\Bank\ImportMedia;
use App\Jobs\Import\Bank\ImportQuestionOptions;
use App\Jobs\Import\Bank\ImportQuestions;
use App\Jobs\Import\Bank\ImportQuizzes;
use App\Jobs\Import\Bank\ImportSections;
use App\Jobs\Import\Behaviour\ComputeItemStats;
use App\Jobs\Import\Behaviour\ComputeOptionStats;
use App\Jobs\Import\Behaviour\DeriveAttemptIndex;
use App\Jobs\Import\Behaviour\ImportResults;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Import\ImportErrorCode;
use App\Support\Import\ImportRunRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * `lab:import` — MySQL → PostgreSQL, under the copy allowlist (FR-022,
 * FR-029). Synchronous by default with a real exit code; `--queue`
 * dispatches the identical job classes to the `database` queue instead —
 * same jobs, same resume cursor, same upsert, only the dispatcher differs
 * (FR-029). `--kind=all` runs `bank`, `behaviour` then `backfill` as three
 * separate `import_runs` rows, because `kind` is a closed set of real
 * values (data-model.md §2) and "all" is a CLI convenience, never a stored
 * one.
 *
 * `jobClassesFor()` registers the Bank ETL, the Behavioural ETL and the
 * three backfill passes. `backfill` is a `--kind` rather than a command of
 * its own: `import_runs.kind` already carries it as a first-class value
 * (data-model.md §2), a backfill needs the same run row, counters and
 * inline/queue dispatch every other pass gets, and the plan's project
 * structure lists exactly two commands. A second command would have
 * duplicated all of that to reach three single-statement jobs.
 */
final class LabImport extends Command
{
    private const KINDS = ['bank', 'behaviour', 'backfill', 'all'];

    protected $signature = 'lab:import
        {--kind=all : Which tables to import: bank, behaviour, backfill, or all}
        {--resume : Continue the last run of this kind from its resume_cursor}
        {--chunk= : Rows per batch (defaults to config(lab.import.chunk_size))}
        {--queue : Dispatch the same job classes to the database queue instead of running inline}';

    protected $description = 'Import the bank and behavioural tables from the read-only injazedu source into the Lab mirror';

    public function handle(ImportRunRecorder $recorder): int
    {
        $kindOption = $this->option('kind');

        if (! in_array($kindOption, self::KINDS, true)) {
            $this->error("Unknown --kind [{$kindOption}]. Expected one of: ".implode(', ', self::KINDS));

            return self::FAILURE;
        }

        $snapshot = SourceSnapshot::latestRun()->first();

        if ($snapshot === null) {
            $this->error('No source_snapshots row exists. Run `php artisan lab:profile` first.');

            return self::FAILURE;
        }

        $chunkSize = (int) ($this->option('chunk') ?? config('lab.import.chunk_size'));
        $ranVia = $this->option('queue') ? 'queue' : 'inline';
        $resuming = (bool) $this->option('resume');

        $runs = [];

        // Backfills last in `all`: each rewrites one mirror column from a
        // table the bank pass has to have finished writing first.
        foreach ($kindOption === 'all' ? ['bank', 'behaviour', 'backfill'] : [$kindOption] as $kind) {
            $run = $this->runKind($recorder, $kind, $snapshot->id, $ranVia, $resuming, $chunkSize);

            if ($run === null) {
                return self::FAILURE;
            }

            $runs[] = $run;
        }

        $this->table(
            ['Run #', 'Kind', 'Read', 'Inserted', 'Updated', 'Unchanged', 'Errors', 'Elapsed (s)', 'Status'],
            array_map(fn (ImportRun $run) => [
                $run->id, $run->kind, $run->rows_read, $run->rows_inserted,
                $run->rows_updated, $run->rows_unchanged, $run->error_count,
                $run->elapsed_seconds, $run->status,
            ], $runs)
        );

        return self::SUCCESS;
    }

    private function runKind(
        ImportRunRecorder $recorder,
        string $kind,
        int $snapshotId,
        string $ranVia,
        bool $resuming,
        int $chunkSize,
    ): ?ImportRun {
        $seedCursor = [];

        if ($resuming) {
            $previous = ImportRun::where('kind', $kind)->latest('id')->first();
            $seedCursor = $previous->resume_cursor ?? [];
        }

        $run = $recorder->start(
            snapshotId: $snapshotId,
            kind: $kind,
            ranVia: $ranVia,
            status: $resuming ? 'resumed' : 'running',
            resumeCursor: $seedCursor,
        );

        $this->info(sprintf(
            'Import run #%d started — kind=%s, ran_via=%s, chunk=%d%s',
            $run->id, $kind, $ranVia, $chunkSize, $resuming ? ', resuming' : ''
        ));

        try {
            foreach ($this->jobClassesFor($kind) as $jobClass) {
                $job = new $jobClass($run->id);

                // `onConnection()` goes on the JOB, not on the return of
                // `Bus::dispatch()`. For a ShouldQueue command that facade
                // returns the pushed job's id — an int — so chaining off it
                // fatals with "Call to a member function onConnection() on
                // int" and takes the whole run down. Only the global
                // `dispatch()` helper returns a chainable PendingDispatch;
                // the facade never has. Every job here uses the `Queueable`
                // trait, which is where the real `onConnection()` lives.
                $this->option('queue')
                    ? Bus::dispatch($job->onConnection('database'))
                    : Bus::dispatchSync($job);
            }

            $recorder->finish('completed');
        } catch (Throwable $e) {
            $recorder->finish('failed');
            $this->error("Import run #{$run->id} failed: {$e->getMessage()}");

            return null;
        }

        // Each dispatched job re-fetches its own ImportRun (App\Jobs\Import\Bank\BankImportJob::handle())
        // and increments counters on that separate instance — correct in
        // the database (Eloquent only writes dirty attributes), but this
        // command's own $run object never saw those writes. Refresh before
        // display.
        return $recorder->run()->refresh();
    }

    /**
     * @return list<class-string>
     */
    private function jobClassesFor(string $kind): array
    {
        return match ($kind) {
            // Mandatory order — key dependencies, not preference (plan.md
            // "Within E"): categories → courses → chapters → lectures →
            // quizzes → sections → questions → options → quiz_files.
            'bank' => [
                ImportCategories::class,
                ImportCourses::class,
                ImportChapters::class,
                ImportLectures::class,
                ImportQuizzes::class,
                ImportSections::class,
                ImportQuestions::class,
                ImportQuestionOptions::class,
                ImportMedia::class,
            ],
            // ImportResults before DeriveAttemptIndex — the window function
            // needs every source_results row to exist first. The two stats
            // jobs read the source directly, not the mirror, so they have no
            // ordering dependency on either; they run last by convention
            // (ADR-022, data-model.md §2).
            'behaviour' => [
                ImportResults::class,
                DeriveAttemptIndex::class,
                ComputeItemStats::class,
                ComputeOptionStats::class,
            ],
            // Second passes over the mirror, each rewriting one column that
            // could not be derived at copy time because the mandatory bank
            // order puts the table it depends on later (FR-013, FR-034,
            // FR-061). Order between them is free — they touch three
            // different columns and none reads what another writes.
            'backfill' => [
                BackfillQuestionsCount::class,
                BackfillRequiresMediaReview::class,
                BackfillAnswerKeyState::class,
            ],
        };
    }

    public function getHelp(): string
    {
        $lines = [
            'lab:import copies the bank and behavioural tables from the read-only injazedu',
            'MySQL source into this Lab\'s PostgreSQL mirror. It is idempotent and resumable:',
            're-running it is always safe, and every write goes through the same upsert',
            'regardless of how it was invoked.',
            '',
            'Flags:',
            '  --kind=bank|behaviour|backfill|all',
            '                             Which tables to import. "all" runs bank, then',
            '                             behaviour, then backfill as three separate',
            '                             import_runs rows. "backfill" re-derives the three',
            '                             columns that depend on a table the bank pass',
            '                             writes later: source_sections.questions_count,',
            '                             source_questions.requires_media_review and',
            '                             source_questions.answer_key_state. It reads no',
            '                             MySQL and touches one column per pass.',
            '  --resume                   Continue the last run of this --kind from its',
            '                             recorded resume_cursor (table => last confirmed',
            '                             source_id) instead of starting over. Never',
            '                             duplicates a row and never drops one.',
            '  --chunk=N                  Rows per batch, for the ~13.8M-row behavioural',
            '                             tables. Defaults to config(lab.import.chunk_size).',
            '  --queue                    Dispatch the same job classes to the database',
            '                             queue instead of running them inline. Same jobs,',
            '                             same resume cursor, same upsert — only the',
            '                             dispatcher differs.',
            '',
            'Exit code 0 means completion; non-zero means failure. That is the run\'s only',
            'completion signal — no separate report is written (FR-029, FR-030).',
        ];

        if (enum_exists(ImportErrorCode::class)) {
            $lines[] = '';
            $lines[] = 'Error codes (import_errors.code):';

            foreach (ImportErrorCode::cases() as $case) {
                $lines[] = sprintf('  %-24s %s', $case->value, $case->description());
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
