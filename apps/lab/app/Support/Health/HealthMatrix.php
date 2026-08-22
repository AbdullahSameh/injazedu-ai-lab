<?php

namespace App\Support\Health;

use Throwable;

final class HealthMatrix
{
    /** @param array<int, HealthCheck> $checks */
    public function __construct(
        private readonly array $checks,
        private readonly ?AiServiceRuntimeSnapshot $runtimeSnapshot = null,
    ) {}

    /** @return array<int, CheckResult> */
    public function run(): array
    {
        $this->runtimeSnapshot?->clear();

        $checks = $this->checks;
        usort($checks, fn (HealthCheck $left, HealthCheck $right) => $left->number() <=> $right->number());

        $results = [];
        foreach ($checks as $check) {
            try {
                $results[] = $check->run();
            } catch (Throwable $exception) {
                $results[] = new CheckResult(
                    number: $check->number(),
                    name: $check->name(),
                    target: $check->target(),
                    expectation: $check->expectation(),
                    outcome: CheckResult::FAIL,
                    detail: "{$check->target()}: {$exception->getMessage()}",
                );
            }
        }

        return $results;
    }
}
