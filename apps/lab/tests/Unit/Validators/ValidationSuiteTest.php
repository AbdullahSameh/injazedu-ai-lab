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
 *
 * P2 (spec 006-p2-duplicate-intelligence) added three codes —
 * EMBEDDING_TRUNCATED, EMBEDDING_FAILED, VERDICT_FAILED — that are raised
 * directly by job-level try/catch around an HTTP call, not by a
 * declarative `ValidationSuite` check run over a row up front. They are
 * exempt from `wiredCodes()` by design, not by omission: this test still
 * pins the exemption list explicitly, so a fourteenth code with no check
 * and no exemption still fails loudly.
 */
class ValidationSuiteTest extends TestCase
{
    /** Raised by job-level error handling, never by a ValidationSuite check — see class docblock. */
    private const CODES_EXEMPT_FROM_A_WIRED_CHECK = [
        ImportErrorCode::EMBEDDING_TRUNCATED,
        ImportErrorCode::EMBEDDING_FAILED,
        ImportErrorCode::VERDICT_FAILED,
    ];

    public function test_every_p1_code_in_the_enum_has_a_check_behind_it(): void
    {
        $exempt = array_map(fn (ImportErrorCode $c): string => $c->value, self::CODES_EXEMPT_FROM_A_WIRED_CHECK);

        $declared = array_map(fn (ImportErrorCode $c): string => $c->value, ImportErrorCode::cases());
        $declared = array_values(array_diff($declared, $exempt));

        $wired = array_map(fn (ImportErrorCode $c): string => $c->value, ValidationSuite::wiredCodes());

        sort($declared);
        sort($wired);

        $this->assertSame($declared, $wired);
        $this->assertCount(13, $wired, 'FR-042 names thirteen checks.');
    }

    public function test_the_enum_holds_no_code_that_is_neither_wired_nor_explicitly_exempt(): void
    {
        $wired = array_map(fn (ImportErrorCode $c): string => $c->value, ValidationSuite::wiredCodes());
        $exempt = array_map(fn (ImportErrorCode $c): string => $c->value, self::CODES_EXEMPT_FROM_A_WIRED_CHECK);
        $accountedFor = [...$wired, ...$exempt];

        foreach (ImportErrorCode::cases() as $code) {
            $this->assertContains(
                $code->value,
                $accountedFor,
                "{$code->value} is neither wired to a ValidationSuite check nor listed in CODES_EXEMPT_FROM_A_WIRED_CHECK."
            );
        }
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
