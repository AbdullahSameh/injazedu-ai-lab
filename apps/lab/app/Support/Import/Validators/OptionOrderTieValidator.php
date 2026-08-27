<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `OPTION_ORDER_TIE` (warning) — two live options in one question share an
 * `order`, so the source does not determine what a student sees first.
 *
 * **Options only** (notes N6). `options.order` defaults to **0** and
 * therefore repeats constantly, which is §5.2's whole problem;
 * `sections.order` and `questions.order` default to 1 and their ties are not
 * defects and get no code. The two-key sort
 * (`` ORDER BY `order` ASC, id ASC ``) is applied to all three anyway,
 * because a stable order costs nothing and an unstable one is invisible
 * until it corrupts something downstream.
 *
 * **This fires on 29,075 of 29,142 questions** — very nearly the whole bank.
 * That is not a bug in the check; it is the measurement query 5 already
 * made, and the reason `option_index` is derived and stored rather than
 * recomputed by whoever needs it next. The code is a warning, and the
 * console groups by code, so the volume is the finding.
 */
final class OptionOrderTieValidator implements QuestionCheck
{
    public function check(QuestionUnderImport $question): ?Finding
    {
        $counts = [];

        foreach ($question->liveOptions() as $option) {
            $order = (int) ($option['order'] ?? 0);
            $counts[$order] = ($counts[$order] ?? 0) + 1;
        }

        $tied = array_keys(array_filter($counts, static fn (int $n): bool => $n > 1));

        if ($tied === []) {
            return null;
        }

        return new Finding(
            ImportErrorCode::OPTION_ORDER_TIE,
            'questions',
            $question->sourceId,
            sprintf('Options share %d repeated `order` value(s); display sequence is settled by id, not by the source.', count($tied)),
            $question->context() + ['tied_orders' => array_values($tied)],
        );
    }
}
