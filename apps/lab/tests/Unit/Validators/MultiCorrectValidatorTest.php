<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\MultiCorrectValidator;

class MultiCorrectValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_two_scoring_options(): void
    {
        $options = [
            $this->option(1, 'Three', points: 1, order: 1),
            $this->option(2, 'Four', points: 5, order: 2),
            $this->option(3, 'Five', points: 0, order: 3),
        ];

        $question = $this->question($options);
        $finding = (new MultiCorrectValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::MULTI_CORRECT, $finding->code);
        $this->assertSame(2, $finding->context['correct_option_count']);
        $this->assertSame([1, 2], $finding->context['correct_option_ids']);

        // Points outside {0, 1} do not change correctness — any option with
        // points > 0 is a correct one (operator note, 2026-08-26).
        $this->assertSubjectUnchanged($this->question($options), $question);
    }

    public function test_soft_deleted_scoring_options_do_not_count(): void
    {
        $this->assertNull((new MultiCorrectValidator)->check($this->question([
            $this->option(1, 'Three', points: 1, order: 1),
            $this->option(2, 'Four', points: 1, order: 2, deletedAt: '2026-01-01 00:00:00'),
        ])));
    }

    public function test_it_passes_a_healthy_question(): void
    {
        $this->assertNull((new MultiCorrectValidator)->check($this->question($this->healthyOptions())));
    }
}
