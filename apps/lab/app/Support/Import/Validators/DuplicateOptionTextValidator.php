<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `DUPLICATE_OPTION_TEXT` (warning) — two live options in one question read
 * identically, so the student is offered the same answer twice and at most
 * one of them can be the scored one.
 *
 * Compared on the trimmed text, because trailing whitespace is a typing
 * artefact and not a distinction a student can see. Blank options are
 * skipped rather than grouped: 336 options in the source have empty text,
 * and reporting every question that has two of them as a "duplicate" would
 * bury the real ones. An option with no text at all is a different problem
 * and not one of the thirteen.
 *
 * One finding per question, not per pair — the question is the thing a
 * reviewer opens. 419 questions on the fixed snapshot.
 */
final class DuplicateOptionTextValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        $seen = [];
        $duplicated = [];

        foreach ($question->liveOptions() as $option) {
            $text = trim((string) ($option['name'] ?? ''));

            if ($text === '') {
                continue;
            }

            if (isset($seen[$text])) {
                $duplicated[$text] = true;

                continue;
            }

            $seen[$text] = true;
        }

        if ($duplicated === []) {
            return null;
        }

        return new Finding(
            ImportErrorCode::DUPLICATE_OPTION_TEXT,
            'questions',
            $question->sourceId,
            sprintf('%d option text(s) appear more than once in this question.', count($duplicated)),
            $question->context() + ['duplicated_texts' => array_keys($duplicated)],
        );
    }
}
