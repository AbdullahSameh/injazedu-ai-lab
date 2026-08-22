<?php

namespace App\Support\Health;

interface HealthCheck
{
    public function number(): int;

    public function name(): string;

    public function target(): string;

    public function expectation(): string;

    public function run(): CheckResult;
}
