<?php

namespace Tests\Feature;

use App\Exceptions\SourceTableNotAllowed;
use App\Support\Import\BatchUpsert;
use App\Support\SourceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FR-026 / FR-055 / SC-010 / SC-011: the copy guard is a tested property,
 * not a promise every job author has to remember.
 *
 * `BatchUpsert::run()` is the single write site every mirror job funnels
 * through (`BankImportJob::flush()`, `ImportQuestions`,
 * `ImportQuestionOptions`, `ImportResults` — grepping the ETL for
 * `->insert(`/`->create(`/`->save(` outside `BatchUpsert` itself turns up
 * nothing). `BackfillJob` writes directly, but it never copies — it rewrites
 * one column of a row already in the mirror from other mirror rows, so it
 * correctly calls neither guard. So proving the guard on `run()` proves it
 * everywhere copying happens.
 *
 * `orders` is the sharpest case (notes N7): it carries `customer_name`,
 * `customer_email`, `customer_phone`, is legitimately readable for query 15
 * (§6), and would be a serious leak if it were ever copyable.
 */
class CopyGuardTest extends TestCase
{
    private const PROBE = 'lab_copy_guard_probe';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('pgsql')->dropIfExists(self::PROBE);
        DB::connection('pgsql')->statement(sprintf(
            'CREATE TABLE %s (
                id BIGSERIAL PRIMARY KEY,
                source_system TEXT NOT NULL,
                source_id BIGINT NOT NULL,
                payload_hash CHAR(64) NOT NULL,
                UNIQUE (source_system, source_id)
            )', self::PROBE
        ));
    }

    protected function tearDown(): void
    {
        Schema::connection('pgsql')->dropIfExists(self::PROBE);

        parent::tearDown();
    }

    public function test_copying_a_profile_only_table_throws_naming_it_and_writes_nothing(): void
    {
        $row = [
            'source_system' => 'injazedu_production',
            'source_id' => 1,
            'payload_hash' => hash('sha256', 'x'),
        ];

        // If `assertCopyable()` were ever removed from `BatchUpsert::run()`,
        // this INSERT would succeed against a perfectly valid destination
        // table — nothing else would stop it — so the exception assertion
        // below is what actually fails, not a side-effect of a broken probe.
        try {
            app(BatchUpsert::class)->run('orders', self::PROBE, [$row]);
            $this->fail('BatchUpsert::run() copied a profile-only table instead of refusing it.');
        } catch (SourceTableNotAllowed $e) {
            $this->assertStringContainsString('orders', $e->getMessage());
        }

        $this->assertSame(
            0,
            DB::connection('pgsql')->table(self::PROBE)->count(),
            'A refused copy must leave the destination table untouched.'
        );
    }

    public function test_orders_is_still_readable_as_a_count(): void
    {
        // Reading and copying are different acts (SourceReader docblock):
        // the sharpest illustration is a table that is legitimately read
        // for query 15 and a leak if it were ever stored.
        $count = app(SourceReader::class)->count('orders');

        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
    }

    public function test_no_import_job_writes_to_the_mirror_outside_batch_upsert(): void
    {
        // A structural check alongside the runtime one above: nothing in the
        // copy-side ETL (bank + behavioural jobs) reaches Postgres through
        // any path but `BatchUpsert::run()`/`runDerived()`. `BackfillJob` is
        // excluded on purpose — it rewrites mirror rows from other mirror
        // rows and never copies from the source, so it correctly calls
        // neither guard (its own docblock says so).
        $etlFiles = array_merge(
            glob(app_path('Jobs/Import/Bank/*.php')) ?: [],
            glob(app_path('Jobs/Import/Behaviour/*.php')) ?: [],
        );

        $this->assertNotEmpty($etlFiles, 'Expected to find ETL job classes to scan.');

        $offenders = [];

        foreach ($etlFiles as $file) {
            $source = file_get_contents($file);

            if (preg_match('/->insert\(|->create\(|->save\(|->upsert\(/', $source)
                && ! str_contains($source, 'BatchUpsert')) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'ETL job(s) writing to the mirror without going through BatchUpsert: '.implode(', ', $offenders)
        );
    }
}
