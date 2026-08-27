<?php

namespace Tests\Unit\Validators;

use App\Support\Import\ImportErrorCode;
use App\Support\Import\Validators\ValidationSuite;
use PHPUnit\Framework\TestCase;

/**
 * FR-042 says thirteen checks run during import, and FR-044 says the codes
 * live in one enumeration. Between those two there is a gap only a test can
 * close: a code can exist in the enum with no check behind it, or a check
 * can exist and be wired nowhere. Either way the console shows a category
 * that can never fill, or an anomaly goes unrecorded — and neither shows up
 * as a failure anywhere else.
 */
class ValidationSuiteTest extends TestCase
{
    public function test_every_code_in_the_enum_has_a_check_behind_it(): void
    {
        $declared = array_map(fn (ImportErrorCode $c): string => $c->value, ImportErrorCode::cases());
        $wired = array_map(fn (ImportErrorCode $c): string => $c->value, ValidationSuite::wiredCodes());

        sort($declared);
        sort($wired);

        $this->assertSame($declared, $wired);
        $this->assertCount(13, $wired, 'FR-042 names thirteen checks.');
    }

    public function test_the_wired_list_matches_what_the_factories_actually_build(): void
    {
        $built = array_merge(
            ValidationSuite::forQuestions([]),
            ValidationSuite::forSections([], []),
            ValidationSuite::forCategories([]),
        );

        $this->assertCount(13, $built, 'A check was declared wired but is not constructed by any factory.');
    }

    public function test_every_code_carries_a_severity_and_a_description(): void
    {
        foreach (ImportErrorCode::cases() as $code) {
            $this->assertContains($code->severity(), ['error', 'warning']);
            $this->assertNotSame('', trim($code->description()));
        }
    }

    public function test_zero_correct_is_an_error_because_it_affects_a_student_now(): void
    {
        // FR-043 names this one specifically; it is the reason severity exists.
        $this->assertSame('error', ImportErrorCode::ZERO_CORRECT->severity());
    }
}
