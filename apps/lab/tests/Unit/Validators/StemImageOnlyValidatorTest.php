<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\StemImageOnlyValidator;

class StemImageOnlyValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_a_stem_that_is_only_an_image(): void
    {
        $question = $this->question($this->healthyOptions(), rawText: '<p><img src="q.png"></p>');
        $finding = (new StemImageOnlyValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::STEM_IMAGE_ONLY, $finding->code);
        $this->assertSame('warning', $finding->code->severity(), 'A picture question is answerable, so it is not an error.');
        $this->assertSame('<p><img src="q.png"></p>', $question->rawText, 'The stem was rewritten.');
    }

    public function test_an_image_with_words_beside_it_is_fine(): void
    {
        $this->assertNull((new StemImageOnlyValidator)->check(
            $this->question($this->healthyOptions(), rawText: '<img src="q.png"> What is shown?')
        ));
    }

    public function test_a_blank_stem_with_no_image_is_the_other_code(): void
    {
        $this->assertNull((new StemImageOnlyValidator)->check(
            $this->question($this->healthyOptions(), rawText: '   ')
        ));
    }
}
