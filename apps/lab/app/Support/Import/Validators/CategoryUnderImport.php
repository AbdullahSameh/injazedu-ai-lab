<?php

namespace App\Support\Import\Validators;

/**
 * One category as the import sees it — the subject of the single check that
 * belongs to `categories`.
 */
final readonly class CategoryUnderImport
{
    public function __construct(
        public int $sourceId,
        public ?int $parentSourceId,
        public bool $isSoftDeleted = false,
    ) {}

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['soft_deleted' => $this->isSoftDeleted];
    }
}
