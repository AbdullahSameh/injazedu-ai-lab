<?php

namespace App\Support\Import\Validators;

use App\Support\Import\ImportErrorCode;

/**
 * `CATEGORY_ORPHAN_PARENT` (warning) — `parent_id` points at a category that
 * does not exist, so the tree has a branch hanging from nothing.
 *
 * Never repaired and never re-parented (FR-046, data-model.md §2): the tree
 * is shown incomplete and honest rather than complete and guessed. The
 * source has no foreign key here and the column is an INT against a BIGINT
 * UNSIGNED id, so nothing ever stopped it.
 *
 * `ImportCategories` is the **first** bank job, so the known-category set
 * cannot come from the mirror — it is read from the source, all 43 ids, at
 * the start of the pass.
 *
 * Zero rows on the fixed snapshot; 10 of the 43 categories are roots with a
 * NULL parent, which is not an orphan.
 */
final class CategoryOrphanParentValidator implements CategoryCheck
{
    /** @param  array<int, true>  $knownCategoryIds  keyed by id for O(1) lookup */
    public function __construct(private readonly array $knownCategoryIds) {}

    public function check(CategoryUnderImport $category): ?Finding
    {
        if ($category->parentSourceId === null || isset($this->knownCategoryIds[$category->parentSourceId])) {
            return null;
        }

        return new Finding(
            ImportErrorCode::CATEGORY_ORPHAN_PARENT,
            'categories',
            $category->sourceId,
            'parent_id points at a category that does not exist.',
            $category->context() + ['parent_source_id' => $category->parentSourceId],
        );
    }
}
