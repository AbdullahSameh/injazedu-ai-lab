<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;

/**
 * `lectures` → `source_lectures` — title and order only (data-model.md §2).
 * `selectColumns()` is the review step: `zoom_start_url`, `zoom_join_url`,
 * `meeting_id`, `passcode`, `meeting_type`, `vimeo_id`, `bunny_id`,
 * `youtube_id`, `upload_status`, `upload_error`, `host`, `live`, `book`,
 * `start_time`, `start_date_hijri` and `duration` are never selected —
 * some are credentials, none is about a question.
 */
final class ImportLectures extends BankImportJob
{
    protected function sourceTable(): string
    {
        return 'lectures';
    }

    protected function mirrorTable(): string
    {
        return 'source_lectures';
    }

    protected function selectColumns(): array
    {
        return ['id', 'topic', 'sorte_order', 'chapter_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    protected function mapAttributes(object $row): array
    {
        $content = [
            'topic' => $row->topic,
            'sort_order' => $row->sorte_order,
            'chapter_source_id' => $row->chapter_id,
        ];

        return $content + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}
