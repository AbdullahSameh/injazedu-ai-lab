<?php

namespace App\Support\Dedup;

/**
 * Layer 0/1 identity and the media boundary fingerprint (FR-015, FR-017,
 * FR-019, FR-141, notes.md N5). `fuzzyTextHash()` is the ONLY caller of
 * `ArabicNormalizer::fuzzy()` in the whole feature, and its output is
 * never an input to `questionTextHash()` or `questionWithOptionsHash()` —
 * that separation is what FuzzyFoldIsolationTest proves (FR-143a).
 */
final class DuplicateHasher
{
    public function __construct(private readonly ArabicNormalizer $normalizer) {}

    /** Layer 0. SHA-256 over search_text — the strict stem identity (FR-015). */
    public function questionTextHash(string $searchText): string
    {
        return hash('sha256', $searchText);
    }

    /**
     * Layer 1. SHA-256 over search_text joined with the normalized options
     * string (App\Support\Dedup\OptionsNormalizer's output, already in
     * option_index order) — the strict full identity (FR-015).
     */
    public function questionWithOptionsHash(string $searchText, string $normalizedOptionsString): string
    {
        return hash('sha256', $searchText."\n".$normalizedOptionsString);
    }

    /**
     * FR-141: recall-only. Takes search_text (never the fuzzy text itself),
     * folds it internally, and hashes the fold. Never an input to the two
     * strict hashes above.
     */
    public function fuzzyTextHash(string $searchText): string
    {
        return hash('sha256', $this->normalizer->fuzzy($searchText));
    }

    /**
     * FR-017, notes.md N5: hashes an ORDERED list of attached image paths.
     * A NULL path folds in as the empty string — defined, rather than
     * sha256(null). Returns null when the question carries no image, so
     * `media_fingerprint` is NULL rather than the hash of an empty list.
     *
     * @param  list<string|null>  $orderedPaths
     */
    public function mediaFingerprint(array $orderedPaths): ?string
    {
        if ($orderedPaths === []) {
            return null;
        }

        $normalized = array_map(static fn (?string $path): string => $path ?? '', $orderedPaths);

        return hash('sha256', implode("\n", $normalized));
    }
}
