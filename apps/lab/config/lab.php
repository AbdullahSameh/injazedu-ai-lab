<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Source COPY allowlist — governs COPYING INTO the Lab, not reading
    |--------------------------------------------------------------------------
    |
    | The eleven tables of the InjazEdu MySQL source the Lab may COPY INTO its
    | own database. This list is the copy check: P1's ETL must call
    | App\Support\SourceReader::assertCopyable() before writing any row.
    |
    | It does NOT govern reading — reading is source_tables ∪ profile_tables.
    | Never merge the two lists into one union check: reading a count is not
    | storing a row, and the split between them is the safety property
    | (P0 §3.2, ADR-021 revised 2026-08-23, FR-001).
    |
    | `results` and `question_result` carry user_id — copyable by design,
    | never storable as-is. P1's ETL converts it to student_ref on the way in.
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
    | Profile allowlist — governs READING AS COUNTS, never copying
    |--------------------------------------------------------------------------
    |
    | Six additional InjazEdu tables that may be READ (as counts/aggregates for
    | §6 profiling) but may NEVER be copied into the Lab database. Added
    | 2026-08-23 (P0 §3.2) so §6 queries 15, 16 and 18 can run in P1.
    |
    | This list is NOT a copy check: assertCopyable() accepts source_tables
    | alone. A table on this list is read-only in the strongest sense — its
    | rows stay in the source, always (P0 §3.2, ADR-021 revised, FR-001).
    |
    */

    'profile_tables' => [
        'course_user',
        'course_order',
        'orders',
        'user_roles',
        'roles',
        'book_course',
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
    | Import (FR-022)
    |--------------------------------------------------------------------------
    |
    | Shared configuration for `php artisan lab:import`. `chunk_size` is the
    | default `--chunk` batch size for the ~13.8M-row behavioural tables,
    | tuned by measurement, not assumed correct. `source_system` is the
    | constant every mirror table's `source_system` column carries — one
    | value, because this Lab has exactly one source.
    |
    */

    'import' => [
        'chunk_size' => 10000,
        'source_system' => 'injazedu_production',
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiling (FR-004)
    |--------------------------------------------------------------------------
    |
    | Where `php artisan lab:profile` finds the eighteen §6 query files and
    | where it generates the human-readable report. The report is generated
    | from `source_snapshots.profiling_results` alone (FR-005) — this path is
    | an output location, never a second source of truth.
    |
    */

    'profiling' => [
        'sql_path' => base_path('../../sql/profiling'),
        'report_path' => base_path('../../docs/reports/p1-profiling.md'),
    ],

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
