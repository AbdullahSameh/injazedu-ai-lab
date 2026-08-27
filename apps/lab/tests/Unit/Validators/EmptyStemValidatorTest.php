<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\EmptyStemValidator;

class EmptyStemValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_a_stem_that_strips_to_nothing(): void
    {
        $question = $this->question($this->healthyOptions(), rawText: '<p>  </p>');
        $finding = (new EmptyStemValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::EMPTY_STEM, $finding->code);
        $this->assertSame('error', $finding->code->severity());
        $this->assertSame('<p>  </p>', $question->rawText, 'The stem was rewritten.');
    }

    public function test_it_flags_a_null_stem(): void
    {
        $this->assertNotNull((new EmptyStemValidator)->check(
            $this->question($this->healthyOptions(), rawText: null)
        ));
    }

    public function test_an_image_only_stem_is_the_other_code_not_this_one(): void
    {
        // Exclusive by design: overlapping the two would make neither count
        // answer its own question.
        $this->assertNull((new EmptyStemValidator)->check(
            $this->question($this->healthyOptions(), rawText: '<img src="q.png">')
        ));
    }

    public function test_it_passes_a_healthy_question(): void
    {
        $this->assertNull((new EmptyStemValidator)->check($this->question($this->healthyOptions())));
    }
}
