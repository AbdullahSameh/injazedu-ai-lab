<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `QUESTION_NO_SECTION` (error) — the question has no `section_id`, so it
 * belongs to no quiz and no student can ever be shown it.
 *
 * Distinct from `ORPHAN_SECTION`, which is a question pointing somewhere
 * that no longer exists. Both leave the question unreachable; only these two
 * codes together say which way it happened.
 *
 * Zero rows on the fixed snapshot.
 */
final class QuestionNoSectionValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        if ($question->sectionSourceId !== null) {
            return null;
        }

        return new Finding(
            ImportErrorCode::QUESTION_NO_SECTION,
            'questions',
            $question->sourceId,
            'Question has no section_id — it belongs to no quiz.',
            $question->context(),
        );
    }
}
