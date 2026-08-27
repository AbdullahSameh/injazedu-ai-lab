<?php

namespace App\Support\Derive;

/**
 * `payload_hash` — SHA256 over a key-sorted JSON serialization (FR-018,
 * data-model.md §1). One mechanism, one exception:
 *
 *  - Every table hashes its own copied columns: pass them to `hash()`.
 *  - `source_questions` alone uses §16's definition verbatim — `name`,
 *    `description`, `hint`, and its live options **ordered by
 *    `option_index`**, each reduced to `name` and `points` — so editing an
 *    option changes the question's hash. `hashQuestion()` re-sorts its
 *    options by `option_index` before hashing, so the caller's input order
 *    never matters.
 */
final class PayloadHasher
{
    public function hash(array $columns): string
    {
        return hash('sha256', $this->canonicalize($columns));
    }

    /**
     * @param  list<array{option_index: int, name: string, points: int}>  $options
     */
    public function hashQuestion(?string $name, ?string $description, ?string $hint, array $options): string
    {
        $ordered = $options;
        usort($ordered, static fn (array $a, array $b): int => $a['option_index'] <=> $b['option_index']);

        $ordered = array_map(
            static fn (array $option): array => [
                'name' => $option['name'],
                'points' => $option['points'],
            ],
            $ordered
        );

        return $this->hash([
            'name' => $name,
            'description' => $description,
            'hint' => $hint,
            'options' => $ordered,
        ]);
    }

    private function canonicalize(mixed $value): string
    {
        return json_encode(
            $this->sortRecursively($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursively($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortRecursively($item), $value);
    }
}
