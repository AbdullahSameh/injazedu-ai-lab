<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\OrphanSectionValidator;

class OrphanSectionValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_a_question_pointing_at_a_section_that_does_not_exist(): void
    {
        $validator = new OrphanSectionValidator([10 => true, 11 => true]);

        $question = $this->question($this->healthyOptions(), sectionId: 99);
        $finding = $validator->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::ORPHAN_SECTION, $finding->code);
        $this->assertSame(99, $finding->context['section_source_id']);
        $this->assertSame(99, $question->sectionSourceId, 'The pointer was repaired.');
    }

    public function test_a_null_section_is_the_other_code(): void
    {
        $validator = new OrphanSectionValidator([10 => true]);

        $this->assertNull($validator->check($this->question($this->healthyOptions(), sectionId: null)));
    }

    public function test_it_passes_a_question_whose_section_exists(): void
    {
        $validator = new OrphanSectionValidator([10 => true]);

        $this->assertNull($validator->check($this->question($this->healthyOptions(), sectionId: 10)));
    }
}
