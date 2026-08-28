<?php

namespace App\Support\Dedup;

use Normalizer as UnicodeNormalizer;

/**
 * Three text layers over one question or option (FR-010 – FR-014, FR-018,
 * FR-139 – FR-141, FR-155). `clean()` and `search()` are the STRICT path —
 * `ة` is never rewritten to `ه` here, in any hash, cluster key or identity
 * decision (FR-012). `fuzzy()` is the one named RECALL-ONLY exception
 * (FR-141): it may only propose candidates and must never reach a strict
 * hash — `App\Support\Dedup\DuplicateHasher::fuzzyTextHash()` is its only
 * caller.
 *
 * `search()`'s transform order is pinned at gate H (2026-08-29, spec
 * Clarifications): option-label stripping runs FIRST, against
 * `أ إ آ ا` while they are still distinguishable, and Alef-form
 * normalization — scoped to `أ`/`إ`/`آ` → `ا` only, never `ى` — runs near
 * the end, after punctuation and digits. Case folding is last.
 */
final class ArabicNormalizer
{
    /** Bump on any change to clean()/search() — makes a stale hash visible (FR-018). */
    public const VERSION = 'p2-normalizer-v1';

    /** Separate from VERSION on purpose — the strict hashes do not depend on the fold (FR-141). */
    public const FUZZY_VERSION = 'p2-fuzzy-v1';

    /** Arabic combining marks: harakat, superscript alef, small high marks. */
    private const DIACRITICS_PATTERN = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u';

    /** Never stripped by normalizePunctuation() — load-bearing in any script (FR-155). */
    private const DECORATIVE_PUNCTUATION_PATTERN = '/[.,;:!?،؛؟"\x{201C}\x{201D}\x{201E}\x{00AB}\x{00BB}()\[\]{}*\/-]+/u';

    public function __construct(private readonly OptionLabelStripper $labelStripper) {}

    /** FR-010: technical cleanup only — HTML stripping, whitespace collapse, NFC. Meaning preserved. */
    public function clean(string $raw): string
    {
        $text = strip_tags($raw);
        $text = UnicodeNormalizer::normalize($text, UnicodeNormalizer::FORM_C) ?: $text;

        return $this->collapseWhitespace($text);
    }

    /** FR-011: the strict comparison representation. Idempotent (FR-013). */
    public function search(string $clean): string
    {
        $stripped = $this->labelStripper->strip($clean);
        $text = $stripped['text'];

        $text = $this->removeTatweel($text);
        $text = $this->removeDiacritics($text);
        $text = $this->normalizePunctuation($text);
        $text = $this->unifyDigits($text);
        $text = $this->unifyAlefForms($text);
        $text = mb_strtolower($text, 'UTF-8'); // FR-140, Unicode-aware, applied last

        return $this->collapseWhitespace($text);
    }

    /**
     * FR-141: a recall-only fold of search_text, driven by config. MUST
     * NEVER be called anywhere upstream of a hash, cluster key or identity
     * decision — DuplicateHasher::fuzzyTextHash() is the only caller.
     */
    public function fuzzy(string $searchText): string
    {
        if (! config('lab.dedup.fuzzy_fold_enabled', false)) {
            return $searchText;
        }

        return strtr($searchText, config('lab.dedup.fuzzy_fold_map', []));
    }

    private function removeTatweel(string $text): string
    {
        return str_replace("\u{0640}", '', $text);
    }

    private function removeDiacritics(string $text): string
    {
        return (string) preg_replace(self::DIACRITICS_PATTERN, '', $text);
    }

    private function unifyDigits(string $text): string
    {
        static $arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        static $extendedArabicIndic = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        static $ascii = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($extendedArabicIndic, $ascii, str_replace($arabicIndic, $ascii, $text));
    }

    /** Gate H, 2026-08-29: أ/إ/آ -> ا ONLY. ى (alef maksura) is never folded, here or in fuzzy(). */
    private function unifyAlefForms(string $text): string
    {
        return str_replace(['أ', 'إ', 'آ'], 'ا', $text);
    }

    /**
     * FR-155: strip-and-collapse only punctuation that is decorative in the
     * normalized search layer. Punctuation load-bearing for technical or
     * linguistic meaning is protected first (a placeholder swap, since it
     * would otherwise sit inside DECORATIVE_PUNCTUATION_PATTERN's class)
     * and restored after stripping: a decimal point between digits in any
     * script (digit unification runs AFTER this step, so \p{Nd} — not
     * \d — must catch Arabic-Indic digits too), a "tight" slash with no
     * surrounding whitespace (km/h, 1/2 — a loose slash like "have/ learn"
     * is decorative and strips), and a hyphen acting as a sign (-5) or a
     * tight compound/range (e-mail, 3-2, 20-30 — a spaced dash like
     * "word - word" is decorative and strips). Percent, degree, plus/minus
     * comparison and arithmetic operators, and every apostrophe form
     * (contractions) are simply never in the strip class at all — they do
     * not occur as decorative sentence punctuation in practice.
     */
    private function normalizePunctuation(string $text): string
    {
        $text = (string) preg_replace('/(\p{Nd})\.(?=\p{Nd})/u', "$1\x01", $text);
        $text = (string) preg_replace('/(?<=\S)\/(?=\S)/u', "\x02", $text);
        $text = (string) preg_replace('/(?<!\S)-(?=\p{Nd})|(?<=\S)-(?=\S)/u', "\x03", $text);

        $text = (string) preg_replace(self::DECORATIVE_PUNCTUATION_PATTERN, ' ', $text);

        return str_replace(["\x01", "\x02", "\x03"], ['.', '/', '-'], $text);
    }

    private function collapseWhitespace(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
