<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `STIMULUS_NO_QUESTIONS` (warning) — a section carries shared reading text
 * that no live question uses, so the passage is written and shown to nobody.
 *
 * `ImportSections` runs **before** `ImportQuestions` in the mandatory bank
 * order, so the set of sections that do have live questions cannot come from
 * the mirror; it is read from the source in one query at the start of the
 * pass. That also makes the check correct under `--resume`, where the mirror
 * holds only part of the bank.
 *
 * Zero rows on the fixed snapshot — and it cannot be otherwise there, since
 * no section has a `description` at all (query 12). The check is written for
 * the source's shape, not for this snapshot's contents.
 */
final class StimulusNoQuestionsValidator implements SectionCheck
{
    /** @param  array<int, true>  $sectionIdsWithLiveQuestions  keyed by section id */
    public function __construct(private readonly array $sectionIdsWithLiveQuestions) {}

    public function check(SectionUnderImport $section): ?Finding
    {
        if (! $section->hasStimulus() || isset($this->sectionIdsWithLiveQuestions[$section->sourceId])) {
            return null;
        }

        return new Finding(
            ImportErrorCode::STIMULUS_NO_QUESTIONS,
            'sections',
            $section->sourceId,
            'Section has shared stimulus text but no live questions using it.',
            $section->context() + ['stimulus_length' => mb_strlen((string) $section->stimulusRaw)],
        );
    }
}
