<?php

namespace App\Providers;

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
use Illuminate\Support\Facades\DB;
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
    }
}
