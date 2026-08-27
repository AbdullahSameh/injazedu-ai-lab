<?php

namespace Tests\Unit\Validators;

use App\Support\Import\Validators\QuestionUnderImport;
use PHPUnit\Framework\TestCase;

/**
 * Builders for the thirteen validator tests (T071, FR-042). No database:
 * every check is a pure function of one row and its options, which is what
 * makes thirteen tests cheap enough to write one per code.
 *
 * Each test asserts three things, and the third is the one FR-046 is about:
 * the anomaly is detected, a healthy row is left alone, and **the subject
 * comes back unchanged** — no check may repair, normalize or drop a row.
 * The DTOs are `readonly`, so a check that tried would not compile; the
 * assertion is there so that stays true if they ever stop being readonly.
 */
abstract class ValidatorTestCase extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $options
     */
    protected function question(
        array $options = [],
        ?string $rawText = 'What is 2 + 2?',
        ?int $sectionId = 10,
        int $sourceId = 1,
        bool $softDeleted = false,
    ): QuestionUnderImport {
        return new QuestionUnderImport(
            sourceId: $sourceId,
            sectionSourceId: $sectionId,
            rawText: $rawText,
            options: $options,
            isSoftDeleted: $softDeleted,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function option(
        int $id,
        string $name = 'Four',
        int $points = 0,
        int $order = 0,
        ?string $deletedAt = null,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'points' => $points,
            'order' => $order,
            'deleted_at' => $deletedAt,
        ];
    }

    /**
     * A healthy four-option question: one correct answer, distinct texts,
     * distinct `order` values. Every check must pass this untouched.
     *
     * @return list<array<string, mixed>>
     */
    protected function healthyOptions(): array
    {
        return [
            $this->option(1, 'Three', points: 0, order: 1),
            $this->option(2, 'Four', points: 1, order: 2),
            $this->option(3, 'Five', points: 0, order: 3),
            $this->option(4, 'Six', points: 0, order: 4),
        ];
    }

    /** FR-046: the check names the problem, it never touches the row. */
    protected function assertSubjectUnchanged(QuestionUnderImport $before, QuestionUnderImport $after): void
    {
        $this->assertSame($before->sourceId, $after->sourceId);
        $this->assertSame($before->rawText, $after->rawText);
        $this->assertSame($before->sectionSourceId, $after->sectionSourceId);
        $this->assertSame($before->options, $after->options);
    }
}
