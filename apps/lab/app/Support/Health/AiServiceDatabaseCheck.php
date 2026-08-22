<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Http;

final class AiServiceDatabaseCheck extends AbstractHealthCheck
{
    public function number(): int
    {
        return 4;
    }

    public function name(): string
    {
        return 'Service to Lab database';
    }

    public function target(): string
    {
        return 'ai-service:8001/health/db';
    }

    public function expectation(): string
    {
        return CheckResult::MUST_SUCCEED;
    }

    public function run(): CheckResult
    {
        $response = Http::baseUrl((string) config('lab.ai_service.base_url'))
            ->timeout((int) config('lab.ai_service.timeout'))
            ->acceptJson()
            ->get('/health/db');

        if (! $response->successful() || $response->json('status') !== 'ok') {
            $reason = $response->json('detail') ?? $response->body();

            return $this->fail("postgres:5433 via ai-service:8001 failed: {$reason}");
        }

        return $this->pass(sprintf(
            'postgres:5433 reached by ai-service:8001 (PostgreSQL %s)',
            $response->json('server_version'),
        ));
    }
}
