<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;

/**
 * `courses` → `source_courses` — metadata only (FR-012, data-model.md §2).
 * `selectColumns()` is the review step: `price`, `discount`, `description`
 * (NOT NULL in the source — notes N7), `course_conditions`, `meta_*`,
 * `image`, `poster`, `schedule`, `intro`, `live_days`, `live_time`,
 * `expire_duration`, `start_date_hijri` and `sorte_order` are never
 * selected, so they cannot leak in even by accident.
 */
final class ImportCourses extends BankImportJob
{
    protected function sourceTable(): string
    {
        return 'courses';
    }

    protected function mirrorTable(): string
    {
        return 'source_courses';
    }

    protected function selectColumns(): array
    {
        return [
            'id', 'name', 'slug', 'category_id', 'status', 'start_date', 'exam_date',
            'telegram_channel', 'telegram_group', 'telegram_private',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    protected function mapAttributes(object $row): array
    {
        $content = [
            'name' => $row->name,
            'slug' => $row->slug,
            'category_source_id' => $row->category_id,
            'status' => (bool) $row->status,
            'start_date' => $row->start_date,
            'exam_date' => $row->exam_date,
            'telegram_channel' => $row->telegram_channel,
            'telegram_group' => $row->telegram_group,
            'telegram_private' => $row->telegram_private,
        ];

        return $content + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}
