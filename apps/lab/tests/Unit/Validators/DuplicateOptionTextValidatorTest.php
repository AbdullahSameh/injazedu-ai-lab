<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\DuplicateOptionTextValidator;

class DuplicateOptionTextValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_two_options_with_the_same_text(): void
    {
        $options = [
            $this->option(1, 'Four', order: 1),
            $this->option(2, 'Four', order: 2),
            $this->option(3, 'Five', order: 3),
        ];

        $question = $this->question($options);
        $finding = (new DuplicateOptionTextValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::DUPLICATE_OPTION_TEXT, $finding->code);
        $this->assertSame(['Four'], $finding->context['duplicated_texts']);
        $this->assertSubjectUnchanged($this->question($options), $question);
    }

    public function test_trailing_whitespace_is_not_a_distinction_a_student_can_see(): void
    {
        $this->assertNotNull((new DuplicateOptionTextValidator)->check($this->question([
            $this->option(1, 'Four', order: 1),
            $this->option(2, "Four  \n", order: 2),
        ])));
    }

    public function test_case_is_left_alone_because_p1_does_not_normalize(): void
    {
        // The source collation is case-insensitive, so MySQL would call these
        // identical. P1 copies and names; deciding that 'four' and 'Four' are
        // the same answer is normalization, and that is P2's call.
        $this->assertNull((new DuplicateOptionTextValidator)->check($this->question([
            $this->option(1, 'Four', order: 1),
            $this->option(2, 'four', order: 2),
        ])));
    }

    public function test_blank_options_are_not_reported_as_duplicates_of_each_other(): void
    {
        // 336 options in the source have empty text. Grouping them would bury
        // the real duplicates under a problem that is not one of the thirteen.
        $this->assertNull((new DuplicateOptionTextValidator)->check($this->question([
            $this->option(1, '', order: 1),
            $this->option(2, '   ', order: 2),
        ])));
    }

    public function test_a_soft_deleted_twin_is_not_a_duplicate(): void
    {
        $this->assertNull((new DuplicateOptionTextValidator)->check($this->question([
            $this->option(1, 'Four', order: 1),
            $this->option(2, 'Four', order: 2, deletedAt: '2026-01-01 00:00:00'),
        ])));
    }

    public function test_it_passes_a_healthy_question(): void
    {
        $this->assertNull((new DuplicateOptionTextValidator)->check($this->question($this->healthyOptions())));
    }
}
