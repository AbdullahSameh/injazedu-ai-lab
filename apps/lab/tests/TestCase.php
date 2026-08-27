<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * RefreshDatabase is safe here only because .env.testing pins the pgsql
 * connection to the disposable injazedu_lab_test database — never the real
 * injazedu_lab. Tests\Validation\TestCase deliberately does NOT extend this
 * class — it must never be able to trigger a migrate/refresh at all.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
}
