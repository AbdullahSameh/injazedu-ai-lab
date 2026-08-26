<?php

namespace App\Support\Derive;

/**
 * `option_index` — the gap-free, stable ordering the rest of this project
 * relies on (data-model.md §2, FR-017). `options.order` defaults to 0 and
 * repeats constantly (notes.md N6), so ties are resolved by `id` ascending:
 * `ORDER BY \`order\` ASC, id ASC`, never abbreviated to `order` alone.
 *
 * A/B/C/D letters do not exist in the source and are never stored — they
 * are synthesized from `option_index` at render time only.
 */
final class OptionIndexDeriver
{
    /**
     * @param  list<array{id: int, order: int}>  $options
     * @return list<array{id: int, order: int, option_index: int}>
     */
    public function derive(array $options): array
    {
        $sorted = $options;

        usort(
            $sorted,
            static fn (array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]
        );

        foreach ($sorted as $index => $option) {
            $sorted[$index]['option_index'] = $index;
        }

        return $sorted;
    }
}
