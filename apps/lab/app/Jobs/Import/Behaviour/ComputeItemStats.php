<?php

namespace App\Jobs\Import\Behaviour;

use App\Support\Derive\PayloadHasher;
use App\Support\Import\BatchUpsert;
use App\Support\Import\ImportRunRecorder;
use App\Support\SourceReader;
use Illuminate\Support\Facades\DB;

/**
 * `question_result` + `results` -> `source_item_stats` (ADR-022, FR-040).
 *
 * One GROUP BY per scope, pushed down into MySQL: ~13.8M rows in, ~28K
 * rows out, measured 4.9s. This is what replaces the raw `source_answers`
 * mirror, and the substitution is exact — cross-checked against that mirror
 * at max delta 0.0 on `p_value` across all 27,814 questions with data.
 *
 * The one trap, measured: MySQL quantizes to 4 decimal places in both
 * `AVG()` over an integer expression and in decimal division
 * (`div_precision_increment`), which put a 5e-5 error on 18,586 questions
 * and looks entirely plausible in the output. Every ratio and mean below
 * therefore casts to DOUBLE *before* the aggregate, never after.
 *
 * `r_pbis` is deliberately NOT computed here — it is P3's headline metric
 * and one arithmetic line over the columns this job stores, needing no raw
 * rows. P1 stores only what genuinely requires the (attempt x question)
 * grain and would be lost when `source_answers` is dropped.
 */
final class ComputeItemStats extends BehaviourStatsJob
{
    public function handle(SourceReader $source, BatchUpsert $upsert): void
    {
        $run = $this->guardedRun($source);
        $recorder = ImportRunRecorder::for($run);
        $hasher = new PayloadHasher;
        $computedAt = now();

        // Every question gets a row per scope, including the 1,328 with no
        // answer data at all — n = 0 says "measured, nothing there", an
        // absent row would say "never computed".
        $questionIds = DB::connection('pgsql')
            ->table('source_questions')
            ->orderBy('source_id')
            ->pluck('source_id')
            ->all();

        foreach (self::SCOPES as $scope) {
            $aggregate = $this->aggregate($scope);
            $batch = [];

            foreach ($questionIds as $questionId) {
                $a = $aggregate[$questionId] ?? null;

                $content = [
                    'n' => (int) ($a->n ?? 0),
                    'n_correct' => (int) ($a->n_correct ?? 0),
                    'p_value' => $a !== null ? $this->stat($a->p_value) : null,
                    'm1_corrected' => $this->stat($a?->m1),
                    'm0_corrected' => $this->stat($a?->m0),
                    'sd_corrected' => $this->stat($a?->sd),
                ];

                $batch[] = $content + [
                    'question_source_id' => $questionId,
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
            'source_item_stats', $batch, ['question_source_id', 'scope'], 'stats_hash'
        );
        $recorder->recordOutcomes($outcome['inserted'], $outcome['updated'], $outcome['unchanged']);
    }

    /**
     * @return array<int, object> keyed by question id
     */
    private function aggregate(string $scope): array
    {
        // Every ratio and mean casts to DOUBLE before aggregating — see the
        // class docblock. `total_points - points` is the CORRECTED total the
        // point-biserial requires (core plan §P3): without subtracting the
        // item's own score the coefficient inflates itself.
        $sql = sprintf(<<<'SQL'
            SELECT qr.question_id                                        AS question_id,
                   COUNT(*)                                              AS n,
                   SUM(qr.points > 0)                                    AS n_correct,
                   CAST(SUM(qr.points > 0) AS DOUBLE) / COUNT(*)         AS p_value,
                   AVG(CASE WHEN qr.points >  0
                            THEN CAST(r.total_points - qr.points AS DOUBLE) END) AS m1,
                   AVG(CASE WHEN qr.points <= 0
                            THEN CAST(r.total_points - qr.points AS DOUBLE) END) AS m0,
                   STDDEV_SAMP(CAST(r.total_points - qr.points AS DOUBLE))       AS sd
            FROM question_result qr
            JOIN results r ON r.id = qr.result_id
            %s
            GROUP BY qr.question_id
        SQL, $this->scopePredicate($scope));

        $keyed = [];

        foreach (DB::connection('injazedu')->select($sql) as $row) {
            $keyed[(int) $row->question_id] = $row;
        }

        return $keyed;
    }
}
