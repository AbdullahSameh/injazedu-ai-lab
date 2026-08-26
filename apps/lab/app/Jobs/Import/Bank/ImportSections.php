<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;

/**
 * `sections` → `source_sections` — where the shared stimulus lives (§8,
 * data-model.md §2). `description` is absent from the source app's own
 * `Section::$fillable`, but the column exists and may be populated: this
 * job reads it directly through the query builder, which is not subject to
 * that model's mass-assignment guard — never assume it is empty.
 *
 * `questions_count` is a second pass, after `source_questions` exists
 * (T060) — left at its column default (0) here.
 */
final class ImportSections extends BankImportJob
{
    private const LONG_STIMULUS_THRESHOLD = 200;

    protected function sourceTable(): string
    {
        return 'sections';
    }

    protected function mirrorTable(): string
    {
        return 'source_sections';
    }

    protected function selectColumns(): array
    {
        return ['id', 'quiz_id', 'name', 'description', 'order', 'created_at', 'updated_at', 'deleted_at'];
    }

    protected function mapAttributes(object $row): array
    {
        $stimulusRaw = $row->description;
        $stimulusLength = $stimulusRaw === null ? 0 : mb_strlen($stimulusRaw);

        $content = [
            'quiz_source_id' => $row->quiz_id,
            'name' => $row->name,
            'order' => $row->order,
        ];

        $derived = [
            'stimulus_raw' => $stimulusRaw,
            'stimulus_length' => $stimulusLength,
            'has_stimulus' => $stimulusLength > 0,
            'is_long_stimulus' => $stimulusLength > self::LONG_STIMULUS_THRESHOLD,
        ];

        return $content + $derived + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}
