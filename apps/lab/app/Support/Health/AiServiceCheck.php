<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Http;

final class AiServiceCheck extends AbstractHealthCheck
{
    public function number(): int
    {
        return 2;
    }

    public function name(): string
    {
        return 'AI service';
    }

    public function target(): string
    {
        return 'ai-service:8001';
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
            ->get('/health');

        if (! $response->successful() || $response->json('status') !== 'ok') {
            return $this->fail('ai-service:8001 liveness failed: '.$response->body());
        }

        return $this->pass(sprintf(
            'ai-service:8001 answered %s v%s',
            $response->json('service'),
            $response->json('version'),
        ));
    }
}
