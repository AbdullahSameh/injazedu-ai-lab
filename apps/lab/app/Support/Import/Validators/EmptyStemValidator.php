<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `EMPTY_STEM` (error) — nothing to read.
 *
 * Deliberately exclusive with `STEM_IMAGE_ONLY`: both start from a stem that
 * strips to nothing, and the image is what separates "the question is a
 * picture" from "the question is blank". Letting a row earn both codes would
 * make the two counts overlap and neither answer its own question.
 */
final class EmptyStemValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        if ($question->strippedText() !== '' || $question->hasImage()) {
            return null;
        }

        return new Finding(
            ImportErrorCode::EMPTY_STEM,
            'questions',
            $question->sourceId,
            'Question text is empty after stripping tags, and carries no image.',
            $question->context() + ['raw_length' => mb_strlen((string) $question->rawText)],
        );
    }
}
