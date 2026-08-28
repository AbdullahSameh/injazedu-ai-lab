<?php

namespace App\Console\Commands;

use App\Models\DuplicateEvalPair;
use App\Models\ImportRun;
use App\Models\SourceSnapshot;
use App\Support\Import\ImportErrorCode;
use App\Support\Import\ImportRunRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * `lab:dedup` — the eleven-step P2 pipeline (FR-102, FR-103), following
 * `LabImport`'s shape: one command, a step selector, `--resume`, a chunk
 * size, and the same run recorder / resume cursor / error recorder / batch
 * upsert P1 already built (FR-102 — reused unchanged).
 *
 * **This is the Phase 1/2 skeleton.** `JOB_CLASSES_FOR_STEP` is deliberately
 * empty for every step today: each later phase wires its own steps in
 * (`--step=derive-text` in T036, `--step=hash-cluster` in T041, and so on)
 * — see the per-step comment for the task that will fill it in. Running an
 * unwired step is reported honestly rather than silently doing nothing.
 *
 * `SourceSnapshot::latestRun()` resolution and `ran_via` recording follow
 * `LabImport` exactly (notes.md N6): both `import_runs` columns are
 * NOT NULL with no default, and failing with a clear message beats a
 * constraint violation.
 */
final class LabDedup extends Command
{
    /** FR-103: the eleven steps, in dependency order. */
    private const STEPS = [
        'derive-text', 'hash-cluster', 'embed', 'candidates',
        'eval-sample', 'eval-report', 'calibrate', 'verdict',
        'auto-cluster', 'conflict-report', 'benchmark-embedders',
    ];

    /**
     * The unconditional run order for `lab:dedup` with no `--step`.
     * `benchmark-embedders` is excluded — FR-103 states it "runs only on a
     * failed gate," so it is never part of the default sequential run and
     * must always be requested explicitly via `--step=benchmark-embedders`.
     */
    private const DEFAULT_RUN_STEPS = [
        'derive-text', 'hash-cluster', 'embed', 'candidates',
        'eval-sample', 'eval-report', 'calibrate', 'verdict',
        'auto-cluster', 'conflict-report',
    ];

    /** `import_runs.kind` for each step — the `p2_` prefix distinguishes these from P1's `bank`/`behaviour`/`backfill`. */
    private const KIND_FOR_STEP = [
        'derive-text' => 'p2_derive_text',
        'hash-cluster' => 'p2_hash_cluster',
        'embed' => 'p2_embed',
        'candidates' => 'p2_candidates',
        'eval-sample' => 'p2_eval_sample',
        'eval-report' => 'p2_eval_report',
        'calibrate' => 'p2_calibrate',
        'verdict' => 'p2_verdict',
        'auto-cluster' => 'p2_auto_cluster',
        'conflict-report' => 'p2_conflict_report',
        'benchmark-embedders' => 'p2_benchmark_embedders',
    ];

    protected $signature = 'lab:dedup
        {--step= : Run one step: derive-text|hash-cluster|embed|candidates|eval-sample|eval-report|calibrate|verdict|auto-cluster|conflict-report|benchmark-embedders}
        {--resume : Continue the given step from its recorded resume_cursor}
        {--chunk= : Rows per batch (defaults to config(lab.dedup.chunk_size))}
        {--count= : Row/pair count limit, for steps that accept one}';

    protected $description = 'Run the P2 duplicate-intelligence pipeline: text derivation, hash clustering, embeddings, candidate generation, calibration, verdicts, and the conflict backlog';

    public function handle(ImportRunRecorder $recorder): int
    {
        $stepOption = $this->option('step');

        if ($stepOption !== null && ! in_array($stepOption, self::STEPS, true)) {
            $this->error("Unknown --step [{$stepOption}]. Expected one of: ".implode(', ', self::STEPS));

            return self::FAILURE;
        }

        $snapshot = SourceSnapshot::latestRun()->first();

        if ($snapshot === null) {
            $this->error('No source_snapshots row exists. Run `php artisan lab:profile` first.');

            return self::FAILURE;
        }

        $ranVia = 'inline'; // lab:dedup exposes no --queue toggle; individual jobs may still dispatch to it internally.
        $resuming = (bool) $this->option('resume');

        if ($stepOption !== null) {
            $run = $this->runStep($recorder, $stepOption, $snapshot->id, $ranVia, $resuming);

            return $run === null ? self::FAILURE : self::SUCCESS;
        }

        // FR-104: with no --step, run the unconditional steps in dependency
        // order and stop with a clear message at the first step whose
        // human input is missing — it must never calibrate against absent
        // labels. benchmark-embedders is never in this loop (see above).
        foreach (self::DEFAULT_RUN_STEPS as $step) {
            if ($step === 'calibrate' && ! $this->calibrationLabelsReady()) {
                $this->info(
                    "Stopping before 'calibrate': wave 1 calibration labels are not complete yet ".
                    "(human gate A). Run --step=eval-sample, label the wave through the screen, ".
                    'then re-run --step=calibrate.'
                );

                return self::SUCCESS;
            }

            if ($this->jobClassesFor($step) === []) {
                $this->info("Stopping at '{$step}': not implemented yet.");

                return self::SUCCESS;
            }

            if ($this->runStep($recorder, $step, $snapshot->id, $ranVia, $resuming) === null) {
                return self::FAILURE;
            }
        }

        $this->info('All unconditional steps completed.');

        return self::SUCCESS;
    }

