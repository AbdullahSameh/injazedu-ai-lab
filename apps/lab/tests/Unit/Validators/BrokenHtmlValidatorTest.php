<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\BrokenHtmlValidator;

class BrokenHtmlValidatorTest extends ValidatorTestCase
{
    public function test_it_flags_an_unclosed_tag(): void
    {
        $question = $this->question($this->healthyOptions(), rawText: '<p>Which of these <b>is correct?</p>');
        $finding = (new BrokenHtmlValidator)->check($question);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::BROKEN_HTML, $finding->code);
        $this->assertSame(['b' => 1], $finding->context['unbalanced']);
        $this->assertSame('<p>Which of these <b>is correct?</p>', $question->rawText, 'The markup was repaired.');
    }

    public function test_a_stray_closing_tag_is_as_broken_as_a_missing_one(): void
    {
        $finding = (new BrokenHtmlValidator)->check(
            $this->question($this->healthyOptions(), rawText: 'Which of these</div> is correct?')
        );

        $this->assertNotNull($finding);
        $this->assertSame(['div' => -1], $finding->context['unbalanced']);
    }

    public function test_void_elements_need_no_closing_tag(): void
    {
        $this->assertNull((new BrokenHtmlValidator)->check(
            $this->question($this->healthyOptions(), rawText: 'Line one<br>Line two<hr><img src="x.png">')
        ));
    }

    public function test_self_closing_tags_are_balanced(): void
    {
        $this->assertNull((new BrokenHtmlValidator)->check(
            $this->question($this->healthyOptions(), rawText: '<p>Text<span/></p>')
        ));
    }

    public function test_plain_text_is_not_examined_at_all(): void
    {
        $this->assertNull((new BrokenHtmlValidator)->check(
            $this->question($this->healthyOptions(), rawText: 'What is 2 + 2?')
        ));
    }

    public function test_it_never_throws_whatever_it_is_handed(): void
    {
        // FR-043: BROKEN_HTML must not stop the batch. Whatever arrives, the
        // check returns — a finding or null — and never propagates.
        $nasty = [
            '<'.str_repeat('a', 100_000),
            "<p>\x00\xC3\x28 broken utf8</p>",
            '<<<<>>>>',
            '<p '.str_repeat('x="y" ', 5000).'>unclosed',
        ];

        foreach ($nasty as $text) {
            $result = (new BrokenHtmlValidator)->check(
                $this->question($this->healthyOptions(), rawText: $text)
            );

            $this->assertTrue($result === null || $result->code === ImportErrorCode::BROKEN_HTML);
        }
    }
}
