<?php

namespace App\Support\Health;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\SourceReader;

final class ForbiddenTableCheck extends AbstractHealthCheck
{
    public function __construct(private readonly SourceReader $source) {}

    public function number(): int
    {
        return 10;
    }

    public function name(): string
    {
        return 'Forbidden table';
    }

    public function target(): string
    {
        return 'injazedu.users';
    }

    public function expectation(): string
    {
        return CheckResult::MUST_BE_REFUSED;
    }

    public function run(): CheckResult
    {
        try {
            $this->source->table('users');
        } catch (SourceTableNotAllowed $exception) {
            return $this->pass('Refused users via SourceTableNotAllowed: '.$exception->getMessage());
        }

        return $this->fail('SourceReader accepted forbidden table users');
    }
}
