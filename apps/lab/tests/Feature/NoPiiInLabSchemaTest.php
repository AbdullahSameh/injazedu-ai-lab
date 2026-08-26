<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FR-011 / FR-054 / SC-009: no non-framework Lab table carries a column that
 * could identify a student, and no behavioural table carries a free-text name.
 *
 * Operator decision (2026-08-26), plan.md Open Question 1, option (a):
 * `name` is allowed on content/metadata mirror tables — `source_categories`,
 * `source_courses`, `source_quizzes`, `source_sections` — because a category
 * or quiz title is not a person. It is hard-forbidden on behavioural tables
 * (`source_results` and the two derived statistics tables), where it has no
 * legitimate meaning.
 * `user_id` and nine other identity-shaped columns are hard-forbidden on
 * every non-framework table — `user_id` is the column FR-011 exists for and
 * the original test never checked it at all.
 *
 * The Lab schema is read live from PostgreSQL's information_schema — the
 * assertion is about what actually exists, not what a migration file claims.
 *
 * The framework's own tables (`users`, `password_reset_tokens`, `sessions`,
 * `cache`, `jobs`, …) are operational, not behavioural — `users.email` is the
 * operator's own credential, `job_batches.name` is a queue label. Every table
 * the Lab itself adds — lab_job_probes today, the mirror tables from Phase 4
 * on — must hold none of these columns outside the exemption above.
 */
class NoPiiInLabSchemaTest extends TestCase
{
    /** Framework operational tables, none of them behavioural. */
    private const FRAMEWORK_TABLES = [
        'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'migrations',
    ];

    /** Identity-shaped columns forbidden on every non-framework table, no exceptions. */
    private const ALWAYS_FORBIDDEN_COLUMNS = [
        'user_id', 'email', 'phone', 'mobile', 'id_number', 'national_id',
        'username', 'first_name', 'last_name', 'full_name',
    ];

    /**
     * Behavioural tables where a free-text `name` has no legitimate meaning.
     * `source_answers` was dropped 2026-08-26 (ADR-022) and replaced by the
     * two derived statistics tables, which are listed here for the same
     * reason: they hold counts about questions, never anything nameable.
     */
    private const BEHAVIOURAL_TABLES = [
        'source_results', 'source_item_stats', 'source_option_stats',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml points DB_DATABASE at :memory: for the sqlite default;
        // the Lab schema lives in the real pgsql database, so restore it.
        config(['database.connections.pgsql.database' => 'injazedu_lab']);
        DB::purge('pgsql');
    }

    public function test_no_non_framework_table_has_an_always_forbidden_column(): void
    {
        $columns = DB::connection('pgsql')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('column_name', self::ALWAYS_FORBIDDEN_COLUMNS)
            ->whereNotIn('table_name', self::FRAMEWORK_TABLES)
            ->pluck('table_name', 'column_name');

        $this->assertEmpty(
            $columns->all(),
            'Identity-shaped columns found on non-framework tables: '
            .json_encode($columns->all())
        );
    }

    public function test_no_behavioural_table_has_a_name_column(): void
    {
        $columns = DB::connection('pgsql')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'name')
            ->whereIn('table_name', self::BEHAVIOURAL_TABLES)
            ->pluck('table_name', 'column_name');

        $this->assertEmpty(
            $columns->all(),
            'A name column was found on a behavioural table: '
            .json_encode($columns->all())
        );
    }
}
