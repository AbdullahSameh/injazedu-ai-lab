<?php

namespace App\Support\Health;

use App\Support\SourceReader;

final class SourceQuestionsCheck extends AbstractHealthCheck
{
    public function __construct(private readonly SourceReader $source) {}

    public function number(): int
    {
        return 8;
    }

    public function name(): string
    {
        return 'InjazEdu source';
    }

    public function target(): string
    {
        return 'injazedu.questions';
    }

    public function expectation(): string
    {
        return CheckResult::MUST_SUCCEED;
    }

    public function run(): CheckResult
    {
        $snapshot = config('lab.snapshot_taken_at');
        if (! is_string($snapshot) || $snapshot === '') {
            return $this->fail('injazedu.questions count cannot be reported without snapshot_taken_at');
        }

        $count = $this->source->count('questions');

        return $this->pass("injazedu.questions count={$count}, snapshot_taken_at={$snapshot}");
    }
}
