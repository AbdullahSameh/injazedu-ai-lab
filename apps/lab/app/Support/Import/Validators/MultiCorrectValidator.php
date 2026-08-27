<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `MULTI_CORRECT` (warning) — more than one live option carries points.
 *
 * A warning rather than an error because the student can still answer and be
 * marked right. The operator recorded on 2026-08-26 that these are
 * data-entry errors and not a supported question type, which is what makes
 * the row worth a human's attention: 34 questions on the fixed snapshot, 33
 * with two correct options and one with four.
 *
 * Nothing is repaired. The row keeps every option exactly as the trainer
 * entered it, and `answer_key_state` records `multi_key` beside it.
 */
final class MultiCorrectValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        $correct = array_values(array_filter(
            $question->liveOptions(),
            static fn (array $o): bool => ($o['points'] ?? 0) > 0
        ));

        if (count($correct) <= 1) {
            return null;
        }

        return new Finding(
            ImportErrorCode::MULTI_CORRECT,
            'questions',
            $question->sourceId,
            sprintf('%d live options carry points > 0 — only one may.', count($correct)),
            $question->context() + [
                'correct_option_count' => count($correct),
                'correct_option_ids' => array_map(static fn (array $o): int => (int) $o['id'], $correct),
            ],
        );
    }
}
