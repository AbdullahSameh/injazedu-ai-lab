<?php

namespace App\Support\Health;

use App\Exceptions\ReadOnlyViolation;
use Illuminate\Support\Facades\DB;

final class SourceWriteCheck extends AbstractHealthCheck
{
    public function number(): int
    {
        return 9;
    }

    public function name(): string
    {
        return 'Source write attempt';
    }

    public function target(): string
    {
        return 'injazedu';
    }

    public function expectation(): string
    {
        return CheckResult::MUST_BE_REFUSED;
    }

    public function run(): CheckResult
    {
        try {
            DB::connection('injazedu')->insert(
                'INSERT INTO questions (id) SELECT id FROM questions WHERE 1 = 0'
            );
        } catch (ReadOnlyViolation $exception) {
            return $this->pass('Refused by ReadOnlyViolation: '.$exception->getMessage());
        }

        return $this->fail('injazedu accepted a valid INSERT through the guarded source connection');
    }
}
