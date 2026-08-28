<?php

namespace App\Support\Dedup;

/**
 * FR-139: removes ONLY a leading option marker — `أ)`, `B-`, `c.`, `٣.` —
 * and never a letter occurring naturally inside the text. The match is
 * anchored to the start of the string, consumes exactly one label token,
 * and requires a delimiter followed by whitespace after it:
 *
 *     ^\s*  LABEL  \s*  DELIMITER  \s+          — nothing else matches
 *
 * so `B.F. Skinner` (no whitespace after the first delimiter), `Vitamin A`
 * and `A root plus derivations` (no delimiter at all) all survive
 * untouched. Both alphabets are required — see `config('lab.dedup')` and
 * notes.md N10.
 *
 * Gate H, 2026-08-29: this class runs FIRST inside
 * `ArabicNormalizer::search()`, before Alef-form normalization, so the
 * label alphabet's four Hamza-Alef forms (`أ إ آ ا`) are still
 * distinguishable when it looks for a match.
 */
final class OptionLabelStripper
{
    /**
     * @return array{label: string|null, text: string}
     */
    public function strip(string $text): array
    {
        $alphabets = config('lab.dedup.option_label_alphabets', []);
        $delimiters = config('lab.dedup.option_label_delimiters', []);

        $labels = array_merge(
            $alphabets['ar'] ?? [],
            $alphabets['la'] ?? [],
            $alphabets['digit'] ?? [],
        );

        if ($labels === [] || $delimiters === []) {
            return ['label' => null, 'text' => $text];
        }

        // Longest labels first, so a two-codepoint label (هـ) is never
        // shadowed by a one-codepoint prefix of itself (ه).
        usort($labels, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $labelAlternation = implode('|', array_map(
            static fn (string $label): string => preg_quote($label, '/'),
            $labels
        ));
        $delimiterClass = implode('', array_map(
            static fn (string $delimiter): string => preg_quote($delimiter, '/'),
            $delimiters
        ));

        // Either `LABEL <delim> `, or the `(LABEL)` wrapped form.
        $pattern = '/^\s*(?:('.$labelAlternation.')\s*['.$delimiterClass.']|\(('.$labelAlternation.')\))\s+/u';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return ['label' => null, 'text' => $text];
        }

        $label = $matches[1] !== '' ? $matches[1] : $matches[2];
        $rest = (string) preg_replace($pattern, '', $text, 1);

        return ['label' => $label, 'text' => $rest];
    }
}
