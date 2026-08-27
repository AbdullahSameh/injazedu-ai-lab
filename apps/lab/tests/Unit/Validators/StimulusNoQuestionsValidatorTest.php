<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\SectionUnderImport;
use App\Support\Import\Validators\StimulusNoQuestionsValidator;
use PHPUnit\Framework\TestCase;

class StimulusNoQuestionsValidatorTest extends TestCase
{
    public function test_it_flags_a_stimulus_nobody_reads(): void
    {
        $section = new SectionUnderImport(sourceId: 5, quizSourceId: 1, stimulusRaw: 'Read the passage below.');
        $finding = (new StimulusNoQuestionsValidator([7 => true]))->check($section);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::STIMULUS_NO_QUESTIONS, $finding->code);
        $this->assertSame('warning', $finding->code->severity());
        $this->assertSame(23, $finding->context['stimulus_length']);
        $this->assertSame('Read the passage below.', $section->stimulusRaw, 'The stimulus was rewritten.');
    }

    public function test_a_section_with_questions_is_fine(): void
    {
        $this->assertNull((new StimulusNoQuestionsValidator([5 => true]))->check(
            new SectionUnderImport(sourceId: 5, quizSourceId: 1, stimulusRaw: 'Read the passage below.')
        ));
    }

    public function test_a_section_with_no_stimulus_has_nothing_to_waste(): void
    {
        foreach ([null, '', '   '] as $stimulus) {
            $this->assertNull((new StimulusNoQuestionsValidator([]))->check(
                new SectionUnderImport(sourceId: 5, quizSourceId: 1, stimulusRaw: $stimulus)
            ));
        }
    }
}
