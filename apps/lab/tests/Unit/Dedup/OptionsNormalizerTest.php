<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\ArabicNormalizer;
use App\Support\Dedup\OptionLabelStripper;
use App\Support\Dedup\OptionsNormalizer;
use Tests\TestCase;

/** FR-016: order-independent, content-sensitive. */
class OptionsNormalizerTest extends TestCase
{
    private function normalizer(): OptionsNormalizer
    {
        return new OptionsNormalizer(new ArabicNormalizer(new OptionLabelStripper));
    }

    public function test_options_in_a_different_input_order_produce_an_identical_string(): void
    {
        $normalizer = $this->normalizer();

        $inOrder = [
            ['option_index' => 0, 'raw_text' => 'أ) تقهقر'],
            ['option_index' => 1, 'raw_text' => 'ب) سلام'],
            ['option_index' => 2, 'raw_text' => 'ج) مقبل'],
        ];

        $shuffled = [
            ['option_index' => 2, 'raw_text' => 'ج) مقبل'],
            ['option_index' => 0, 'raw_text' => 'أ) تقهقر'],
            ['option_index' => 1, 'raw_text' => 'ب) سلام'],
        ];

        $this->assertSame($normalizer->build($inOrder), $normalizer->build($shuffled));
    }

    public function test_a_changed_option_text_produces_a_different_string(): void
    {
        $normalizer = $this->normalizer();

        $original = [
            ['option_index' => 0, 'raw_text' => 'أ) تقهقر'],
            ['option_index' => 1, 'raw_text' => 'ب) سلام'],
        ];

        $changed = [
            ['option_index' => 0, 'raw_text' => 'أ) تقهقر'],
            ['option_index' => 1, 'raw_text' => 'ب) حرب'],
        ];

        $this->assertNotSame($normalizer->build($original), $normalizer->build($changed));
    }

    public function test_it_reflects_option_index_not_array_input_order(): void
    {
        $normalizer = $this->normalizer();

        // Deliberately given in reverse of option_index to prove build()
        // sorts by option_index and never trusts array position.
        $reversed = [
            ['option_index' => 2, 'raw_text' => 'third'],
            ['option_index' => 1, 'raw_text' => 'second'],
            ['option_index' => 0, 'raw_text' => 'first'],
        ];

        $this->assertSame("first\nsecond\nthird", $normalizer->build($reversed));
    }
}
