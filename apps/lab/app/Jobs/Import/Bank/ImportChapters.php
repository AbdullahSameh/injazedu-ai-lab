<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;

/** `chapters` → `source_chapters` — title and order only (data-model.md §2). */
final class ImportChapters extends BankImportJob
{
    protected function sourceTable(): string
    {
        return 'chapters';
    }

    protected function mirrorTable(): string
    {
        return 'source_chapters';
    }

    protected function selectColumns(): array
    {
        return ['id', 'title', 'sorte_order', 'course_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    protected function mapAttributes(object $row): array
    {
        $content = [
            'title' => $row->title,
            'sort_order' => $row->sorte_order,
            'course_source_id' => $row->course_id,
        ];

        return $content + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}
