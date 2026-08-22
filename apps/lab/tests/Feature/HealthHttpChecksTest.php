<?php

namespace Tests\Feature;

use App\Support\Health\AiServiceCheck;
use App\Support\Health\AiServiceDatabaseCheck;
use App\Support\Health\AiServiceRuntimeSnapshot;
use App\Support\Health\ChatModelCheck;
use App\Support\Health\CheckResult;
use App\Support\Health\EmbeddingModelCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthHttpChecksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'lab.ai_service.base_url' => 'http://127.0.0.1:8001',
            'lab.ai_service.timeout' => 10,
            'lab.embedding.models.chat' => 'gemma4:e2b-it-qat',
            'lab.embedding.models.embedding' => 'embeddinggemma:300m-qat-q4_0',
        ]);
    }

    public function test_liveness_and_database_checks_use_their_independent_endpoints(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response([
                'status' => 'ok',
                'service' => 'injazedu-lab-ai-service',
                'version' => '0.1.0',
            ]),
            'http://127.0.0.1:8001/health/db' => Http::response([
                'status' => 'ok',
                'database' => 'injazedu_lab',
                'host' => '127.0.0.1:5433',
                'server_version' => '17.6',
            ]),
        ]);

        $this->assertSame(CheckResult::PASS, app(AiServiceCheck::class)->run()->outcome);
        $this->assertSame(CheckResult::PASS, app(AiServiceDatabaseCheck::class)->run()->outcome);

        Http::assertSentCount(2);
    }

    public function test_chat_then_embedding_share_one_runtime_probe_without_hiding_model_status(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/health/ollama' => Http::response([
                'status' => 'degraded',
                'host' => '127.0.0.1:11434',
                'models' => [
                    'gemma4:e2b-it-qat' => ['status' => 'ok', 'latency_ms' => 47],
                    'embeddinggemma:300m-qat-q4_0' => [
                        'status' => 'error',
                        'error' => 'model_unavailable',
                        'detail' => 'connection refused',
                    ],
                ],
            ], 503),
        ]);

        $snapshot = app(AiServiceRuntimeSnapshot::class);
        $chat = new ChatModelCheck($snapshot);
        $embedding = new EmbeddingModelCheck($snapshot);

        $this->assertSame(CheckResult::PASS, $chat->run()->outcome);
        $embeddingResult = $embedding->run();
        $this->assertSame(CheckResult::FAIL, $embeddingResult->outcome);
        $this->assertStringContainsString('ollama:11434', $embeddingResult->detail);
        $this->assertStringContainsString('embeddinggemma:300m-qat-q4_0', $embeddingResult->detail);
        Http::assertSentCount(1);
    }
}
