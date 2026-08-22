<?php

namespace Tests\Unit;

use App\Support\Health\CheckResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CheckResultTest extends TestCase
{
    public function test_it_exposes_the_complete_health_result_shape(): void
    {
        $result = new CheckResult(
            number: 9,
            name: 'Source write attempt',
            target: 'injazedu',
            expectation: CheckResult::MUST_BE_REFUSED,
            outcome: CheckResult::PASS,
            detail: 'Refused by ReadOnlyViolation',
        );

        $this->assertSame([
            'number' => 9,
            'name' => 'Source write attempt',
            'target' => 'injazedu',
            'expectation' => 'must_be_refused',
            'outcome' => 'pass',
            'detail' => 'Refused by ReadOnlyViolation',
        ], $result->toArray());
    }

    public function test_it_rejects_an_unknown_expectation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CheckResult(1, 'Name', 'target', 'sometimes', 'pass', 'detail');
    }

    public function test_it_rejects_an_unknown_outcome(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CheckResult(1, 'Name', 'target', 'must_succeed', 'unknown', 'detail');
    }
}