    private function runStep(
        ImportRunRecorder $recorder,
        string $step,
        int $snapshotId,
        string $ranVia,
        bool $resuming,
    ): ?ImportRun {
        // FR-104 holds regardless of how the step was reached — an explicit
        // `--step=calibrate` is refused exactly like the default run stops.
        if ($step === 'calibrate' && ! $this->calibrationLabelsReady()) {
            $this->error(
                "Cannot run 'calibrate': wave 1 calibration labels are not complete yet (human gate A)."
            );

            return null;
        }

        $jobClasses = $this->jobClassesFor($step);

        if ($jobClasses === []) {
            $this->error("Step '{$step}' has no job classes wired yet.");

            return null;
        }

        $kind = self::KIND_FOR_STEP[$step];

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
            'Dedup run #%d started — step=%s, kind=%s%s',
            $run->id, $step, $kind, $resuming ? ', resuming' : ''
        ));

        try {
            foreach ($jobClasses as $jobClass) {
                Bus::dispatchSync(new $jobClass($run->id));
            }

            $recorder->finish('completed');
        } catch (Throwable $e) {
            $recorder->finish('failed');
            $this->error("Dedup run #{$run->id} failed: {$e->getMessage()}");

            return null;
        }

        return $recorder->run()->refresh();
    }

    /**
     * @return list<class-string>
     */
    private function jobClassesFor(string $step): array
    {
        return match ($step) {
            // Wired in Phase 3, T036/T041.
            'derive-text', 'hash-cluster' => [],
            // Wired in Phase 5, T053/T065.
            'embed', 'candidates' => [],
            // Wired in Phase 6, T070/T080.
            'eval-sample', 'eval-report' => [],
            // Wired in Phase 6/7, T077.
            'calibrate' => [],
            // Wired in Phase 7, T095.
            'verdict' => [],
            // Wired in Phase 7, T102.
            'auto-cluster' => [],
            // Wired in Phase 8, T110.
            'conflict-report' => [],
            // Wired in Phase 6, T085 — conditional on a failed gate only.
            'benchmark-embedders' => [],
        };
    }

    /**
     * FR-104: the one precondition this skeleton already enforces. Wave 1
     * must be fully labelled (every `label_round = 1` calibration pair
     * carries a `human_relation`) before calibration may run at all —
     * calibrating against a mix of labelled and absent rows would silently
     * corrupt the gate.
     */
    private function calibrationLabelsReady(): bool
    {
        $wave1 = DuplicateEvalPair::query()
            ->where('purpose', 'calibration')
            ->where('label_round', 1);

        return (clone $wave1)->exists() && (clone $wave1)->whereNull('human_relation')->doesntExist();
    }

    public function getHelp(): string
    {
        $lines = [
            'lab:dedup runs the P2 duplicate-intelligence pipeline over the Lab mirror.',
            'Zero rows are written to injazedu; every step writes only the eight P2 tables.',
            '',
            'Flags:',
            '  --step=STEP   Run one step: '.implode('|', self::STEPS),
            '                             With no --step, runs the unconditional steps in',
            '                             dependency order and stops at the first step whose',
            '                             human input is missing or that is not implemented',
            '                             yet. benchmark-embedders always needs --step, even',
            '                             in a full run — it fires only on a failed gate.',
            '  --resume                   Continue the given step from its recorded',
            '                             resume_cursor instead of starting over.',
            '  --chunk=N                  Rows per batch. Defaults to config(lab.dedup.chunk_size).',
            '  --count=N                  Row/pair count limit, for steps that accept one.',
            '',
            'Exit code 0 means completion (including a clean stop before a step whose input',
            'is missing); non-zero means failure.',
        ];

        if (enum_exists(ImportErrorCode::class)) {
            $lines[] = '';
            $lines[] = 'Error codes this pipeline can add to import_errors:';
            $lines[] = sprintf('  %-24s %s', ImportErrorCode::EMBEDDING_TRUNCATED->value, ImportErrorCode::EMBEDDING_TRUNCATED->description());
            $lines[] = sprintf('  %-24s %s', ImportErrorCode::EMBEDDING_FAILED->value, ImportErrorCode::EMBEDDING_FAILED->description());
            $lines[] = sprintf('  %-24s %s', ImportErrorCode::VERDICT_FAILED->value, ImportErrorCode::VERDICT_FAILED->description());
        }

        return implode(PHP_EOL, $lines);
    }
}
