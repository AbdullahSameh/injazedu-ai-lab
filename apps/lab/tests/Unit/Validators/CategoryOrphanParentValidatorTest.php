<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\CategoryOrphanParentValidator;
use App\Support\Import\Validators\CategoryUnderImport;
use PHPUnit\Framework\TestCase;

class CategoryOrphanParentValidatorTest extends TestCase
{
    public function test_it_flags_a_parent_that_does_not_exist(): void
    {
        $category = new CategoryUnderImport(sourceId: 7, parentSourceId: 99);
        $finding = (new CategoryOrphanParentValidator([1 => true, 7 => true]))->check($category);

        $this->assertNotNull($finding);
        $this->assertSame(ImportErrorCode::CATEGORY_ORPHAN_PARENT, $finding->code);
        $this->assertSame('categories', $finding->sourceTable);
        $this->assertSame(99, $finding->context['parent_source_id']);

        // FR-046: the tree is shown incomplete and honest, never re-parented.
        $this->assertSame(99, $category->parentSourceId);
    }

    public function test_a_root_category_is_not_an_orphan(): void
    {
        // 10 of the 43 source categories have a NULL parent_id.
        $this->assertNull((new CategoryOrphanParentValidator([1 => true]))->check(
            new CategoryUnderImport(sourceId: 7, parentSourceId: null)
        ));
    }

    public function test_it_passes_a_category_whose_parent_exists(): void
    {
        $this->assertNull((new CategoryOrphanParentValidator([1 => true]))->check(
            new CategoryUnderImport(sourceId: 7, parentSourceId: 1)
        ));
    }
}
