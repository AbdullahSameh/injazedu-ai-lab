<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FR-024 / SC-005: no column in the Lab schema is named email, phone, mobile,
 * id_number, national_id, or name on a behavioural table.
 *
 * The Lab schema is read live from PostgreSQL's information_schema — the
 * assertion is about what actually exists, not what a migration file claims.
 *
 * The framework's own tables (`users`, `password_reset_tokens`, `sessions`,
 * `cache`, `jobs`, …) are operational, not behavioural — `users.email` is the
 * operator's own credential, `job_batches.name` is a queue label. Every table
 * the Lab itself adds — lab_job_probes today, the domain tables later phases
 * bring — must hold no column that could carry personal data.
 */
class NoPiiInLabSchemaTest extends TestCase
{
    /** Framework operational tables, none of them behavioural. */
    private const FRAMEWORK_TABLES = [
        'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'migrations',
    ];

    /** Column names that must never appear on a behavioural table. */
    private const FORBIDDEN_COLUMNS = ['email', 'phone', 'mobile', 'id_number', 'national_id', 'name'];

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml points DB_DATABASE at :memory: for the sqlite default;
        // the Lab schema lives in the real pgsql database, so restore it.
        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_no_behavioural_table_has_a_pii_column(): void
    {
        $columns = DB::connection('pgsql')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('column_name', self::FORBIDDEN_COLUMNS)
            ->whereNotIn('table_name', self::FRAMEWORK_TABLES)
            ->pluck('table_name', 'column_name');

        $this->assertEmpty(
            $columns->all(),
            'PII-capable columns found on behavioural tables: '
            .json_encode($columns->all())
        );
    }
}
