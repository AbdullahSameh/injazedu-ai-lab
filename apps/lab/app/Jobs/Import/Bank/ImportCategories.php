<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;
use App\Support\Import\ImportErrorRecorder;
use App\Support\Import\Validators\CategoryCheck;
use App\Support\Import\Validators\CategoryUnderImport;
use App\Support\Import\Validators\ValidationSuite;
use App\Support\SourceReader;

/**
 * `categories` → `source_categories` (FR-009, FR-015, data-model.md §2).
 * `parent_id` is copied as-is into `parent_source_id` — an INT against a
 * BIGINT UNSIGNED `id`, no FK either side — orphans are recorded by
 * `CATEGORY_ORPHAN_PARENT` and never repaired here (FR-046).
 *
 * This is the **first** bank job, so the set of category ids that exist
 * cannot come from the mirror; all 43 are read from the source once, before
 * the pass.
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

    /** @var list<CategoryCheck> */
    private array $checks = [];

    protected function prepareChecks(SourceReader $source): void
    {
        $this->checks = ValidationSuite::forCategories(
            array_fill_keys($source->table('categories')->pluck('id')->all(), true)
        );
    }

    protected function validate(object $row, ImportErrorRecorder $errors): void
    {
        $category = new CategoryUnderImport(
            sourceId: (int) $row->id,
            parentSourceId: $row->parent_id === null ? null : (int) $row->parent_id,
            isSoftDeleted: $row->deleted_at !== null,
        );

        foreach ($this->checks as $check) {
            if ($finding = $check->check($category)) {
                $errors->recordFinding($finding);
            }
        }
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
