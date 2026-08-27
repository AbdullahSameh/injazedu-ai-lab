<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `ZERO_CORRECT` (**error**) — the most serious code in the set, and the
 * reason severity exists at all (FR-043). No live option carries points, so
 * a student sitting this question today cannot get it right whatever they
 * choose.
 *
 * 31 active questions on the fixed snapshot, which must equal query 3's
 * `correct_count = 0` row exactly (FR-045, SC-013). A discrepancy is a defect
 * in the profiling run or in the mirror and blocks acceptance — it is not a
 * number to reconcile by adjusting this check.
 */
final class ZeroCorrectValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        $live = $question->liveOptions();

        // A question with no options at all earns this code **as well as**
        // MISSING_OPTIONS. Suppressing one to avoid "double reporting" would
        // be a judgement, and it would break FR-045: query 3 counts every
        // question whose `correct_count` is 0, the 49 optionless ones
        // included, and this count has to equal it exactly.
        if ($this->correctCount($live) !== 0) {
            return null;
        }

        return new Finding(
            ImportErrorCode::ZERO_CORRECT,
            'questions',
            $question->sourceId,
            'No live option carries points > 0 — the question has no correct answer.',
            $question->context() + ['options_count' => count($live)],
        );
    }

    /** @param  list<array<string, mixed>>  $options */
    private function correctCount(array $options): int
    {
        return count(array_filter($options, static fn (array $o): bool => ($o['points'] ?? 0) > 0));
    }
}
