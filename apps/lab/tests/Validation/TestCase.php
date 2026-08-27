<?php

namespace Tests\Validation;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base for the MirrorValidation suite — tests that intentionally read the
 * real, live injazedu_lab database (never injazedu_lab_test). Deliberately
 * does NOT extend Tests\TestCase and does NOT use RefreshDatabase: this
 * suite must never be structurally able to trigger a migrate or refresh
 * against any database.
 *
 * Every subclass repoints the pgsql connection to injazedu_lab itself in
 * setUp() and must keep any write inside a transaction it rolls back (see
 * ImportIdempotencyTest, ImportResumeTest, ImportQueueParityTest) — this
 * suite runs only via `composer test:mirror`, never `composer test`
 * (phpunit.xml's MirrorValidation testsuite, excluded from the default
 * Unit,Feature run).
 */
abstract class TestCase extends BaseTestCase
{
    //
}
