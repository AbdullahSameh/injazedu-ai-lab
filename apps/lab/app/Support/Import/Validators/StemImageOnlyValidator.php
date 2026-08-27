<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `STEM_IMAGE_ONLY` (warning) — the question is a picture.
 *
 * Not an error: it is perfectly answerable by a student looking at it. It is
 * a warning because such a question cannot be read, searched, deduplicated
 * or embedded as text, so every later phase of this program is blind to it
 * and needs to know that in advance.
 *
 * Zero rows on the fixed 2026-08-07 snapshot — query 9 found no `<img` in
 * any stem. The check exists because that is a fact about this snapshot, not
 * a property of the source.
 */
final class StemImageOnlyValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        if (! $question->hasImage() || $question->strippedText() !== '') {
            return null;
        }

        return new Finding(
            ImportErrorCode::STEM_IMAGE_ONLY,
            'questions',
            $question->sourceId,
            'Question text is an image with no words.',
            $question->context(),
        );
    }
}
