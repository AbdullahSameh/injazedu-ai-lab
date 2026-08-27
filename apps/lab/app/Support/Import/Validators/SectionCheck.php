<?php

namespace App\Support\Import\Validators;

/** A check over one section. See {@see QuestionCheck} for the contract. */
interface SectionCheck
{
    public function check(SectionUnderImport $section): ?Finding;
}
