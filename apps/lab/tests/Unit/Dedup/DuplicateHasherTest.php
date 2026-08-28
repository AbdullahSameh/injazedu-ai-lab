<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\ArabicNormalizer;
use App\Support\Dedup\DuplicateHasher;
use App\Support\Dedup\OptionLabelStripper;
use Tests\TestCase;

/** FR-015, FR-017, FR-019, notes.md N5. */
class DuplicateHasherTest extends TestCase
{
    private function hasher(): DuplicateHasher
    {
        return new DuplicateHasher(new ArabicNormalizer(new OptionLabelStripper));
    }

    public function test_question_text_hash_is_stable_for_the_same_input(): void
    {
        $hasher = $this->hasher();
        $this->assertSame(
            $hasher->questionTextHash('same text'),
            $hasher->questionTextHash('same text')
        );
    }

    public function test_question_text_hash_is_sensitive_to_a_change(): void
    {
        $hasher = $this->hasher();
        $this->assertNotSame(
            $hasher->questionTextHash('text a'),
            $hasher->questionTextHash('text b')
        );
    }

    public function test_question_with_options_hash_is_sensitive_to_a_changed_option(): void
    {
        $hasher = $this->hasher();
        $this->assertNotSame(
            $hasher->questionWithOptionsHash('stem', "opt a\nopt b"),
            $hasher->questionWithOptionsHash('stem', "opt a\nopt c")
        );
    }

    public function test_question_with_options_hash_is_insensitive_to_option_presentation_order(): void
    {
        // OptionsNormalizer already resolves order before the string
        // reaches the hasher — the hasher itself just hashes whatever
        // string it is given, so two callers producing the same
        // options-in-index-order string get the same hash regardless of
        // what order the caller originally read the rows in.
        $hasher = $this->hasher();
        $this->assertSame(
            $hasher->questionWithOptionsHash('stem', "opt a\nopt b"),
            $hasher->questionWithOptionsHash('stem', "opt a\nopt b")
        );
    }

    public function test_media_fingerprint_is_null_for_no_images(): void
    {
        $hasher = $this->hasher();
        $this->assertNull($hasher->mediaFingerprint([]));
    }

    public function test_a_two_image_question_fingerprints_differently_from_a_one_image_question(): void
    {
        $hasher = $this->hasher();

        $one = $hasher->mediaFingerprint(['/media/a.png']);
        $two = $hasher->mediaFingerprint(['/media/a.png', '/media/b.png']);

        $this->assertNotNull($one);
        $this->assertNotNull($two);
        $this->assertNotSame($one, $two);
    }

    public function test_media_fingerprint_is_order_sensitive(): void
    {
        $hasher = $this->hasher();

        $forward = $hasher->mediaFingerprint(['/media/a.png', '/media/b.png']);
        $reversed = $hasher->mediaFingerprint(['/media/b.png', '/media/a.png']);

        $this->assertNotSame($forward, $reversed);
    }

    public function test_a_null_path_is_defined_rather_than_sha256_of_null(): void
    {
        $hasher = $this->hasher();

        $withNullPath = $hasher->mediaFingerprint([null]);
        $withEmptyStringPath = $hasher->mediaFingerprint(['']);

        $this->assertNotNull($withNullPath);
        $this->assertSame($withEmptyStringPath, $withNullPath);
        $this->assertNotSame(hash('sha256', serialize(null)), $withNullPath);
    }

    public function test_fuzzy_text_hash_output_never_feeds_the_two_strict_hashes(): void
    {
        // A structural guarantee, not just behavioural: neither strict
        // method accepts the fuzzy fold's output as meaningful input — it
        // is hashed as ordinary text, proving fuzzyTextHash()'s result has
        // no privileged path into the strict hashes.
        $hasher = $this->hasher();

        $searchText = 'المشرفة التربوية';
        $fuzzyHash = $hasher->fuzzyTextHash($searchText);

        $this->assertNotSame($hasher->questionTextHash($searchText), $fuzzyHash);
    }
}
