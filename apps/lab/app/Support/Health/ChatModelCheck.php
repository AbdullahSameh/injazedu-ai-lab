<?php

namespace App\Support\Health;

final class ChatModelCheck extends AbstractHealthCheck
{
    public function __construct(private readonly AiServiceRuntimeSnapshot $snapshot) {}

    public function number(): int
    {
        return 5;
    }

    public function name(): string
    {
        return 'Service to chat model';
    }

    public function target(): string
    {
        return (string) config('lab.embedding.models.chat');
    }

    public function expectation(): string
    {
        return CheckResult::MUST_SUCCEED;
    }

    public function run(): CheckResult
    {
        $payload = $this->snapshot->get();
        $model = $this->target();
        $status = $payload['models'][$model] ?? null;

        if (($status['status'] ?? null) !== 'ok') {
            $reason = $status['detail'] ?? $payload['detail'] ?? 'model status missing';

            return $this->fail("ollama:11434 model {$model} failed: {$reason}");
        }

        return $this->pass("ollama:11434 model {$model} answered in {$status['latency_ms']} ms");
    }
}
