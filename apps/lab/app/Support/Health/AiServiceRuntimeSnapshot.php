<?php

namespace App\Support\Health;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class AiServiceRuntimeSnapshot
{
    private ?array $payload = null;

    /** @return array<string, mixed> */
    public function get(): array
    {
        if ($this->payload !== null) {
            return $this->payload;
        }

        try {
            $response = Http::baseUrl((string) config('lab.ai_service.base_url'))
                ->timeout((int) config('lab.ai_service.timeout'))
                ->acceptJson()
                ->get('/health/ollama');

            $this->payload = $response->json() ?? [
                'status' => 'error',
                'detail' => $response->body(),
                'models' => [],
            ];
        } catch (ConnectionException $exception) {
            $this->payload = [
                'status' => 'error',
                'host' => '127.0.0.1:11434',
                'detail' => $exception->getMessage(),
                'models' => [],
            ];
        }

        return $this->payload;
    }

    public function clear(): void
    {
        $this->payload = null;
    }
}
