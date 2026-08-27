<?php

namespace App\Support\Import\Validators;

/**
 * One section as the import sees it — the subject of the two
 * structure-level checks that belong to `sections`.
 */
final readonly class SectionUnderImport
{
    public function __construct(
        public int $sourceId,
        public ?int $quizSourceId,
        public ?string $stimulusRaw,
        public bool $isSoftDeleted = false,
    ) {}

    public function hasStimulus(): bool
    {
        return trim((string) $this->stimulusRaw) !== '';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['soft_deleted' => $this->isSoftDeleted];
    }
}
