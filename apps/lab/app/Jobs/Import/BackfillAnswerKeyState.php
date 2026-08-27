<?php

namespace App\Jobs\Import;

use RuntimeException;

/**
 * `source_questions.answer_key_state` — the pass FR-061 gates on the
 * multi-key decision (data-model.md §2, SC-020).
 *
 * `correct_option_count` is mechanical and is set in the copy pass, because
 * counting is not interpreting. Naming the *state* is the interpretation,
 * and `AnswerKeyDeriver` returns `pending` for every question until a human
 * has said what more than one correct option means. This job is where that
 * answer is applied — and it is the only place `answer_key_state` is ever
 * written.
 *
 * **The decision, recorded 2026-08-26** from queries 3 and 4 (34 questions
 * above one correct option; 33 at 2, 1 at 4; 0.118% of active questions):
 * multi-key is a **data-entry error, not a supported question type**. A
 * valid question has exactly one correct option. That answer does not
 * change FR-016's mapping — it authorises it, and it fixes what `multi_key`
 * means downstream: a review flag, never an answerable item. Nothing is
 * repaired or deleted here; the mirror stays faithful and the questions
 * stay exactly as the trainers entered them.
 *
 * The mapping is therefore FR-016's, unconditional:
 *
 *   | `correct_option_count` | `answer_key_state` |
 *   |---|---|
 *   | 0   | `broken_no_key`  — copied, flagged, escalated, never answerable |
 *   | 1   | `single_correct` |
 *   | > 1 | `multi_key`      — a review flag under the recorded decision |
 *
 * `config('lab.import.multi_key_policy')` carries the decision, and an
 * absent one is a hard refusal rather than a default. That refusal is the
 * whole guarantee: "no question may leave `pending` on a guess" has to be
 * something the code cannot do, not something it is trusted not to do.
 *
 * Soft-deleted questions get a state too. It is derived from a count that
 * is already stored for every mirrored row, and withholding it would leave
 * `pending` meaning two different things — "not yet decided" and "deleted".
 * SC-020 asks that no *active* question stay pending; this satisfies that
 * and stops the state being ambiguous.
 */
final class BackfillAnswerKeyState extends BackfillJob
{
    protected function mirrorTable(): string
    {
        return 'source_questions';
    }

    protected function guard(): void
    {
        $policy = config('lab.import.multi_key_policy');

        if ($policy === null || $policy === '') {
            throw new RuntimeException(
                'No multi-key policy is recorded (FR-061). Set lab.import.multi_key_policy from '
                .'the profiling run before backfilling answer_key_state — a question must never '
                .'leave `pending` on a guess.'
            );
        }
    }

    protected function statement(): string
    {
        return <<<'SQL'
            UPDATE source_questions AS q
            SET answer_key_state = CASE
                WHEN q.correct_option_count = 0 THEN 'broken_no_key'
                WHEN q.correct_option_count = 1 THEN 'single_correct'
                ELSE 'multi_key'
            END
            WHERE q.answer_key_state IS DISTINCT FROM CASE
                WHEN q.correct_option_count = 0 THEN 'broken_no_key'
                WHEN q.correct_option_count = 1 THEN 'single_correct'
                ELSE 'multi_key'
            END
        SQL;
    }
}
