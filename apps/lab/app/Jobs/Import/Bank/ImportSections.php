<?php

namespace App\Jobs\Import\Bank;

use App\Support\Derive\PayloadHasher;
use App\Support\Import\ImportErrorRecorder;
use App\Support\Import\Validators\SectionCheck;
use App\Support\Import\Validators\SectionUnderImport;
use App\Support\Import\Validators\ValidationSuite;
use App\Support\SourceReader;

/**
 * `sections` → `source_sections` — where the shared stimulus lives (§8,
 * data-model.md §2). `description` is absent from the source app's own
 * `Section::$fillable`, but the column exists and may be populated: this
 * job reads it directly through the query builder, which is not subject to
 * that model's mass-assignment guard — never assume it is empty.
 *
 * `questions_count` is a second pass, after `source_questions` exists
 * (T060) — left at its column default (0) here.
 *
 * Two checks run here: `ORPHAN_QUIZ` and `STIMULUS_NO_QUESTIONS`. Both need
 * a table this pass does not own, and this pass runs before
 * `ImportQuestions`, so both lookups are read from the **source** once
 * rather than from the mirror — which is also what keeps them correct under
 * `--resume`, where the mirror holds only part of the bank.
 */
final class ImportSections extends BankImportJob
{
    private const LONG_STIMULUS_THRESHOLD = 200;

    protected function sourceTable(): string
    {
        return 'sections';
    }

    protected function mirrorTable(): string
    {
        return 'source_sections';
    }

    protected function selectColumns(): array
    {
        return ['id', 'quiz_id', 'name', 'description', 'order', 'created_at', 'updated_at', 'deleted_at'];
    }

    /** @var list<SectionCheck> */
    private array $checks = [];

    protected function prepareChecks(SourceReader $source): void
    {
        $this->checks = ValidationSuite::forSections(
            array_fill_keys($source->table('quizzes')->pluck('id')->all(), true),
            array_fill_keys(
                $source->table('questions')
                    ->whereNull('deleted_at')
                    ->whereNotNull('section_id')
                    ->distinct()
                    ->pluck('section_id')
                    ->all(),
                true
            ),
        );
    }

    protected function validate(object $row, ImportErrorRecorder $errors): void
    {
        $section = new SectionUnderImport(
            sourceId: (int) $row->id,
            quizSourceId: $row->quiz_id === null ? null : (int) $row->quiz_id,
            stimulusRaw: $row->description,
            isSoftDeleted: $row->deleted_at !== null,
        );

        foreach ($this->checks as $check) {
            if ($finding = $check->check($section)) {
                $errors->recordFinding($finding);
            }
        }
    }

    protected function mapAttributes(object $row): array
    {
        $stimulusRaw = $row->description;
        $stimulusLength = $stimulusRaw === null ? 0 : mb_strlen($stimulusRaw);

        $content = [
            'quiz_source_id' => $row->quiz_id,
            'name' => $row->name,
            'order' => $row->order,
        ];

        $derived = [
            'stimulus_raw' => $stimulusRaw,
            'stimulus_length' => $stimulusLength,
            'has_stimulus' => $stimulusLength > 0,
            'is_long_stimulus' => $stimulusLength > self::LONG_STIMULUS_THRESHOLD,
        ];

        return $content + $derived + ['payload_hash' => (new PayloadHasher)->hash($content)];
    }
}
