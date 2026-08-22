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

];
