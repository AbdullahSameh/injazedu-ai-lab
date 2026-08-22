<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Source copy allowlist (FR-003, data-model.md §2)
    |--------------------------------------------------------------------------
    |
    | The eleven tables of the InjazEdu MySQL source the Lab may read. The
    | database holds fifty; the other thirty-nine are unreachable through
    | App\Support\SourceReader, the only sanctioned path (guard 3).
    |
    | `results` and `question_result` carry user_id — readable by design, never
    | storable. P1's ETL converts it to student_ref on the way in.
    |
    */

    'source_tables' => [
        'categories',
        'courses',
        'chapters',
        'lectures',
        'quizzes',
        'sections',
        'questions',
        'options',
        'quiz_files',
        'results',
        'question_result',
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot provenance (FR-006)
    |--------------------------------------------------------------------------
    |
    | The date the local copy of the InjazEdu database was taken. Carried in
    | apps/lab/.env as SNAPSHOT_TAKEN_AT.
    |
    */

    'snapshot_taken_at' => env('SNAPSHOT_TAKEN_AT'),

    /*
    |--------------------------------------------------------------------------
    | Embedding contract (FR-004, data-model.md §3)
    |--------------------------------------------------------------------------
    |
    | One opaque string, carried by every vector from P2 onward. Changing any
    | component — model tag, prefix template, dimension, or normalization —
    | silently invalidates every stored vector (§12.2). This is configuration,
    | not housekeeping.
    |
    | The prefix has exactly one owner: the service applies it, callers send
    | raw text. A caller that pre-applies it produces wrong vectors with no
    | error.
    |
    */

    'embedding' => [
        'config_version' => env('EMBEDDING_CONFIG_VERSION'),
        'prefix_template' => 'task: sentence similarity | query: {text}',
        'dimension' => 768,
        'models' => [
            'chat' => 'gemma4:e2b-it-qat',
            'embedding' => 'embeddinggemma:300m-qat-q4_0',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI service (contracts/ai-service.md)
    |--------------------------------------------------------------------------
    |
    | The stateless FastAPI service on loopback — the Lab's only door to the
    | model runtime. The timeout covers the measured cold service runtime
    | probe (6.378 s chat + 1.645 s embed; notes N7) with headroom.
    |
    */

    'ai_service' => [
        'base_url' => env('AI_SERVICE_URL'),
        'timeout' => 10,
    ],

];
