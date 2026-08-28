<?php

namespace App\Support\Dedup;

/**
 * FR-016: the ordered normalized options string that feeds
 * `questionWithOptionsHash()`. Consumes P1's existing `option_index` —
 * `App\Support\Derive\OptionIndexDeriver`'s ordering is reused and NEVER
 * re-derived here, because re-solving the `options.order` tie differently
 * would silently produce a different hash for the same real order.
 */
final class OptionsNormalizer
{
    public function __construct(private readonly ArabicNormalizer $normalizer) {}

    /**
     * @param  list<array{option_index: int, raw_text: string}>  $options  already indexed by OptionIndexDeriver
     */
    public function build(array $options): string
    {
        $sorted = $options;
        usort(
            $sorted,
            static fn (array $a, array $b): int => $a['option_index'] <=> $b['option_index']
        );

        $normalized = array_map(
            fn (array $option): string => $this->normalizer->search($this->normalizer->clean($option['raw_text'])),
            $sorted
        );

        return implode("\n", $normalized);
    }
}
