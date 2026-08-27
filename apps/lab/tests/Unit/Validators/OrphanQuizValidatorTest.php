<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\OrphanQuizValidator;
use App\Support\Import\Validators\SectionUnderImport;
use PHPUnit\Framework\TestCase;

class OrphanQuizValidatorTest extends TestCase
{
    public function test_it_flags_a_section_pointing_at_a_quiz_that_does_not_exist(): void
    {
        $section = new SectionUnderImport(sourceId: 5, quizSourceId: 99, stimulusRaw: null);
        $finding = (new OrphanQuizValidator([1 => true, 2 => true]))->check($section);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::ORPHAN_QUIZ, $finding->code);
        $this->assertSame('error', $finding->code->severity());
        $this->assertSame('sections', $finding->sourceTable);
        $this->assertSame(5, $finding->sourceId);
        $this->assertSame(99, $section->quizSourceId, 'The pointer was repaired.');
    }

    public function test_a_null_quiz_id_is_not_an_orphan(): void
    {
        $this->assertNull((new OrphanQuizValidator([1 => true]))->check(
            new SectionUnderImport(sourceId: 5, quizSourceId: null, stimulusRaw: null)
        ));
    }

    public function test_it_passes_a_section_whose_quiz_exists(): void
    {
        $this->assertNull((new OrphanQuizValidator([1 => true]))->check(
            new SectionUnderImport(sourceId: 5, quizSourceId: 1, stimulusRaw: null)
        ));
    }
}
