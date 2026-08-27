<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `ORPHAN_QUIZ` (error) — the section points at a `quiz_id` that does not
 * exist, so every question under it is unreachable.
 *
 * Same shape as {@see OrphanSectionValidator}: the known-quiz set is read
 * once per pass, and a soft-deleted quiz counts as existing.
 *
 * Zero rows on the fixed snapshot.
 */
final class OrphanQuizValidator implements SectionCheck
{
    /** @param  array<int, true>  $knownQuizIds  keyed by id for O(1) lookup */
    public function __construct(private readonly array $knownQuizIds) {}

    public function check(SectionUnderImport $section): ?Finding
    {
        // A NULL quiz_id is not an orphan — `source_quizzes` allows it and
        // data-model.md §2 gives it a meaning of its own.
        if ($section->quizSourceId === null || isset($this->knownQuizIds[$section->quizSourceId])) {
            return null;
        }

        return new Finding(
            ImportErrorCode::ORPHAN_QUIZ,
            'sections',
            $section->sourceId,
            'Section points at a quiz that does not exist.',
            $section->context() + ['quiz_source_id' => $section->quizSourceId],
        );
    }
}
