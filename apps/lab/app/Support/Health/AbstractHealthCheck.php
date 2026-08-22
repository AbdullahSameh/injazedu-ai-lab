<?php

namespace App\Support\Health;

abstract class AbstractHealthCheck implements HealthCheck
{
    protected function pass(string $detail): CheckResult
    {
        return $this->result(CheckResult::PASS, $detail);
    }

    protected function fail(string $detail): CheckResult
    {
        return $this->result(CheckResult::FAIL, $detail);
    }

    private function result(string $outcome, string $detail): CheckResult
    {
        return new CheckResult(
            number: $this->number(),
            name: $this->name(),
            target: $this->target(),
            expectation: $this->expectation(),
            outcome: $outcome,
            detail: $detail,
        );
    }
}
