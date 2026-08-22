<?php

namespace Tests\Feature;

use App\Support\Health\CheckResult;
use App\Support\Health\ForbiddenTableCheck;
use App\Support\Health\SourceWriteCheck;
use Tests\TestCase;

class HealthGuardrailChecksTest extends TestCase
{
    public function test_source_write_check_passes_only_because_the_guard_refuses_valid_sql(): void
    {
        $result = app(SourceWriteCheck::class)->run();

        $this->assertSame(CheckResult::MUST_BE_REFUSED, $result->expectation);
        $this->assertSame(CheckResult::PASS, $result->outcome);
        $this->assertStringContainsString('ReadOnlyViolation', $result->detail);
    }

    public function test_forbidden_table_check_passes_only_because_users_is_refused_by_name(): void
    {
        $result = app(ForbiddenTableCheck::class)->run();

        $this->assertSame(CheckResult::MUST_BE_REFUSED, $result->expectation);
        $this->assertSame(CheckResult::PASS, $result->outcome);
        $this->assertStringContainsString('users', $result->detail);
    }
}
