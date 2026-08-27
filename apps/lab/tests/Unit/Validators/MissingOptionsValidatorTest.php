<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\MissingOptionsValidator;

class MissingOptionsValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_a_question_with_no_options(): void
    {
        $question = $this->question(options: []);
        $finding = (new MissingOptionsValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::MISSING_OPTIONS, $finding->code);
        $this->assertSame('error', $finding->code->severity());
        $this->assertSame(1, $finding->sourceId);
        $this->assertSubjectUnchanged($this->question(options: []), $question);
    }

    public function test_a_question_whose_options_were_all_soft_deleted_is_equally_unanswerable(): void
    {
        $finding = (new MissingOptionsValidator)->check($this->question([
            $this->option(1, deletedAt: '2026-01-01 00:00:00'),
            $this->option(2, deletedAt: '2026-01-01 00:00:00'),
        ]));

        $this->assertNotNull($finding);
        $this->assertSame(2, $finding->context['options_total']);
    }

    public function test_it_passes_a_healthy_question(): void
    {
        $this->assertNull((new MissingOptionsValidator)->check($this->question($this->healthyOptions())));
    }
}
