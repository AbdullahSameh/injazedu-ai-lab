<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\QuestionNoSectionValidator;

class QuestionNoSectionValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_a_question_with_no_section(): void
    {
        $question = $this->question($this->healthyOptions(), sectionId: null);
        $finding = (new QuestionNoSectionValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::QUESTION_NO_SECTION, $finding->code);
        $this->assertSame('error', $finding->code->severity());
        $this->assertNull($question->sectionSourceId, 'The section was invented.');
    }

    public function test_it_passes_a_question_that_has_one(): void
    {
        $this->assertNull((new QuestionNoSectionValidator)->check($this->question($this->healthyOptions())));
    }
}
