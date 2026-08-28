<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\ArabicNormalizer;
use App\Support\Dedup\DuplicateHasher;
use App\Support\Dedup\OptionLabelStripper;
use Tests\TestCase;

/**
 * FR-143a: the test that keeps the carve-out honest. questionTextHash()
 * and questionWithOptionsHash() must be byte-identical whether
 * fuzzy_fold_enabled is true or false — proving the fold provably cannot
 * reach the strict path. Constitution IV (v2.5.0) permits the fold ONLY
 * on this condition.
 */
class FuzzyFoldIsolationTest extends TestCase
{
    /** A fixture deliberately containing both ة and ه variants of the same words. */
    private const FIXTURE_STEMS = [
        'المشرفة التربوية',
        'المشرفه التربوية',
        'تحمي البشرة من الجفاف',
        'تحمي البشره من الجفاف',
        'شبه جملة',
        'شبة جملة',
    ];

    private function hasher(): DuplicateHasher
    {
        return new DuplicateHasher(new ArabicNormalizer(new OptionLabelStripper));
    }

    public function test_strict_hashes_are_byte_identical_with_the_fold_enabled_and_disabled(): void
    {
        $normalizer = new ArabicNormalizer(new OptionLabelStripper);
        $hasher = $this->hasher();

        foreach (self::FIXTURE_STEMS as $raw) {
            $searchText = $normalizer->search($normalizer->clean($raw));

            config(['lab.dedup.fuzzy_fold_enabled' => true]);
            $textHashEnabled = $hasher->questionTextHash($searchText);
            $withOptionsHashEnabled = $hasher->questionWithOptionsHash($searchText, 'أ) نص');

            config(['lab.dedup.fuzzy_fold_enabled' => false]);
            $textHashDisabled = $hasher->questionTextHash($searchText);
            $withOptionsHashDisabled = $hasher->questionWithOptionsHash($searchText, 'أ) نص');

            $this->assertSame(
                $textHashEnabled,
                $textHashDisabled,
                "questionTextHash() must be byte-identical for: {$raw}"
            );
            $this->assertSame(
                $withOptionsHashEnabled,
                $withOptionsHashDisabled,
                "questionWithOptionsHash() must be byte-identical for: {$raw}"
            );
        }
    }

    public function test_the_fold_actually_does_something_when_enabled_so_this_suite_is_not_vacuous(): void
    {
        $normalizer = new ArabicNormalizer(new OptionLabelStripper);

        config(['lab.dedup.fuzzy_fold_enabled' => true]);

        $a = $normalizer->search($normalizer->clean('المشرفة التربوية'));
        $b = $normalizer->search($normalizer->clean('المشرفه التربوية'));

        // Different under the strict form...
        $this->assertNotSame($a, $b);

        // ...but the recall-only fold unifies them.
        $this->assertSame($normalizer->fuzzy($a), $normalizer->fuzzy($b));
    }

    public function test_fuzzy_text_hash_differs_from_strict_hash_on_a_teh_marbuta_variant(): void
    {
        $normalizer = new ArabicNormalizer(new OptionLabelStripper);
        $hasher = $this->hasher();

        config(['lab.dedup.fuzzy_fold_enabled' => true]);

        $searchText = $normalizer->search($normalizer->clean('المشرفة التربوية'));

        $this->assertNotSame(
            $hasher->questionTextHash($searchText),
            $hasher->fuzzyTextHash($searchText)
        );
    }
}
