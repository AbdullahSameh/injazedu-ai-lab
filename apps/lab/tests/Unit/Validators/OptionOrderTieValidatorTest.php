<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\OptionOrderTieValidator;

class OptionOrderTieValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_options_sharing_an_order_value(): void
    {
        $options = [
            $this->option(1, 'Three', order: 0),
            $this->option(2, 'Four', order: 0),
            $this->option(3, 'Five', order: 1),
        ];

        $question = $this->question($options);
        $finding = (new OptionOrderTieValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::OPTION_ORDER_TIE, $finding->code);
        $this->assertSame([0], $finding->context['tied_orders']);
        $this->assertSubjectUnchanged($this->question($options), $question);
    }

    public function test_the_default_zero_is_what_makes_this_the_common_case(): void
    {
        // `options.order` defaults to 0 in the source and so repeats
        // constantly — 29,075 of 29,142 questions on the fixed snapshot.
        $finding = (new OptionOrderTieValidator)->check($this->question([
            $this->option(1, 'A'), $this->option(2, 'B'),
            $this->option(3, 'C'), $this->option(4, 'D'),
        ]));

        $this->assertNotNull($finding);
    }

    public function test_soft_deleted_options_do_not_create_a_tie(): void
    {
        $this->assertNull((new OptionOrderTieValidator)->check($this->question([
            $this->option(1, 'Three', order: 1),
            $this->option(2, 'Four', order: 1, deletedAt: '2026-01-01 00:00:00'),
        ])));
    }

    public function test_it_passes_options_with_distinct_order_values(): void
    {
        $this->assertNull((new OptionOrderTieValidator)->check($this->question($this->healthyOptions())));
    }
}
