<?php

namespace App\Support\Derive;

/**
 * The mechanical half of `source_questions`' answer key (data-model.md §2,
 * FR-016). `correct_option_ids` and `correct_option_count` are counted from
 * live (non-soft-deleted) options with `points > 0` and are always returned.
 *
 * `answer_key_state` is an interpretation, not a count: it stays `pending`
 * here regardless of `correct_option_count`. Setting it to `single_correct`,
 * `broken_no_key` or `multi_key` under the recorded multi-key policy is the
 * backfill pass's job (T062), gated on that decision — this class never
 * guesses (FR-061, SC-020).
 */
final class AnswerKeyDeriver
{
    /**
     * @param  list<array{id: int, points: int, deleted_at: ?string}>  $options
     * @return array{correct_option_ids: list<int>, correct_option_count: int, answer_key_state: string}
     */
    public function derive(array $options): array
    {
        $liveOptions = array_values(array_filter(
            $options,
            static fn (array $option): bool => empty($option['deleted_at'])
        ));

        $correctOptionIds = array_values(array_map(
            static fn (array $option): int => $option['id'],
            array_filter(
                $liveOptions,
                static fn (array $option): bool => ($option['points'] ?? 0) > 0
            )
        ));

        return [
            'correct_option_ids' => $correctOptionIds,
            'correct_option_count' => count($correctOptionIds),
            'answer_key_state' => 'pending',
        ];
    }
}
