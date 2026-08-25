<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\DB;

final class LabDatabaseCheck extends AbstractHealthCheck
{
    public function number(): int
    {
        return 1;
    }

    public function name(): string
    {
        return 'Lab database';
    }

    public function target(): string
    {
        return 'postgres:5433';
    }

    public function expectation(): string
    {
        return CheckResult::MUST_SUCCEED;
    }

    public function run(): CheckResult
    {
        DB::connection()->selectOne('SELECT 1');

        return $this->pass('postgres:5433 answered SELECT 1');
    }
}
