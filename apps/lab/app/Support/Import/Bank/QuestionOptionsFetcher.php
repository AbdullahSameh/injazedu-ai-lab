<?php

namespace App\Support\Import\Bank;

use App\Support\Derive\OptionIndexDeriver;
use App\Support\SourceReader;

/**
 * The one place both `ImportQuestions` and `ImportQuestionOptions` get a
 * question's options from, so the two jobs can never derive a different
 * `option_index` for the same option (FR-017). Groups the full `options`
 * table — soft-deleted rows included, since `option_index` numbers the
 * table's own physical (order, id) sequence, not a rendering-time subset
 * (notes N6) — by `question_id`, each option already carrying its derived
 * `option_index`.
 */
final class QuestionOptionsFetcher
{
    public function __construct(private readonly SourceReader $source) {}

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function grouped(): array
    {
        // 124,549 rows (2026-08-07 snapshot) held as one PHP structure for
        // the whole pass, twice per bank run (ImportQuestions and
        // ImportQuestionOptions each call this independently) — comfortably
        // past the 128M CLI/worker default. Bank tables are small enough
        // overall that this project chose one pass over chunking (see
        // BankImportJob); this is the one place that pass is heavy enough
        // to need the room. -1 means "already unlimited" — leave it alone.
        if (ini_get('memory_limit') !== '-1') {
            ini_set('memory_limit', '512M');
        }

        $byQuestion = [];

        foreach ($this->source->table('options')
            ->select(['id', 'question_id', 'name', 'points', 'order', 'created_at', 'updated_at', 'deleted_at'])
            ->orderBy('question_id')
            ->orderBy('order')
            ->orderBy('id')
            ->cursor() as $row) {
            $byQuestion[$row->question_id][] = (array) $row;
        }

        $deriver = new OptionIndexDeriver;

        foreach ($byQuestion as $questionId => $options) {
            $byQuestion[$questionId] = $deriver->derive($options);
        }

        return $byQuestion;
    }
}
