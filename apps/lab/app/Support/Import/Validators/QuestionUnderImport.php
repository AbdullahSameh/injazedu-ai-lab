<?php

namespace App\Support\Import\Validators;

/**
 * One question as the import sees it, with the option set it was read
 * alongside — the subject of the ten question-level checks.
 *
 * The options are the **source** rows, soft-deleted ones included, exactly
 * as `QuestionOptionsFetcher` grouped them. Each check decides for itself
 * whether deleted options count: `MISSING_OPTIONS` asks about live ones,
 * because a question whose only options were deleted is unanswerable today,
 * while `option_index` numbers the physical sequence including them.
 */
final readonly class QuestionUnderImport
{
    /**
     * @param  list<array<string, mixed>>  $options  source `options` rows, deleted included
     */
    public function __construct(
        public int $sourceId,
        public ?int $sectionSourceId,
        public ?string $rawText,
        public array $options,
        public bool $isSoftDeleted = false,
    ) {}

    /** @return list<array<string, mixed>> */
    public function liveOptions(): array
    {
        return array_values(array_filter(
            $this->options,
            static fn (array $option): bool => empty($option['deleted_at'])
        ));
    }

    /** The stem with tags removed — what a reader is actually left with. */
    public function strippedText(): string
    {
        return trim(strip_tags((string) $this->rawText));
    }

    public function hasImage(): bool
    {
        return str_contains((string) $this->rawText, '<img');
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['soft_deleted' => $this->isSoftDeleted];
    }
}
