<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\OptionLabelStripper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-139, SC-031. Validated against a 35-row sample of the real mirror at
 * gate H (spec Clarifications, session 2026-08-29) before this class
 * existed — these cases are drawn directly from that sample.
 */
class OptionLabelStripperTest extends TestCase
{
    public static function positiveCases(): array
    {
        return [
            'Arabic, dot delimiter' => ['أ. المدينة', 'أ', 'المدينة'],
            'Arabic, paren delimiter' => ['ب) سلام : حرب', 'ب', 'سلام : حرب'],
            'Latin uppercase, paren' => ['B) Paris', 'B', 'Paris'],
            'Latin lowercase, dash' => ['c- cat', 'c', 'cat'],
            'digit label, dot' => ['٣. ثلاثة', '٣', 'ثلاثة'],
            'wrapped form' => ['(A) who', 'A', 'who'],
            'madda-alef label' => ['آ) واحد', 'آ', 'واحد'],
        ];
    }

    #[DataProvider('positiveCases')]
    public function test_a_leading_option_marker_is_stripped(string $input, string $expectedLabel, string $expectedText): void
    {
        $result = (new OptionLabelStripper)->strip($input);

        $this->assertSame($expectedLabel, $result['label']);
        $this->assertSame($expectedText, $result['text']);
    }

    public static function negativeCases(): array
    {
        return [
            'a real Arabic word starting with a label letter' => ['دمشق'],
            'a real English sentence starting with a label letter' => ['A cat sat on the mat'],
            'a real English sentence starting with A + space' => ['A root plus derivations & inflectional'],
            'an abbreviation with no whitespace after the delimiter' => ['B.F. Skinner'],
            'a delimiter glued to the next word' => ['D.Formative test'],
            'a proper noun, not a label' => ['Vitamin A'],
            'a mid-string label-shaped token' => ['future perfect form: A. Will we done B. Will we have done'],
        ];
    }

    #[DataProvider('negativeCases')]
    public function test_real_content_is_never_removed(string $input): void
    {
        $result = (new OptionLabelStripper)->strip($input);

        $this->assertNull($result['label']);
        $this->assertSame($input, $result['text']);
    }
}
