<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FR-020 / SC-009: `user_id` never reaches Lab storage — not as a column,
 * not inside a JSONB payload, and not through a log line the ETL writes.
 *
 * `NoPiiInLabSchemaTest` already asserts `user_id` is absent from every
 * non-framework column; this test covers the two places a column-name scan
 * cannot see: the free-form `import_errors.context` JSONB payload, and
 * anything the ETL's own code could have written to the application log.
 * `ImportResults` reads and hashes `user_id` inside the same expression that
 * builds the row (data-model.md §2) — no named variable ever holds the raw
 * id, which is what makes both guarantees below hold by construction rather
 * than by discipline.
 */
class NoUserIdAnywhereTest extends TestCase
{
    public function test_no_import_error_context_carries_a_user_id_key(): void
    {
        // A JSONB column is invisible to information_schema — the only way
        // to find a leak here is to read the payloads themselves.
        $count = DB::connection('pgsql')
            ->table('import_errors')
            ->where('context', 'like', '%user_id%')
            ->count();

        $this->assertSame(
            0,
            $count,
            'Found an import_errors.context payload mentioning user_id — the raw id must never reach a finding.'
        );
    }

    /**
     * @return list<string>
     */
    private static function etlSourceFiles(): array
    {
        return array_merge(
            glob(app_path('Jobs/Import/Bank/*.php')) ?: [],
            glob(app_path('Jobs/Import/Behaviour/*.php')) ?: [],
            glob(app_path('Jobs/Import/*.php')) ?: [],
            glob(app_path('Support/Import/*.php')) ?: [],
            glob(app_path('Support/Import/Validators/*.php')) ?: [],
        );
    }

    public function test_no_etl_class_calls_the_application_logger(): void
    {
        // The ETL writes nothing to storage/logs/laravel.log at all — proven
        // here rather than assumed, so a log line can never carry a raw
        // user_id in the first place. `ImportErrorRecorder::record()` is the
        // sanctioned place an anomaly is written down, and it writes to
        // `import_errors`, not the log.
        $offenders = [];

        foreach (self::etlSourceFiles() as $file) {
            if (preg_match('/\b(Log::|logger\(|report\()/', file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'ETL file(s) calling the application logger: '.implode(', ', $offenders)
        );
    }

    public function test_no_pgsql_table_has_a_column_named_user_id(): void
    {
        // Belt-and-braces alongside NoPiiInLabSchemaTest's broader column
        // scan: this one names user_id specifically. `sessions.user_id` is
        // Laravel's own auth session table, operational rather than
        // behavioural — the same exemption NoPiiInLabSchemaTest makes.
        $tables = DB::connection('pgsql')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'user_id')
            ->where('table_name', '!=', 'sessions')
            ->pluck('table_name');

        $this->assertEmpty(
            $tables->all(),
            'Table(s) with a user_id column: '.implode(', ', $tables->all())
        );
    }
}
