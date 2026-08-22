<?php

namespace App\Providers;

use App\Exceptions\ReadOnlyViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
