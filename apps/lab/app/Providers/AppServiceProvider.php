<?php

namespace App\Providers;

use App\Exceptions\DestructiveOperationBlocked;
use App\Exceptions\ReadOnlyViolation;
use App\Support\Health\AiServiceCheck;
use App\Support\Health\AiServiceDatabaseCheck;
use App\Support\Health\AiServiceRuntimeSnapshot;
use App\Support\Health\ChatModelCheck;
use App\Support\Health\EmbeddingModelCheck;
use App\Support\Health\ForbiddenTableCheck;
use App\Support\Health\HealthMatrix;
use App\Support\Health\LabDatabaseCheck;
use App\Support\Health\QueueExecutionCheck;
use App\Support\Health\SourceQuestionsCheck;
use App\Support\Health\SourceWriteCheck;
use App\Support\Health\VectorRoundTripCheck;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiServiceRuntimeSnapshot::class);

        $this->app->singleton(HealthMatrix::class, function ($app) {
            $runtime = $app->make(AiServiceRuntimeSnapshot::class);

            return new HealthMatrix([
                $app->make(LabDatabaseCheck::class),
                $app->make(AiServiceCheck::class),
                $app->make(QueueExecutionCheck::class),
                $app->make(AiServiceDatabaseCheck::class),
                $app->make(ChatModelCheck::class),
                $app->make(EmbeddingModelCheck::class),
                $app->make(VectorRoundTripCheck::class),
                $app->make(SourceQuestionsCheck::class),
                $app->make(SourceWriteCheck::class),
                $app->make(ForbiddenTableCheck::class),
            ], $runtime);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Guard 2 of the source read-only enforcement (FR-002, data-model.md §1).
        //
        // beforeExecuting runs BEFORE the statement is sent, so the throw below
        // prevents execution rather than merely observing it. Registering the
        // callback resolves the connection object but does not open a PDO
        // connection — that happens on the first query.
        DB::connection('injazedu')->beforeExecuting(function (string $query) {
            $keyword = strtoupper(strtok(ltrim($query), " \t\n\r("));

            if (! in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) {
                throw ReadOnlyViolation::forStatement($query);
            }
        });

        $this->guardDestructiveCommands();
        $this->guardDestructiveStatements();
    }

    /**
     * Refuses migrate:fresh / migrate:refresh / migrate:reset / db:wipe
     * outside config('lab.safe_destructive_databases') — see
     * config/lab.php and App\Exceptions\DestructiveOperationBlocked. This is
     * what stops one of these commands, run without --env=testing, from
     * resetting the developer's real injazedu_lab database instead of the
     * disposable injazedu_lab_test.
     */
    private function guardDestructiveCommands(): void
    {
        $destructive = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'];

        Event::listen(CommandStarting::class, function (CommandStarting $event) use ($destructive) {
            if (! in_array($event->command, $destructive, true)) {
                return;
            }

            $connection = ($event->input->hasOption('database') ? $event->input->getOption('database') : null)
                ?: config('database.default');

            $database = config("database.connections.{$connection}.database");

            if (! in_array($database, config('lab.safe_destructive_databases', []), true)) {
                throw DestructiveOperationBlocked::forCommand($event->command, $connection, $database);
            }
        });
    }

    /**
     * The real backstop: this fires for every statement on the pgsql
     * connection regardless of how it was issued — CLI, Artisan::call(),
     * RefreshDatabase's own internal migrate:fresh — because it hooks
     * Connection::run() itself rather than a specific command name.
     *
     * beforeExecuting() is bound to one Connection *instance* and is lost
     * whenever that instance is purged and reconnected (DB::purge('pgsql'),
     * which the MirrorValidation suite's repoint does, and which
     * migrate:fresh itself does internally when creating a missing
     * database) — that gap is exactly what let the first version of this
     * guard miss a real case in testing. Re-registering on every
     * ConnectionEstablished (dispatched on connect *and* reconnect) closes
     * it: the callback is always attached to whichever instance is live.
     */
    private function guardDestructiveStatements(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection->getName() !== 'pgsql') {
                return;
            }

            $event->connection->beforeExecuting(function (string $query) use ($event) {
                $normalized = trim($query);

                // DROP DATABASE names its target explicitly and always runs
                // from a *different* database (Postgres refuses to drop the
                // one it's connected to) — the target name is what to check,
                // not whatever database issued the statement.
                if (preg_match('/^\s*drop\s+database\s+(?:if\s+exists\s+)?"?([a-zA-Z_][\w$]*)"?/i', $normalized, $matches)) {
                    if (! in_array($matches[1], config('lab.safe_destructive_databases', []), true)) {
                        throw DestructiveOperationBlocked::forStatement($query, $matches[1]);
                    }

                    return;
                }

                // DROP SCHEMA and db:wipe's own SQL (PostgresGrammar::
                // compileDropAllTables/Views/Types/Domains — every object at
                // once, no IF EXISTS, terminated by CASCADE) both act on
                // whichever database the connection currently points at. An
                // ordinary migration's Schema::dropIfExists() always carries
                // IF EXISTS and never matches this shape.
                $isSchemaDrop = (bool) preg_match('/^\s*drop\s+schema\b/i', $normalized);
                $isWipeStyleDrop = preg_match('/^\s*drop\s+(table|view|type|domain)\s+/i', $normalized)
                    && ! preg_match('/\bif\s+exists\b/i', $normalized)
                    && preg_match('/\bcascade\s*;?\s*$/i', $normalized);

                if (! $isSchemaDrop && ! $isWipeStyleDrop) {
                    return;
                }

                $database = $event->connection->getDatabaseName();

                if (! in_array($database, config('lab.safe_destructive_databases', []), true)) {
                    throw DestructiveOperationBlocked::forStatement($query, $database);
                }
            });
        });
    }
}
