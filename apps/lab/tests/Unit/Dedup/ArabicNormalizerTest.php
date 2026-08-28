<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\ArabicNormalizer;
use App\Support\Dedup\OptionLabelStripper;
use Tests\TestCase;

/**
 * FR-010 – FR-014, FR-018, FR-140, FR-141, FR-155. Extends the Laravel
 * TestCase (not plain PHPUnit) because search()/fuzzy() read
 * config('lab.dedup.*') — no database row is ever touched.
 */
class ArabicNormalizerTest extends TestCase
{
    private function normalizer(): ArabicNormalizer
    {
        return new ArabicNormalizer(new OptionLabelStripper);
    }

    public function test_tatweel_is_removed(): void
    {
        $n = $this->normalizer();
        $this->assertSame('كتاب', $n->search($n->clean('كـــتاب')));
    }

    public function test_diacritics_are_removed(): void
    {
        $n = $this->normalizer();
        $this->assertSame('كتاب', $n->search($n->clean('كِتَابٌ')));
    }

    public function test_arabic_indic_and_extended_digits_unify_to_ascii(): void
    {
        $n = $this->normalizer();
        $this->assertSame('12', $n->search($n->clean('١٢')));
        $this->assertSame('34', $n->search($n->clean('۳۴')));
    }

    public function test_alef_forms_fold_to_bare_alef_but_alef_maksura_never_folds(): void
    {
        $n = $this->normalizer();

        $this->assertSame('اب اب اب', $n->search($n->clean('أب إب آب')));

        // ى (alef maksura, U+0649) must survive untouched — gate H, 2026-08-29.
        $this->assertSame('على', $n->search($n->clean('على')));
        $this->assertStringNotContainsString('علا', $n->search($n->clean('على')));
    }

    public function test_case_folding_is_unicode_aware(): void
    {
        $n = $this->normalizer();
        $this->assertSame(
            $n->search($n->clean('Hello World')),
            $n->search($n->clean('HELLO WORLD'))
        );
    }

    public function test_search_is_idempotent(): void
    {
        $n = $this->normalizer();
        $once = $n->search($n->clean('  أفضل الأساليب  التعليمية جِدًّا  '));
        $twice = $n->search($once);

        $this->assertSame($once, $twice);
    }

    public function test_html_branch_strips_synthetic_markup_even_though_the_snapshot_has_none(): void
    {
        $n = $this->normalizer();
        $this->assertSame(
            'bold and italic',
            $n->search($n->clean('<p><b>bold</b> and <i>italic</i></p>'))
        );
    }

    public function test_meaning_change_never_folds_teh_marbuta_to_heh(): void
    {
        $n = $this->normalizer();

        // The explicit negative test: must fail the moment ة -> ه is added
        // to the strict path (FR-012).
        $this->assertSame('مدرسة', $n->search($n->clean('مدرسة')));
        $this->assertNotSame('مدرسه', $n->search($n->clean('مدرسة')));
    }

    // --- FR-155: punctuation preservation -----------------------------------

    public function test_a_decimal_point_between_digits_survives(): void
    {
        $n = $this->normalizer();
        $this->assertSame('3.14', $n->search($n->clean('3.14')));
    }

    public function test_a_decimal_point_between_arabic_indic_digits_survives_and_then_unifies(): void
    {
        $n = $this->normalizer();
        // Digit unification runs AFTER punctuation normalization (FR-011's
        // pinned order) — the decimal point must still be protected.
        $this->assertSame('3.14', $n->search($n->clean('٣.١٤')));
    }

    public function test_percent_and_degree_signs_survive(): void
    {
        $n = $this->normalizer();
        $this->assertSame('50%', $n->search($n->clean('50%')));
        $this->assertSame('36.5°c', $n->search($n->clean('36.5°C')));
    }

    public function test_a_tight_unit_slash_survives_but_a_loose_slash_is_decorative(): void
    {
        $n = $this->normalizer();
        $this->assertSame('km/h', $n->search($n->clean('km/h')));
        $this->assertSame('1/2', $n->search($n->clean('1/2')));
        $this->assertSame('have learn', $n->search($n->clean('have/ learn')));
    }

    public function test_signed_numbers_and_operators_survive(): void
    {
        $n = $this->normalizer();
        $this->assertSame('±5', $n->search($n->clean('±5')));
        $this->assertSame('the temperature is -5 today', $n->search($n->clean('the temperature is -5 today')));
        $this->assertSame('3-2=1', $n->search($n->clean('3-2=1')));
    }

    public function test_a_tight_hyphen_survives_but_a_spaced_dash_is_decorative(): void
    {
        $n = $this->normalizer();
        $this->assertSame('e-mail', $n->search($n->clean('e-mail')));
        $this->assertSame('20-30', $n->search($n->clean('20-30')));
        $this->assertSame('word word', $n->search($n->clean('word - word')));
    }

    public function test_an_apostrophe_in_a_contraction_survives(): void
    {
        $n = $this->normalizer();
        $this->assertSame("don't have to", $n->search($n->clean("don't have to")));
    }

    public function test_decorative_punctuation_strips_and_collapses(): void
    {
        $n = $this->normalizer();
        $this->assertSame('end of sentence', $n->search($n->clean('end of sentence.')));
        $this->assertSame('hello', $n->search($n->clean('"hello"')));
        $this->assertSame('a question', $n->search($n->clean('a question؟')));
    }
}
