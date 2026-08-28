<?php

namespace App\Support\Dedup;

/**
 * FR-144, FR-145: the stopping rule for progressive calibration, kept out
 * of ThresholdCalibrator deliberately — it is the one place a progressive
 * design can silently become a weaker gate, so it must be testable
 * without a database row.
 *
 * Expand unless ALL FOUR of FR-144's conditions hold:
 *   1. every similarity decile and every quota is non-empty at the cumulative n
 *   2. inter-rater agreement on the doubled subsample is acceptable
 *   3. the positive class holds at least the configured floor (30 on arrival)
 *   4. the 95% Wilson LOWER bound of precision >= 0.90 AND of recall >= 0.70
 *
 * FR-145's decisive failure takes priority over all four: if the 95%
 * Wilson UPPER bound of precision is below 0.90, the gate is recorded
 * failed immediately, without labelling the remaining waves — a further
 * 300 labels cannot rescue a threshold whose upper bound already misses.
 */
final class CalibrationExpansionRule
{
    /** §17's own gate: precision >= 0.90 at recall >= 0.70 — not configurable. */
    private const PRECISION_GATE = 0.90;

    private const RECALL_GATE = 0.70;

    public function evaluate(
        bool $allStrataAndQuotasNonEmpty,
        bool $interRaterAgreementAcceptable,
        int $positiveClassCount,
        float $precisionLowerBound,
        float $precisionUpperBound,
        float $recallLowerBound,
        ?int $positiveClassFloor = null,
    ): string {
        if ($precisionUpperBound < self::PRECISION_GATE) {
            return 'stop_fail';
        }

        $positiveClassFloor ??= (int) config('lab.dedup.eval_positive_class_floor', 30);

        $allFourConditionsHold = $allStrataAndQuotasNonEmpty
            && $interRaterAgreementAcceptable
            && $positiveClassCount >= $positiveClassFloor
            && $precisionLowerBound >= self::PRECISION_GATE
            && $recallLowerBound >= self::RECALL_GATE;

        return $allFourConditionsHold ? 'stop_pass' : 'expand';
    }
}
