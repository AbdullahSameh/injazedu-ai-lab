<?php

namespace Tests\Unit;

use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use App\Support\Health\HealthMatrix;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HealthMatrixTest extends TestCase
{
    public function test_it_runs_every_check_in_fixed_numeric_order_even_after_a_failure(): void
    {
        $ran = [];
        $matrix = new HealthMatrix([
            $this->check(2, $ran),
            $this->check(1, $ran, throws: true),
            $this->check(3, $ran),
        ]);

        $results = $matrix->run();

        $this->assertSame([1, 2, 3], $ran);
        $this->assertSame([1, 2, 3], array_map(fn (CheckResult $result) => $result->number, $results));
        $this->assertSame(CheckResult::FAIL, $results[0]->outcome);
        $this->assertStringContainsString('target-1', $results[0]->detail);
        $this->assertStringContainsString('expected failure', $results[0]->detail);
    }

    private function check(int $number, array &$ran, bool $throws = false): HealthCheck
    {
        return new class($number, $ran, $throws) implements HealthCheck
        {
            public function __construct(
                private readonly int $checkNumber,
                private array &$ran,
                private readonly bool $throws,
            ) {}

            public function number(): int
            {
                return $this->checkNumber;
            }

            public function name(): string
            {
                return "Check {$this->checkNumber}";
            }

            public function target(): string
            {
                return "target-{$this->checkNumber}";
            }

            public function expectation(): string
            {
                return CheckResult::MUST_SUCCEED;
            }

            public function run(): CheckResult
            {
                $this->ran[] = $this->checkNumber;

                if ($this->throws) {
                    throw new RuntimeException('expected failure');
                }

                return new CheckResult(
                    $this->number(),
                    $this->name(),
                    $this->target(),
                    $this->expectation(),
                    CheckResult::PASS,
                    'ok',
                );
            }
        };
    }
}
