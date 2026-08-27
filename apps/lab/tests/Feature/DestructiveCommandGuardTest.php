<?php

namespace Tests\Feature;

use App\Exceptions\DestructiveOperationBlocked;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The guard added 2026-08-27 after a destructive artisan command wiped real,
 * manually-imported data in injazedu_lab.
 *
 * Verified WITHOUT ever pointing any connection at the real 'injazedu_lab' —
 * a plain config assertion proves it is excluded from the safe list, and
 * every other case runs against the already-provisioned, disposable
 * injazedu_lab_test with the safe list temporarily cleared, so a passing
 * "refused" test proves the guard's decision logic without needing a second
 * throwaway database or ever reproducing the incident it guards against.
 *
 * migrate:fresh / db:wipe route through Artisan::call() here (Laravel's own
 * test helpers do the same), which does not dispatch CommandStarting — so
 * these exercise guardDestructiveStatements(), the statement-level guard
 * that fires regardless of how the command was invoked. That guard is what
 * actually matters for RefreshDatabase's own internal migrate:fresh, which
 * also goes through Artisan::call() and could never be caught by a
 * command-name guard alone.
 */
class DestructiveCommandGuardTest extends TestCase
{
    public function test_injazedu_lab_is_never_in_the_safe_list(): void
    {
        $this->assertNotContains('injazedu_lab', config('lab.safe_destructive_databases'));
    }

    public function test_migrate_fresh_is_refused_when_the_current_database_is_not_on_the_safe_list(): void
    {
        // injazedu_lab_test already has real tables from this process's own
        // RefreshDatabase boot, so migrate:fresh's internal db:wipe actually
        // reaches the drop-all-tables statement this guard looks for.
        Config::set('lab.safe_destructive_databases', []);

        $this->expectException(DestructiveOperationBlocked::class);

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_db_wipe_is_refused_when_the_current_database_is_not_on_the_safe_list(): void
    {
        Config::set('lab.safe_destructive_databases', []);

        $this->expectException(DestructiveOperationBlocked::class);

        Artisan::call('db:wipe', ['--force' => true]);
    }

    public function test_an_ordinary_migration_style_drop_is_not_refused(): void
    {
        // Schema::dropIfExists() — single table, IF EXISTS — is what a
        // normal migration issues. The guard must never block this, even
        // with the safe list cleared, or ordinary schema evolution breaks.
        Config::set('lab.safe_destructive_databases', []);

        DB::connection('pgsql')->statement('drop table if exists "a_table_that_does_not_exist"');

        $this->addToAssertionCount(1);
    }

    public function test_a_raw_drop_database_statement_is_refused_when_the_named_target_is_not_on_the_safe_list(): void
    {
        $this->expectException(DestructiveOperationBlocked::class);

        // Never the currently-open connection's own database — Postgres
        // refuses that regardless of this guard. A harmless, nonexistent
        // name proves the guard inspects the DROP's named target.
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS a_database_that_does_not_exist');
    }

    public function test_migrate_fresh_is_allowed_against_the_disposable_test_database(): void
    {
        $this->assertSame('injazedu_lab_test', config('database.connections.pgsql.database'));
        $this->assertContains('injazedu_lab_test', config('lab.safe_destructive_databases'));

        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]));
    }
}
