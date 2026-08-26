<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;

/**
 * `categories` → `source_categories` (FR-009, FR-015, data-model.md §2).
 * `parent_id` is copied as-is into `parent_source_id` — an INT against a
 * BIGINT UNSIGNED `id`, no FK either side — orphans and cycles are a
 * validator's business (Phase 5), never repaired here.
 *
 * Not copied: `meta_title`, `meta_description`, `courses_card`,
 * `quizzes_card`, `events_card`, `mobile_image`.
 */
final class ImportCategories extends BankImportJob
{
    protected function sourceTable(): string
    {
        return 'categories';
    }

    protected function mirrorTable(): string
    {
        return 'source_categories';
    }

    protected function selectColumns(): array
    {
        return ['id', 'name', 'slug', 'sorte_order', 'parent_id', 'image', 'created_at', 'updated_at', 'deleted_at'];
    }

    protected function mapAttributes(object $row): array
    {
        $content = [
            'name' => $row->name,
            'slug' => $row->slug,
            'sort_order' => $row->sorte_order,
            'parent_source_id' => $row->parent_id,
            'image' => $row->image,
        ];

        return $content + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}
