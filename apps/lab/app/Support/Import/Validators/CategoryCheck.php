<?php

namespace App\Support\Import\Validators;

/** A check over one category. See {@see QuestionCheck} for the contract. */
interface CategoryCheck
{
    public function check(CategoryUnderImport $category): ?Finding;
}
