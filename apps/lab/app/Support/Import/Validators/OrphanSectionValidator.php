<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `ORPHAN_SECTION` (error) — the question points at a `section_id` that does
 * not exist. There is no foreign key in the source, so nothing prevented it.
 *
 * The known-section set is read once per pass and passed in, because the
 * alternative — a lookup per question — is 29,142 queries to answer a
 * question that 3,316 ids already settle. Soft-deleted sections **count as
 * existing**: the row is there, the mirror copies it, and a question
 * pointing at it is not orphaned, merely pointing at something withdrawn.
 *
 * Zero rows on the fixed snapshot.
 */
final class OrphanSectionValidator implements QuestionCheck
{
    /** @param  array<int, true>  $knownSectionIds  keyed by id for O(1) lookup */
    public function __construct(private readonly array $knownSectionIds) {}

    public function check(QuestionUnderImport $question): ?Finding
    {
        // A NULL section_id is QUESTION_NO_SECTION's finding, not this one's.
        if ($question->sectionSourceId === null || isset($this->knownSectionIds[$question->sectionSourceId])) {
            return null;
        }

        return new Finding(
            ImportErrorCode::ORPHAN_SECTION,
            'questions',
            $question->sourceId,
            'Question points at a section that does not exist.',
            $question->context() + ['section_source_id' => $question->sectionSourceId],
        );
    }
}
