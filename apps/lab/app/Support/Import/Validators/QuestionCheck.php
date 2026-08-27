<?php

namespace App\Support\Import\Validators;

/**
 * A check over one question and its options. Returns the finding or null —
 * never throws, never writes, never changes the subject (FR-046).
 *
 * Pass-scoped lookups (the set of section ids that exist, say) are
 * constructor arguments, not method arguments: they are read once per pass,
 * and threading them through every call would invite reading them per row.
 */
interface QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding;
}
