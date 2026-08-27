<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\ZeroCorrectValidator;

class ZeroCorrectValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_a_question_where_no_option_scores(): void
    {
        $options = [
            $this->option(1, 'Three', points: 0, order: 1),
            $this->option(2, 'Four', points: 0, order: 2),
        ];

        $question = $this->question($options);
        $finding = (new ZeroCorrectValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::ZERO_CORRECT, $finding->code);
        $this->assertSame('error', $finding->code->severity(), 'ZERO_CORRECT affects a student now (FR-043).');
        $this->assertSubjectUnchanged($this->question($options), $question);
    }

    public function test_a_correct_option_that_was_soft_deleted_leaves_the_question_broken(): void
    {
        $this->assertNotNull((new ZeroCorrectValidator)->check($this->question([
            $this->option(1, 'Three', points: 0, order: 1),
            $this->option(2, 'Four', points: 1, order: 2, deletedAt: '2026-01-01 00:00:00'),
        ])));
    }

    public function test_a_question_with_no_options_also_earns_this_code(): void
    {
        // Not suppressed in favour of MISSING_OPTIONS: query 3 counts these
        // among its `correct_count = 0` rows, and FR-045 requires the two
        // numbers to agree exactly.
        $this->assertNotNull((new ZeroCorrectValidator)->check($this->question(options: [])));
    }

    public function test_it_passes_a_healthy_question(): void
    {
        $this->assertNull((new ZeroCorrectValidator)->check($this->question($this->healthyOptions())));
    }
}
