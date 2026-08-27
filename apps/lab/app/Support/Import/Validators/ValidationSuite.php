<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * The thirteen checks of FR-042, assembled in one place so that "thirteen"
 * is something a reader can count and a test can assert, rather than a claim
 * spread across three jobs.
 *
 * Each factory takes the pass-scoped lookups its checks need. Those are read
 * **once per pass, from the source**, never per row and never from the
 * mirror: `ImportCategories` is the first bank job and `ImportSections` runs
 * before `ImportQuestions`, so for two of the three the mirror does not yet
 * hold the answer — and under `--resume` it holds only part of it.
 *
 * Where a check runs is decided by where its subject is fully known, not by
 * which table the anomaly is nominally about. `DUPLICATE_OPTION_TEXT` and
 * `OPTION_ORDER_TIE` are option-level defects, but they are properties of a
 * question's option **set**, and `ImportQuestions` is the pass that holds
 * that set — so they run there, and their findings are filed against the
 * question, which is the row a reviewer would open.
 */
final class ValidationSuite
{
    /**
     * The ten checks whose subject is a question and its options.
     *
     * @param  array<int, true>  $knownSectionIds
     * @return list<QuestionCheck>
     */
    public static function forQuestions(array $knownSectionIds): array
    {
        return [
            new MissingOptionsValidator,
            new EmptyStemValidator,
            new StemImageOnlyValidator,
            new ZeroCorrectValidator,
            new MultiCorrectValidator,
            new QuestionNoSectionValidator,
            new OrphanSectionValidator($knownSectionIds),
            new DuplicateOptionTextValidator,
            new OptionOrderTieValidator,
            new BrokenHtmlValidator,
        ];
    }

    /**
     * The two checks whose subject is a section.
     *
     * @param  array<int, true>  $knownQuizIds
     * @param  array<int, true>  $sectionIdsWithLiveQuestions
     * @return list<SectionCheck>
     */
    public static function forSections(array $knownQuizIds, array $sectionIdsWithLiveQuestions): array
    {
        return [
            new OrphanQuizValidator($knownQuizIds),
            new StimulusNoQuestionsValidator($sectionIdsWithLiveQuestions),
        ];
    }

    /**
     * The one check whose subject is a category.
     *
     * @param  array<int, true>  $knownCategoryIds
     * @return list<CategoryCheck>
     */
    public static function forCategories(array $knownCategoryIds): array
    {
        return [
            new CategoryOrphanParentValidator($knownCategoryIds),
        ];
    }

    /**
     * Every code the suite can raise, for the test that pins the set against
     * {@see ImportErrorCode} — a check that exists but is wired nowhere is
     * invisible, and so is a code with no check behind it.
     *
     * @return list<ImportErrorCode>
     */
    public static function wiredCodes(): array
    {
        return [
            ImportErrorCode::MISSING_OPTIONS,
            ImportErrorCode::EMPTY_STEM,
            ImportErrorCode::STEM_IMAGE_ONLY,
            ImportErrorCode::ZERO_CORRECT,
            ImportErrorCode::MULTI_CORRECT,
            ImportErrorCode::QUESTION_NO_SECTION,
            ImportErrorCode::ORPHAN_SECTION,
            ImportErrorCode::DUPLICATE_OPTION_TEXT,
            ImportErrorCode::OPTION_ORDER_TIE,
            ImportErrorCode::BROKEN_HTML,
            ImportErrorCode::ORPHAN_QUIZ,
            ImportErrorCode::STIMULUS_NO_QUESTIONS,
            ImportErrorCode::CATEGORY_ORPHAN_PARENT,
        ];
    }
}
