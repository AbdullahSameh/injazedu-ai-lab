<?php

namespace App\Jobs\Import\Behaviour;

use App\Support\Derive\PayloadHasher;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportRunRecorder;
use App\Support\SourceReader;
use Illuminate\Support\Facades\DB;

/**
 * `question_result` + `results` -> `source_option_stats` (ADR-022, FR-040).
 * The distractor distribution: how often each option was chosen, and what
 * share of its question's answers that is.
 *
 * Driven by the mirror's own `source_question_options`, LEFT JOINed to the
 * pushdown aggregate rather than built from the aggregate alone. 45,840 of
 * 124,549 options (37%) were never chosen by anyone; a plain GROUP BY over
 * the answer table returns only the 78,709 that were, and silently drops
 * exactly the rows that "a distractor chosen by under 2% is a dead
 * distractor" exists to surface. A never-chosen option belongs here with
 * `chosen_n = 0`.
 *
 * The denominator is the sum of the question's own option counts, which is
 * identical to its answer count: `question_result.option_id` is NOT NULL,
 * so every answer row selects exactly one option (notes N3).
 */
final class ComputeOptionStats extends BehaviourStatsJob
{
    public function handle(SourceReader $source, BatchUpsert $upsert): void
    {
        $run = $this->guardedRun($source);
        $recorder = ImportRunRecorder::for($run);
        $hasher = new PayloadHasher;
        $computedAt = now();

        foreach (self::SCOPES as $scope) {
            [$chosen, $questionTotals] = $this->aggregate($scope);
            $batch = [];

            // Streamed, not collected: 124,549 options, and holding their
            // attribute arrays alongside the aggregate maps is what pushes
            // this past the default memory limit.
            $options = DB::connection('pgsql')
                ->table('source_question_options')
                ->select(['source_id', 'question_source_id', 'is_correct_derived'])
                ->orderBy('source_id')
                ->cursor();

            foreach ($options as $option) {
                $chosenN = $chosen[$option->source_id] ?? 0;
                $total = $questionTotals[$option->question_source_id] ?? 0;

                $content = [
                    'chosen_n' => $chosenN,
                    'chosen_share' => $total > 0 ? $this->stat($chosenN / $total) : null,
                    'is_key' => (bool) $option->is_correct_derived,
                ];

                $batch[] = $content + [
                    'question_source_id' => $option->question_source_id,
                    'option_source_id' => $option->source_id,
                    'scope' => $scope,
                    'computed_at' => $computedAt,
                    'import_run_id' => $run->id,
                    'snapshot_id' => $run->snapshot_id,
                    'stats_hash' => $hasher->hash($content),
                ];

                if (count($batch) >= self::FLUSH_SIZE) {
                    $this->flush($upsert, $recorder, $batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->flush($upsert, $recorder, $batch);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function flush(BatchUpsert $upsert, ImportRunRecorder $recorder, array $batch): void
    {
        $recorder->recordRead(count($batch));

        $outcome = $upsert->runDerived(
            'source_option_stats', $batch, ['option_source_id', 'scope'], 'stats_hash'
        );
        $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, int>} chosen-per-option, total-per-question
     */
    private function aggregate(string $scope): array
    {
        $sql = sprintf(<<<'SQL'
            SELECT qr.question_id AS question_id, qr.option_id AS option_id, COUNT(*) AS chosen_n
            FROM question_result qr
            JOIN results r ON r.id = qr.result_id
            %s
            GROUP BY qr.question_id, qr.option_id
        SQL, $this->scopePredicate($scope));

        $chosen = [];
        $questionTotals = [];

        foreach (DB::connection('injazedu')->select($sql) as $row) {
            $chosen[(int) $row->option_id] = (int) $row->chosen_n;
            $questionTotals[(int) $row->question_id] =
                ($questionTotals[(int) $row->question_id] ?? 0) + (int) $row->chosen_n;
        }

        return [$chosen, $questionTotals];
    }
}
