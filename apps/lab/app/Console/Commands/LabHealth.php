<?php

namespace App\Console\Commands;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthMatrix;
use Illuminate\Console\Command;

final class LabHealth extends Command
{
    protected $signature = 'lab:health';

    protected $description = 'Run the ten Lab connectivity and guardrail checks';

    public function handle(HealthMatrix $matrix): int
    {
        $results = $matrix->run();

        $this->table(
            ['#', 'Check', 'Target', 'Expectation', 'Outcome', 'Detail'],
            array_map(fn (CheckResult $result) => [
                $result->number,
                $result->name,
                $result->target,
                $result->expectation,
                strtoupper($result->outcome),
                $result->detail,
            ], $results),
        );

        return collect($results)->every(
            fn (CheckResult $result) => $result->outcome === CheckResult::PASS
        ) ? self::SUCCESS : self::FAILURE;
    }
}
