<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `MISSING_OPTIONS` (error) — a question with nothing to choose from.
 *
 * Counts **live** options: a question whose options were all soft-deleted is
 * as unanswerable as one that never had any, and the mirror keeps both
 * kinds of row, so only the live count answers "can this be sat today?".
 * 49 questions on the fixed snapshot, 27 of them active (query 2).
 */
final class MissingOptionsValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        if ($question->liveOptions() !== []) {
            return null;
        }

        return new Finding(
            ImportErrorCode::MISSING_OPTIONS,
            'questions',
            $question->sourceId,
            'Question has no live options.',
            $question->context() + ['options_total' => count($question->options)],
        );
    }
}
